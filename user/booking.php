<?php
/**
 * user/booking.php
 * POST endpoint — creates a meeting (pending approval), inserts notifications.
 * Credits are NOT deducted here; they are deducted only when the teacher approves.
 * Receives JSON: { teacher_id, sub_id, meeting_time, topic? }
 */

ob_start();

session_start();
require_once __DIR__ . '/../includes/scripts/connection.php';

ob_clean();

header('Content-Type: application/json');

// ── DEBUG ─────────────────────────────────────────────────────────────────────
if (isset($_GET['debug'])) {
    $raw = file_get_contents('php://input');
    echo json_encode([
        'file'         => __FILE__,
        'method'       => $_SERVER['REQUEST_METHOD'],
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
        'php_input'    => $raw,
        'php_input_len'=> strlen($raw),
        'session'      => [
            'user_id'    => $_SESSION['user_id']    ?? 'missing',
            'isloggedin' => $_SESSION['isloggedin'] ?? 'missing',
        ],
    ], JSON_PRETTY_PRINT);
    exit;
}

// ── Auth ──────────────────────────────────────────────────────────────────────
if (
    empty($_SESSION['isloggedin']) ||
    $_SESSION['isloggedin'] !== true ||
    empty($_SESSION['login_token'])
) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// ── Read JSON body ────────────────────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

$teacher_id   = isset($body['teacher_id'])   ? (int) $body['teacher_id']   : 0;
$sub_id       = isset($body['sub_id'])       ? (int) $body['sub_id']       : 0;
$topic        = isset($body['topic'])        ? trim($body['topic'])        : '';
$meeting_time = isset($body['meeting_time']) ? trim($body['meeting_time']) : '';

// ── Validation ────────────────────────────────────────────────────────────────
$missing = [];
if (!$teacher_id)   $missing[] = 'teacher_id';
if (!$sub_id)       $missing[] = 'sub_id';
if (!$meeting_time) $missing[] = 'meeting_time';
if ($missing) {
    echo json_encode([
        'success'   => false,
        'error'     => 'Missing required fields: ' . implode(', ', $missing),
        'debug_raw' => $raw,
    ]);
    exit;
}

$student_id = (int) $_SESSION['user_id'];

if ($teacher_id === $student_id) {
    echo json_encode(['success' => false, 'error' => 'You cannot book a session with yourself.']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $meeting_time)) {
    echo json_encode(['success' => false, 'error' => 'Invalid date/time format. Got: ' . $meeting_time]);
    exit;
}

if (strtotime($meeting_time) <= time()) {
    echo json_encode(['success' => false, 'error' => 'Meeting time must be in the future.']);
    exit;
}

// ── Fixed session cost ────────────────────────────────────────────────────────
define('SESSION_COST', 10);

// ── Fetch student — verify they have enough credits to proceed ────────────────
$stmt = $conn->prepare("SELECT credit, full_name FROM user_master WHERE user_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    echo json_encode(['success' => false, 'error' => 'Student not found.']);
    exit;
}

$student_credit = (int) $student['credit'];
$student_name   =       $student['full_name'];

// Guard: student must have enough credits — actual deduction happens on approval
if ($student_credit < SESSION_COST) {
    echo json_encode([
        'success' => false,
        'error'   => "Not enough credits. You have {$student_credit} but need " . SESSION_COST . ".",
    ]);
    exit;
}

// ── Fetch teacher ─────────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT full_name FROM user_master WHERE user_id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$teacher) {
    echo json_encode(['success' => false, 'error' => 'Teacher not found.']);
    exit;
}

$teacher_name = $teacher['full_name'];

// ── Verify teacher teaches this subject ───────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT 1 FROM user_roles WHERE user_id = ? AND sub_id = ? AND user_role = 'teacher'"
);
$stmt->bind_param("ii", $teacher_id, $sub_id);
$stmt->execute();
$isTeacher = (bool) $stmt->get_result()->fetch_row();
$stmt->close();

if (!$isTeacher) {
    echo json_encode(['success' => false, 'error' => 'This teacher does not teach the selected subject.']);
    exit;
}

// ── Subject name ──────────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT sub_name FROM subject_master WHERE sub_id = ?");
$stmt->bind_param("i", $sub_id);
$stmt->execute();
$subRow   = $stmt->get_result()->fetch_assoc();
$stmt->close();
$sub_name = $subRow ? $subRow['sub_name'] : 'Unknown Subject';

// ── Duplicate booking check ───────────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT meeting_id FROM meetings
     WHERE teacher_id = ? AND student_id = ? AND meeting_time = ? AND status != 'completed'"
);
$stmt->bind_param("iis", $teacher_id, $student_id, $meeting_time);
$stmt->execute();
$duplicate = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($duplicate) {
    echo json_encode(['success' => false, 'error' => 'You already have a session booked at this time with this mentor.']);
    exit;
}

// ── Generate meeting ID ───────────────────────────────────────────────────────
$meeting_id = 'MTG-' . strtoupper(bin2hex(random_bytes(6)));

// ── Atomic transaction ────────────────────────────────────────────────────────
// NOTE: Credits are NOT deducted here. They are deducted (and awarded to the
//       teacher) only when the teacher approves in review_request.php.
$conn->begin_transaction();
try {

    // 1. Insert meeting as pending (approved = '2' means awaiting teacher action)
    $stmt = $conn->prepare("
        INSERT INTO meetings (meeting_id, teacher_id, student_id, sub_id, topic, approved, status, meeting_time)
        VALUES (?, ?, ?, ?, ?, '2', 'upcoming', ?)
    ");
    $stmt->bind_param("siiiss", $meeting_id, $teacher_id, $student_id, $sub_id, $topic, $meeting_time);
    if (!$stmt->execute()) throw new Exception('Could not create meeting: ' . $stmt->error);
    $stmt->close();
    $_SESSION['meeting_id'] = $meeting_id;

    // 2. Notify student — let them know the request is pending teacher approval
    $fmt         = date('D, d M Y \a\t h:i A', strtotime($meeting_time));
    $msg_student = "Your session request with {$teacher_name} for {$sub_name} on {$fmt} is pending approval. Credits will be deducted once the teacher approves.";
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, msg, `time`, msg_read) VALUES (?, ?, CURRENT_TIMESTAMP, 'no')");
    $stmt->bind_param("is", $student_id, $msg_student);
    if (!$stmt->execute()) throw new Exception('Student notification failed.');
    $stmt->close();

    // 3. Notify teacher
    $msg_teacher = "New session request from {$student_name} for {$sub_name} on {$fmt}. Please review and approve.";
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, msg, `time`, msg_read) VALUES (?, ?, CURRENT_TIMESTAMP, 'no')");
    $stmt->bind_param("is", $teacher_id, $msg_teacher);
    if (!$stmt->execute()) throw new Exception('Teacher notification failed.');
    $stmt->close();

    $conn->commit();

    echo json_encode([
        'success'    => true,
        'meeting_id' => $meeting_id,
        'message'    => "Session request sent to {$teacher_name} for {$sub_name}! Your {" . SESSION_COST . "} credits will be deducted once approved.",
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}