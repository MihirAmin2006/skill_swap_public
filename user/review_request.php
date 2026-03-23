<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
include_once __DIR__ . '/validation.php';
require_once __DIR__ . '/../includes/scripts/connection.php';
include_once __DIR__ . '/../mailSender/main.php';

if (
    empty($_SESSION['isloggedin']) ||
    $_SESSION['isloggedin'] !== true ||
    empty($_SESSION['login_token'])
) {
    session_unset();
    session_destroy();
    header('Location: ../Auth/sign_in.php');
    exit;
}

// ── AJAX: Approve a meeting ───────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'approve') {
    header('Content-Type: application/json');

    $meeting_id = $_POST['meeting_id'] ?? '';
    $student_id = (int)($_POST['student_id'] ?? 0);
    $teacher_id = (int)$_SESSION['user_id'];

    define('SESSION_COST', 10);

    // ── Fetch meeting details (also confirms this teacher owns the meeting) ────
    $s = $conn->prepare("
        SELECT m.meeting_time, m.approved, m.student_id,
               sm.sub_name,
               um.full_name  AS teacher_name, um.email AS teacher_email,
               us.full_name  AS student_name, us.email AS student_email,
               us.credit     AS student_credit
        FROM meetings m
        JOIN subject_master sm ON m.sub_id  = sm.sub_id
        JOIN user_master    um ON m.teacher_id = um.user_id
        JOIN user_master    us ON m.student_id = us.user_id
        WHERE m.meeting_id = ? AND m.teacher_id = ?
    ");
    $s->bind_param("si", $meeting_id, $teacher_id);
    $s->execute();
    $details = $s->get_result()->fetch_assoc();
    $s->close();

    if (!$details) {
        echo json_encode(['success' => false, 'message' => 'Meeting not found or access denied.']);
        exit;
    }

    if ($details['approved'] === '1') {
        echo json_encode(['success' => false, 'message' => 'This session has already been approved.']);
        exit;
    }

    if ((int)$details['student_credit'] < SESSION_COST) {
        echo json_encode(['success' => false, 'message' => 'Student no longer has enough credits to confirm this session.']);
        exit;
    }

    // ── Atomic transaction: approve + deduct student + credit teacher ─────────
    $conn->begin_transaction();
    try {
        $cost = SESSION_COST;

        // 1. Mark meeting as approved
        $s = $conn->prepare("UPDATE `meetings` SET `approved` = '1' WHERE `meeting_id` = ? AND `teacher_id` = ?");
        $s->bind_param("si", $meeting_id, $teacher_id);
        if (!$s->execute()) throw new Exception('Could not approve meeting.');
        $s->close();

        // 2. Deduct credits from student (guard against race condition)
        $s = $conn->prepare("UPDATE `user_master` SET `credit` = `credit` - ? WHERE `user_id` = ? AND `credit` >= ?");
        $s->bind_param("iii", $cost, $student_id, $cost);
        if (!$s->execute())        throw new Exception('Credit deduction query failed.');
        if ($s->affected_rows < 1) throw new Exception('Student has insufficient credits.');
        $s->close();

        // 3. Add credits to teacher
        $s = $conn->prepare("UPDATE `user_master` SET `credit` = `credit` + ? WHERE `user_id` = ?");
        $s->bind_param("ii", $cost, $teacher_id);
        if (!$s->execute()) throw new Exception('Could not award credits to teacher.');
        $s->close();

        // 4. Notify student
        $formatted_time = date('D, d M Y \a\t h:i A', strtotime($details['meeting_time']));
        $msg_student = "Your session for {$details['sub_name']} has been approved by {$details['teacher_name']}. " .
                       "Scheduled on {$formatted_time}. {$cost} credits have been deducted from your account.";
        $s = $conn->prepare("INSERT INTO `notifications` (`user_id`, `msg`) VALUES (?, ?)");
        $s->bind_param("is", $student_id, $msg_student);
        if (!$s->execute()) throw new Exception('Student notification failed.');
        $s->close();

        // 5. Notify teacher
        $msg_teacher = "You approved the session with {$details['student_name']} for {$details['sub_name']} " .
                       "on {$formatted_time}. {$cost} credits have been added to your account.";
        $s = $conn->prepare("INSERT INTO `notifications` (`user_id`, `msg`) VALUES (?, ?)");
        $s->bind_param("is", $teacher_id, $msg_teacher);
        if (!$s->execute()) throw new Exception('Teacher notification failed.');
        $s->close();

        $conn->commit();

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }

    // ── Send approval email to student ────────────────────────────────────────
    sendMail(
        $details['student_email'],
        "Your Session Has Been Approved",
        "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>
        body{font-family:Arial,sans-serif;background:#f4f6f9;margin:0;padding:0;}
        .container{max-width:600px;margin:30px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 4px 10px rgba(0,0,0,0.08);}
        .header{background:#10b981;color:white;padding:20px;text-align:center;font-size:22px;font-weight:bold;}
        .content{padding:25px;color:#333;line-height:1.6;}
        .button{display:inline-block;padding:12px 20px;margin-top:15px;background:#10b981;color:white !important;text-decoration:none;border-radius:5px;font-weight:bold;}
        .footer{text-align:center;padding:15px;font-size:13px;color:#777;background:#f4f6f9;}
        </style></head><body>
        <div class='container'>
        <div class='header'>Session Approved ✓</div>
        <div class='content'>
        <p>Hello <b>{$details['student_name']}</b>,</p>
        <p>Your session for <b>{$details['sub_name']}</b> has been approved by <b>{$details['teacher_name']}</b>.</p>
        <p><b>Scheduled On:</b> {$formatted_time}</p>
        <p><b>" . SESSION_COST . " credits</b> have been deducted from your account.</p>
        <a href='http://localhost:80/skill_swap/Auth/sign_in' class='button'>View Session</a>
        </div>
        <div class='footer'>© " . date('Y') . " Virtual Meeting System<br>Automated Notification</div>
        </div></body></html>"
    );

    echo json_encode(['success' => true, 'message' => 'Session approved successfully. Credits transferred.']);
    exit;
}

// ── AJAX: Reject a meeting ────────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'reject') {
    $meeting_id = $_POST['meeting_id'] ?? '';
    $student_id = (int)($_POST['student_id'] ?? 0);
    $reason     = trim($_POST['reason'] ?? '');

    if (empty($reason)) {
        echo json_encode(['success' => false, 'message' => 'Please provide a rejection reason.']);
        exit;
    }

    // Delete the meeting (or you could add a 'rejected' status if preferred)
    $s = $conn->prepare("UPDATE `meetings` SET `approved` = '0' WHERE `meeting_id` = ? AND `teacher_id` = ?");
    $s->bind_param("si", $meeting_id, $_SESSION['user_id']);
    $s->execute();
    $s->close();

        $s = $conn->prepare("
    SELECT m.meeting_time, sm.sub_name, um.full_name AS teacher_name,
           um.email AS teacher_email,        
           us.full_name AS student_name,     
           us.email AS student_email         
    FROM meetings m
    JOIN subject_master sm ON m.sub_id = sm.sub_id
    JOIN user_master um ON m.teacher_id = um.user_id
    JOIN user_master us ON m.student_id = us.user_id  
    WHERE m.meeting_id = ?
");
    $s->bind_param("s", $meeting_id);
    $s->execute();
    $details = $s->get_result()->fetch_assoc();
    $s->close();

    // No credits were deducted at booking time, so no refund is needed on rejection
    $msg = "Your session request for {$details['sub_name']} was declined by {$details['teacher_name']}. Reason: {$reason}. No credits have been deducted.";

    // Notify student
    $s = $conn->prepare("INSERT INTO `notifications` (`user_id`, `msg`) VALUES (?, ?)");
$s->bind_param("is", $student_id, $msg);
$s->execute();
$s->close();
sendMail(
    $details['student_email'],
    "Your Session Request Was Declined",
    "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>
    body{font-family:Arial,sans-serif;background:#f4f6f9;margin:0;padding:0;}
    .container{max-width:600px;margin:30px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 4px 10px rgba(0,0,0,0.08);}
    .header{background:#ef4444;color:white;padding:20px;text-align:center;font-size:22px;font-weight:bold;}
    .content{padding:25px;color:#333;line-height:1.6;}
    .footer{text-align:center;padding:15px;font-size:13px;color:#777;background:#f4f6f9;}
    </style></head><body>
    <div class='container'>
    <div class='header'>Session Declined</div>
    <div class='content'>
    <p>Hello <b>{$details['student_name']}</b>,</p>
    <p>Your session request for <b>{$details['sub_name']}</b> was declined by <b>{$details['teacher_name']}</b>.</p>
    <p><b>Reason:</b> {$reason}</p>
    <p style='margin-top:25px'>You can submit a new request for a different time.</p>
    </div>
    <div class='footer'>© " . date('Y') . " Virtual Meeting System<br>Automated Notification</div>
    </div></body></html>"
);

    echo json_encode(['success' => true, 'message' => 'Session rejected and student notified.']);
    exit;
}

// ── Fetch logged-in user ──────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM `user_master` WHERE `user_id` = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user_detials = $stmt->get_result()->fetch_assoc();
$name = explode(" ", $user_detials['full_name']);
$stmt->close();

// ── Fetch pending session requests for this teacher ───────────────────────────
// approved = '0' means pending
$stmt = $conn->prepare("
    SELECT
        m.meeting_id,
        m.student_id,
        m.meeting_time,
        m.approved,
        m.status,
        sm.sub_name,
        m.topic,
        um.full_name   AS student_name,
        um.user_name   AS student_username,
        um.profile_pic AS student_pic,
        um.rating      AS student_rating,
        um.bio         AS student_bio
    FROM meetings m
    JOIN subject_master sm ON m.sub_id = sm.sub_id
    JOIN user_master um    ON m.student_id = um.user_id
    WHERE m.teacher_id = ?
      AND m.approved = '2'
      AND not m.status = 'completed'
    ORDER BY m.meeting_time ASC
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function human_time(string $ts_str): string
{
    $ts   = strtotime($ts_str);
    $diff = $ts - time(); // future
    if ($diff < 0)      return 'Overdue — ' . date('d M Y, h:i A', $ts);
    if ($diff < 3600)   return 'In ' . floor($diff / 60) . ' min';
    if ($diff < 86400)  return 'In ' . floor($diff / 3600) . ' hr' . (floor($diff / 3600) > 1 ? 's' : '');
    if ($diff < 172800) return 'Tomorrow, ' . date('h:i A', $ts);
    return date('D, d M Y', $ts) . ' at ' . date('h:i A', $ts);
}
?>
<!DOCTYPE html>
<html lang="en" class="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include_once __DIR__ . '/../includes/scripts/common.php'; ?>
    <link rel="stylesheet" href="../assets/styles/user/common.css">
    <script src="../assets/js/tailwind.js.php" defer></script>

    <style>
        :root {
            --royal: #1e3a8a;
            --royal-mid: #1d4ed8;
            --royal-soft: #93c5fd;
            --success: #10b981;
            --danger: #ef4444;
            --warn: #f59e0b;
        }

        /* ── Page Layout ── */
        .requests-feed {
            padding: 2rem 1.5rem 4rem;
            max-width: 860px;
            margin: 0 auto;
        }

        /* ── Stats Bar ── */
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.75);
            border: 1.5px solid rgba(30, 58, 138, 0.07);
            backdrop-filter: blur(12px);
            border-radius: 16px;
            padding: 1.1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: slideIn 0.4s ease both;
        }

        html.dark .stat-card {
            background: rgba(15, 23, 42, 0.6);
            border-color: rgba(147, 197, 253, 0.1);
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .stat-icon svg {
            width: 20px;
            height: 20px;
        }

        .stat-icon.pending {
            background: rgba(245, 158, 11, 0.12);
            color: #f59e0b;
        }

        .stat-icon.approved {
            background: rgba(16, 185, 129, 0.12);
            color: #10b981;
        }

        .stat-icon.total {
            background: rgba(29, 78, 216, 0.12);
            color: #1d4ed8;
        }

        .stat-info p {
            font-size: 0.72rem;
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin: 0 0 0.15rem;
        }

        .stat-info h3 {
            font-size: 1.5rem;
            font-weight: 800;
            margin: 0;
            color: #1e293b;
            line-height: 1;
        }

        html.dark .stat-info h3 {
            color: #e2e8f0;
        }

        /* ── Request Card ── */
        .request-card {
            background: rgba(255, 255, 255, 0.75);
            border: 1.5px solid rgba(30, 58, 138, 0.07);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            margin-bottom: 1rem;
            overflow: hidden;
            animation: slideIn 0.4s ease both;
            transition: box-shadow 0.2s ease, transform 0.2s ease;
            font-family: inherit;
        }

        html.dark .request-card {
            background: rgba(15, 23, 42, 0.6);
            border-color: rgba(147, 197, 253, 0.1);
        }

        .request-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 40px rgba(30, 58, 138, 0.1);
        }

        html.dark .request-card:hover {
            box-shadow: 0 10px 40px rgba(29, 78, 216, 0.2);
        }

        /* Card Header */
        .card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(30, 58, 138, 0.06);
        }

        html.dark .card-header {
            border-color: rgba(147, 197, 253, 0.08);
        }

        /* Avatar */
        .student-avatar {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            object-fit: cover;
            flex-shrink: 0;
        }

        .student-avatar-placeholder {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            background: linear-gradient(135deg, #1e3a8a, #1d4ed8);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .student-info {
            flex: 1;
            min-width: 0;
        }

        .student-name {
            font-size: 1rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0 0 0.15rem;
        }

        html.dark .student-name {
            color: #e2e8f0;
        }

        .student-meta {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            flex-wrap: wrap;
        }

        .student-username {
            font-size: 0.78rem;
            color: #94a3b8;
        }

        .rating-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 0.15rem 0.55rem;
            border-radius: 999px;
            background: rgba(245, 158, 11, 0.12);
            color: #d97706;
        }

        .rating-pill svg {
            width: 11px;
            height: 11px;
            fill: #f59e0b;
        }

        .new-badge {
            font-size: 0.62rem;
            font-weight: 800;
            letter-spacing: 0.07em;
            text-transform: uppercase;
            padding: 0.15rem 0.55rem;
            border-radius: 999px;
            background: rgba(29, 78, 216, 0.12);
            color: #1d4ed8;
        }

        /* Card Body */
        .card-body {
            padding: 1.1rem 1.5rem;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 0.85rem;
            margin-bottom: 1rem;
        }

        .detail-item {
            display: flex;
            align-items: flex-start;
            gap: 0.6rem;
        }

        .detail-icon {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            background: rgba(29, 78, 216, 0.08);
            color: #1d4ed8;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 1px;
        }

        html.dark .detail-icon {
            background: rgba(147, 197, 253, 0.1);
            color: #93c5fd;
        }

        .detail-icon svg {
            width: 15px;
            height: 15px;
        }

        .detail-label {
            font-size: 0.68rem;
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0 0 0.1rem;
        }

        .detail-value {
            font-size: 0.88rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }

        html.dark .detail-value {
            color: #e2e8f0;
        }

        /* Bio snippet */
        .bio-snippet {
            font-size: 0.8rem;
            color: #64748b;
            line-height: 1.5;
            padding: 0.75rem 1rem;
            background: rgba(30, 58, 138, 0.03);
            border-left: 3px solid rgba(29, 78, 216, 0.2);
            border-radius: 0 10px 10px 0;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        html.dark .bio-snippet {
            background: rgba(147, 197, 253, 0.04);
            border-color: rgba(147, 197, 253, 0.15);
            color: #94a3b8;
        }

        /* Card Footer / Actions */
        .card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.5rem;
            border-top: 1px solid rgba(30, 58, 138, 0.06);
            flex-wrap: wrap;
        }

        html.dark .card-footer {
            border-color: rgba(147, 197, 253, 0.08);
        }

        .meeting-id-tag {
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            color: #94a3b8;
            font-family: monospace;
        }

        .action-buttons {
            display: flex;
            gap: 0.6rem;
        }

        .btn-approve,
        .btn-reject {
            font-family: inherit;
            font-size: 0.8rem;
            font-weight: 700;
            padding: 0.5rem 1.25rem;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .btn-approve {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
        }

        .btn-approve:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(16, 185, 129, 0.35);
        }

        .btn-approve:active {
            transform: translateY(0);
        }

        .btn-reject {
            background: rgba(239, 68, 68, 0.08);
            color: #ef4444;
            border: 1.5px solid rgba(239, 68, 68, 0.2);
        }

        .btn-reject:hover {
            background: rgba(239, 68, 68, 0.15);
            border-color: rgba(239, 68, 68, 0.35);
        }

        .btn-approve svg,
        .btn-reject svg {
            width: 15px;
            height: 15px;
        }

        /* ── Reject Modal ── */
        .modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 1000;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(6px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s ease;
        }

        .modal-overlay.open {
            opacity: 1;
            pointer-events: all;
        }

        .modal-box {
            background: white;
            border-radius: 24px;
            padding: 2rem;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 24px 64px rgba(0, 0, 0, 0.15);
            transform: translateY(20px) scale(0.97);
            transition: transform 0.25s ease;
        }

        html.dark .modal-box {
            background: #0f172a;
            border: 1.5px solid rgba(147, 197, 253, 0.1);
        }

        .modal-overlay.open .modal-box {
            transform: translateY(0) scale(1);
        }

        .modal-icon {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.25rem;
        }

        .modal-icon svg {
            width: 24px;
            height: 24px;
        }

        .modal-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: #1e293b;
            margin: 0 0 0.35rem;
        }

        html.dark .modal-title {
            color: #e2e8f0;
        }

        .modal-subtitle {
            font-size: 0.83rem;
            color: #64748b;
            margin: 0 0 1.25rem;
        }

        html.dark .modal-subtitle {
            color: #94a3b8;
        }

        .modal-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #64748b;
            margin-bottom: 0.5rem;
        }

        html.dark .modal-label {
            color: #94a3b8;
        }

        .modal-textarea {
            width: 100%;
            min-height: 100px;
            padding: 0.85rem 1rem;
            border-radius: 14px;
            border: 1.5px solid rgba(30, 58, 138, 0.15);
            background: rgba(248, 250, 252, 0.8);
            font-family: inherit;
            font-size: 0.88rem;
            color: #1e293b;
            resize: vertical;
            outline: none;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }

        html.dark .modal-textarea {
            background: rgba(15, 23, 42, 0.8);
            border-color: rgba(147, 197, 253, 0.15);
            color: #e2e8f0;
        }

        .modal-textarea:focus {
            border-color: #1d4ed8;
        }

        .modal-textarea::placeholder {
            color: #94a3b8;
        }

        .char-counter {
            font-size: 0.7rem;
            color: #94a3b8;
            text-align: right;
            margin: 0.3rem 0 1.25rem;
        }

        .modal-actions {
            display: flex;
            gap: 0.75rem;
        }

        .btn-modal-cancel {
            flex: 1;
            font-family: inherit;
            font-size: 0.85rem;
            font-weight: 700;
            padding: 0.7rem;
            border-radius: 12px;
            border: 1.5px solid rgba(30, 58, 138, 0.15);
            background: transparent;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-modal-cancel:hover {
            background: rgba(30, 58, 138, 0.05);
        }

        html.dark .btn-modal-cancel {
            border-color: rgba(147, 197, 253, 0.15);
            color: #94a3b8;
        }

        .btn-modal-confirm {
            flex: 1;
            font-family: inherit;
            font-size: 0.85rem;
            font-weight: 700;
            padding: 0.7rem;
            border-radius: 12px;
            border: none;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25);
        }

        .btn-modal-confirm:hover {
            box-shadow: 0 6px 18px rgba(239, 68, 68, 0.35);
            transform: translateY(-1px);
        }

        /* ── Toast ── */
        .toast {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            z-index: 2000;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.85rem 1.25rem;
            border-radius: 14px;
            background: #1e293b;
            color: white;
            font-size: 0.85rem;
            font-weight: 600;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            transform: translateY(20px);
            opacity: 0;
            transition: all 0.3s ease;
            pointer-events: none;
            max-width: 360px;
        }

        html.dark .toast {
            background: #1d4ed8;
        }

        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }

        .toast.success {
            background: #059669;
        }

        .toast.error {
            background: #dc2626;
        }

        .toast svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }

        /* ── Empty State ── */
        .empty-state {
            text-align: center;
            padding: 5rem 2rem;
        }

        .empty-icon {
            width: 88px;
            height: 88px;
            margin: 0 auto 1.5rem;
            border-radius: 28px;
            background: rgba(30, 58, 138, 0.06);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .empty-icon svg {
            width: 40px;
            height: 40px;
            color: #93c5fd;
        }

        .empty-state h3 {
            font-size: 1.2rem;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        html.dark .empty-state h3 {
            color: #e2e8f0;
        }

        .empty-state p {
            font-size: 0.85rem;
            color: #94a3b8;
            max-width: 320px;
            margin: 0 auto;
        }

        /* ── Section Header ── */
        .page-header-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .pending-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 24px;
            height: 24px;
            padding: 0 7px;
            border-radius: 999px;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            font-size: 0.72rem;
            font-weight: 800;
        }

        /* ── Animations ── */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(14px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: scale(1);
                max-height: 400px;
                margin-bottom: 1rem;
            }

            to {
                opacity: 0;
                transform: scale(0.97);
                max-height: 0;
                margin-bottom: 0;
            }
        }

        .removing {
            animation: fadeOut 0.35s ease forwards;
            pointer-events: none;
            overflow: hidden;
        }

        .request-card {
            animation-delay: calc(var(--i, 0) * 80ms);
        }

        @media (max-width: 640px) {
            .stats-bar {
                grid-template-columns: 1fr;
            }

            .detail-grid {
                grid-template-columns: 1fr 1fr;
            }

            .card-footer {
                flex-direction: column;
                align-items: stretch;
            }

            .action-buttons {
                width: 100%;
            }

            .btn-approve,
            .btn-reject {
                flex: 1;
                justify-content: center;
            }
        }
    </style>
</head>

<body class="font-sans bg-page-light text-ink-light overflow-x-hidden dark:bg-page-dark dark:text-ink-dark transition-colors duration-300 relative">

    <div id="mobileOverlay" onclick="closeMobileSidebar()"></div>
    <?php include_once __DIR__ . '/../animated-bg.php'; ?>
    <?php include_once __DIR__ . '/navbar.php'; ?>

    <main class="lg:ml-64 min-h-screen relative z-10">

        <!-- Top Bar -->
        <header class="sticky top-0 z-40 bg-white/80 dark:bg-card-dark/80 backdrop-blur-xl border-b border-royal-basic/10 dark:border-royal-violet/20 transition-colors duration-300">
            <div class="flex items-center justify-between px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center gap-3">
                    <button onclick="openMobileSidebar()" class="lg:hidden p-2 rounded-xl bg-royal-basic/5 dark:bg-royal-soft/10 hover:bg-royal-basic/10 dark:hover:bg-royal-soft/15 text-royal-basic dark:text-royal-soft transition-all duration-200">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="3" y1="12" x2="21" y2="12" />
                            <line x1="3" y1="6" x2="21" y2="6" />
                            <line x1="3" y1="18" x2="21" y2="18" />
                        </svg>
                    </button>
                    <div>
                        <h1 class="font-display font-bold text-xl sm:text-2xl text-royal-basic dark:text-royal-soft">Session Requests</h1>
                        <p class="text-xs sm:text-sm text-muted-light dark:text-muted-dark hidden sm:block">Review and manage incoming session requests</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 sm:gap-4">
                    <!-- Dark Mode Toggle -->
                    <div id="toggleWrap" class="relative cursor-pointer rounded-full bg-slate-300 dark:bg-royal-DEFAULT transition-colors duration-400" style="width:52px;height:28px;" onclick="toggleTheme()" role="button" aria-label="Toggle dark mode" tabindex="0">
                        <div id="toggleKnob" class="absolute top-0.5 left-0.5 rounded-full bg-white shadow-knob flex items-center justify-center transition-transform duration-300" style="width:22px;height:22px;">
                            <svg id="iconSun" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="#1e3a8a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="5" />
                                <line x1="12" y1="1" x2="12" y2="3" />
                                <line x1="12" y1="21" x2="12" y2="23" />
                                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" />
                                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78" />
                                <line x1="1" y1="12" x2="3" y2="12" />
                                <line x1="21" y1="12" x2="23" y2="12" />
                                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36" />
                                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22" />
                            </svg>
                            <svg id="iconMoon" class="w-3.5 h-3.5 hidden" viewBox="0 0 24 24" fill="none" stroke="#a5b4fc" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
                            </svg>
                        </div>
                    </div>
                    <!-- Profile -->
                    <div class="relative hidden sm:block">
                        <button onclick="toggleDropdown('profileDropdown')" class="flex items-center gap-2 sm:gap-3 p-2 pr-3 rounded-xl hover:bg-royal-basic/5 dark:hover:bg-royal-soft/10 transition-all duration-200">
                            <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-full flex items-center justify-center text-white font-semibold text-sm" style="background:linear-gradient(135deg,#1e3a8a,#1d4ed8);">
                                <?= htmlspecialchars($name[0][0] . ($name[1][0] ?? '')) ?>
                            </div>
                            <svg class="w-4 h-4 text-muted-light dark:text-muted-dark hidden sm:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </button>
                        <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-64 bg-white dark:bg-card-dark rounded-2xl shadow-dropdown border border-royal-basic/10 dark:border-royal-violet/20 overflow-hidden animate-scale-in opacity-0">
                            <div class="p-4 border-b border-royal-basic/10 dark:border-royal-violet/20">
                                <p class="font-semibold text-sm"><?= htmlspecialchars($user_detials['full_name']) ?></p>
                                <p class="text-xs text-muted-light dark:text-muted-dark">@<?= htmlspecialchars($user_detials['user_name']) ?></p>
                            </div>
                            <div class="p-2">
                                <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-royal-basic/5 dark:hover:bg-royal-soft/10 transition-colors">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                        <circle cx="12" cy="7" r="4" />
                                    </svg>
                                    <span class="text-sm">View Profile</span>
                                </a>
                            </div>
                            <div class="p-2 border-t border-royal-basic/10 dark:border-royal-violet/20">
                                <button id="logoutButton" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 text-red-600 dark:text-red-400 transition-colors">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                                        <polyline points="16 17 21 12 16 7" />
                                        <line x1="21" y1="12" x2="9" y2="12" />
                                    </svg>
                                    <span class="text-sm font-semibold">Logout</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- ════════════ REQUESTS FEED ════════════ -->
        <div class="requests-feed">

            <?php
            // Count total meetings for this teacher (all statuses) for stats
            $s = $conn->prepare("SELECT COUNT(*) as total, SUM(approved='1') as approved_count FROM meetings WHERE teacher_id = ?");
            $s->bind_param("i", $_SESSION['user_id']);
            $s->execute();
            $stats = $s->get_result()->fetch_assoc();
            $s->close();
            $total_meetings  = (int)($stats['total'] ?? 0);
            $approved_count  = (int)($stats['approved_count'] ?? 0);
            $pending_count   = count($requests);
            ?>

            <!-- Stats Bar -->
            <div class="stats-bar">
                <div class="stat-card" style="--i:0">
                    <div class="stat-icon pending">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10" />
                            <polyline points="12 6 12 12 16 14" />
                        </svg>
                    </div>
                    <div class="stat-info">
                        <p>Pending</p>
                        <h3 id="pendingCount"><?= $pending_count ?></h3>
                    </div>
                </div>
                <div class="stat-card" style="--i:1">
                    <div class="stat-icon approved">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                    </div>
                    <div class="stat-info">
                        <p>Approved</p>
                        <h3><?= $approved_count ?></h3>
                    </div>
                </div>
                <div class="stat-card" style="--i:2">
                    <div class="stat-icon total">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                            <line x1="16" y1="2" x2="16" y2="6" />
                            <line x1="8" y1="2" x2="8" y2="6" />
                            <line x1="3" y1="10" x2="21" y2="10" />
                        </svg>
                    </div>
                    <div class="stat-info">
                        <p>Total Sessions</p>
                        <h3><?= $total_meetings ?></h3>
                    </div>
                </div>
            </div>

            <!-- Section Header -->
            <div class="page-header-row">
                <div class="flex items-center gap-3">
                    <span style="font-size:1rem;font-weight:800;">Pending Requests</span>
                    <?php if ($pending_count > 0): ?>
                        <span class="pending-badge"><?= $pending_count ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Request Cards -->
            <?php if (empty($requests)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                        </svg>
                    </div>
                    <h3>No pending requests</h3>
                    <p>You're all caught up! New session requests from students will appear here.</p>
                </div>

            <?php else: ?>
                <?php foreach ($requests as $i => $req):
                    $initials = strtoupper(substr($req['student_name'], 0, 1));
                    $name_parts = explode(' ', $req['student_name']);
                    if (count($name_parts) > 1) $initials = strtoupper($name_parts[0][0] . end($name_parts)[0]);
                    $scheduled_time = date('D, d M Y', strtotime($req['meeting_time']));
                    $scheduled_clock = date('h:i A', strtotime($req['meeting_time']));
                    $relative_time = human_time($req['meeting_time']);
                ?>
                    <div class="request-card" data-meeting="<?= htmlspecialchars($req['meeting_id']) ?>" style="--i:<?= $i + 3 ?>">

                        <!-- Card Header: Student Info -->
                        <div class="card-header">
                            <?php if (!empty($req['student_pic'])): ?>
                                <img src="../assets/uploads/<?= htmlspecialchars($req['student_pic']) ?>"
                                    alt="<?= htmlspecialchars($req['student_name']) ?>"
                                    class="student-avatar"
                                    onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                <div class="student-avatar-placeholder" style="display:none"><?= $initials ?></div>
                            <?php else: ?>
                                <div class="student-avatar-placeholder"><?= $initials ?></div>
                            <?php endif; ?>

                            <div class="student-info">
                                <p class="student-name"><?= htmlspecialchars($req['student_name']) ?></p>
                                <div class="student-meta">
                                    <span class="student-username">@<?= htmlspecialchars($req['student_username']) ?></span>
                                    <?php if ($req['student_rating'] > 0): ?>
                                        <span class="rating-pill">
                                            <svg viewBox="0 0 24 24">
                                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                            </svg>
                                            <?= $req['student_rating'] ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="new-badge">New Request</span>
                                </div>
                            </div>

                            <!-- Time badge -->
                            <div style="text-align:right;flex-shrink:0;">
                                <div style="font-size:0.72rem;color:#94a3b8;font-weight:600;"><?= $relative_time ?></div>
                            </div>
                        </div>

                        <!-- Card Body: Details -->
                        <div class="card-body">

                            <?php if (!empty($req['student_bio'])): ?>
                                <div class="bio-snippet">"<?= htmlspecialchars($req['student_bio']) ?>"</div>
                            <?php endif; ?>

                            <div class="detail-grid">
                                <div class="detail-item">
                                    <div class="detail-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z" />
                                            <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="detail-label">Subject</p>
                                        <p class="detail-value"><?= htmlspecialchars($req['sub_name']) ?></p>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z" />
                                            <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z" />
                                        </svg>
                                    </div>
                                    <?php $topics = explode(",", $req['topic']); ?>
                                    <div>
                                        <p class="detail-label">Topic(s)</p>
                                        <p class="detail-value">
                                            <?php
                                            foreach ($topics as $a) {
                                                echo $a . "<br>";
                                            }
                                            ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                                            <line x1="16" y1="2" x2="16" y2="6" />
                                            <line x1="8" y1="2" x2="8" y2="6" />
                                            <line x1="3" y1="10" x2="21" y2="10" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="detail-label">Date</p>
                                        <p class="detail-value"><?= $scheduled_time ?></p>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="12" cy="12" r="10" />
                                            <polyline points="12 6 12 12 16 14" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="detail-label">Time</p>
                                        <p class="detail-value"><?= $scheduled_clock ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card Footer: Actions -->
                        <div class="card-footer">
                            <span></span>
                            <div class="action-buttons">
                                <button class="btn-reject"
                                    onclick="openRejectModal('<?= htmlspecialchars($req['meeting_id'], ENT_QUOTES) ?>', <?= (int)$req['student_id'] ?>, '<?= htmlspecialchars($req['student_name'], ENT_QUOTES) ?>')">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                                        <line x1="18" y1="6" x2="6" y2="18" />
                                        <line x1="6" y1="6" x2="18" y2="18" />
                                    </svg>
                                    Decline
                                </button>
                                <button class="btn-approve"
                                    onclick="approveRequest('<?= htmlspecialchars($req['meeting_id'], ENT_QUOTES) ?>', <?= (int)$req['student_id'] ?>, this)">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                                        <polyline points="20 6 9 17 4 12" />
                                    </svg>
                                    Approve
                                </button>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </main>

    <!-- ════════════ REJECT MODAL ════════════ -->
    <div class="modal-overlay" id="rejectModal" onclick="closeRejectModal(event)">
        <div class="modal-box" onclick="event.stopPropagation()">
            <div class="modal-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="15" y1="9" x2="9" y2="15" />
                    <line x1="9" y1="9" x2="15" y2="15" />
                </svg>
            </div>
            <h2 class="modal-title">Decline Session Request</h2>
            <p class="modal-subtitle" id="modalSubtitle">Please provide a reason so the student understands why their request was declined.</p>

            <p class="modal-label">Reason for declining</p>
            <textarea class="modal-textarea" id="rejectReason" placeholder="e.g. I'm unavailable at this time. Please reschedule for next week..." maxlength="300" oninput="updateCharCount(this)"></textarea>
            <p class="char-counter"><span id="charCount">0</span> / 300</p>

            <div class="modal-actions">
                <button class="btn-modal-cancel" onclick="closeRejectModal()">Cancel</button>
                <button class="btn-modal-confirm" onclick="confirmReject()">Send & Decline</button>
            </div>
        </div>
    </div>

    <!-- ════════════ TOAST ════════════ -->
    <div class="toast" id="toast">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" id="toastIcon">
            <polyline points="20 6 9 17 4 12" />
        </svg>
        <span id="toastMsg">Done!</span>
    </div>

    <script src="../assets/js/dashboard.js.php" defer></script>
    <script src="../assets/js/darkmodeToggle.js.php" defer></script>

    <script>
        const PAGE_URL = window.location.pathname;
        let _rejectMeetingId = null;
        let _rejectStudentId = null;
        let _rejectStudentName = null;

        /* ── Approve ── */
        function approveRequest(meetingId, studentId, btn) {
            btn.disabled = true;
            btn.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="animation:spin 0.8s linear infinite"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Approving…';

            fetch(PAGE_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=approve&meeting_id=${encodeURIComponent(meetingId)}&student_id=${studentId}`
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showToast('Session approved! Student has been notified.', 'success');
                        removeCard(meetingId);
                        decrementPending();
                    } else {
                        showToast(data.message || 'Something went wrong.', 'error');
                        btn.disabled = false;
                        btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="width:15px;height:15px"><polyline points="20 6 9 17 4 12"/></svg> Approve';
                    }
                });
        }

        /* ── Reject Modal ── */
        function openRejectModal(meetingId, studentId, studentName) {
            _rejectMeetingId = meetingId;
            _rejectStudentId = studentId;
            _rejectStudentName = studentName;
            document.getElementById('modalSubtitle').textContent =
                `Let ${studentName} know why their request was declined.`;
            document.getElementById('rejectReason').value = '';
            document.getElementById('charCount').textContent = '0';
            document.getElementById('rejectModal').classList.add('open');
        }

        function closeRejectModal(e) {
            if (!e || e.target === document.getElementById('rejectModal')) {
                document.getElementById('rejectModal').classList.remove('open');
            }
        }

        function updateCharCount(el) {
            document.getElementById('charCount').textContent = el.value.length;
        }

        function confirmReject() {
            const reason = document.getElementById('rejectReason').value.trim();
            if (!reason) {
                document.getElementById('rejectReason').focus();
                document.getElementById('rejectReason').style.borderColor = '#ef4444';
                setTimeout(() => document.getElementById('rejectReason').style.borderColor = '', 1500);
                return;
            }

            const confirmBtn = document.querySelector('.btn-modal-confirm');
            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Sending…';

            fetch(PAGE_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=reject&meeting_id=${encodeURIComponent(_rejectMeetingId)}&student_id=${_rejectStudentId}&reason=${encodeURIComponent(reason)}`
                })
                .then(r => r.json())
                .then(data => {
                    confirmBtn.disabled = false;
                    confirmBtn.textContent = 'Send & Decline';
                    document.getElementById('rejectModal').classList.remove('open');
                    if (data.success) {
                        showToast('Request declined. Student has been notified.', 'success');
                        removeCard(_rejectMeetingId);
                        decrementPending();
                    } else {
                        showToast(data.message || 'Something went wrong.', 'error');
                    }
                });
        }

        /* ── Remove card from DOM ── */
        function removeCard(meetingId) {
            const card = document.querySelector(`.request-card[data-meeting="${meetingId}"]`);
            if (!card) return;
            card.classList.add('removing');
            setTimeout(() => {
                card.remove();
                checkEmpty();
            }, 360);
        }

        function decrementPending() {
            const el = document.getElementById('pendingCount');
            if (el) {
                const n = Math.max(0, parseInt(el.textContent) - 1);
                el.textContent = n;
            }
        }

        function checkEmpty() {
            if (document.querySelectorAll('.request-card').length === 0) {
                const feed = document.querySelector('.requests-feed');
                // Remove section header
                feed.querySelector('.page-header-row')?.remove();
                feed.insertAdjacentHTML('beforeend', `
                    <div class="empty-state">
                        <div class="empty-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        </div>
                        <h3>No pending requests</h3>
                        <p>You're all caught up! New session requests will appear here.</p>
                    </div>
                `);
            }
        }

        /* ── Toast ── */
        let _toastTimer = null;

        function showToast(msg, type = 'success') {
            const toast = document.getElementById('toast');
            const icon = document.getElementById('toastIcon');
            document.getElementById('toastMsg').textContent = msg;
            toast.className = `toast ${type}`;
            icon.innerHTML = type === 'success' ?
                '<polyline points="20 6 9 17 4 12"/>' :
                '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>';
            setTimeout(() => toast.classList.add('show'), 10);
            clearTimeout(_toastTimer);
            _toastTimer = setTimeout(() => toast.classList.remove('show'), 3500);
        }
    </script>

    <style>
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</body>

</html>