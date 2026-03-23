<?php
session_start();
include_once __DIR__ . '/validation.php';
require_once __DIR__ . '/../includes/scripts/connection.php';

// ── CORS for AJAX calls from dev tunnel ──────────────────────────────────────
if (isset($_POST['action'])) {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: POST");
    header("Access-Control-Allow-Headers: Content-Type");
}

// ── AJAX: mark meeting completed ──────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'end_meeting') {
    header('Content-Type: application/json');
    $meeting_id = $_POST['meeting_id'] ?? '';
    if ($meeting_id) {
        $s = $conn->prepare("UPDATE `meetings` SET `status`='completed' WHERE `meeting_id`=?");
        $s->bind_param("s", $meeting_id);
        $s->execute(); $s->close();
    }
    echo json_encode(['ok' => true]); exit;
}

// ── AJAX: save feedback & update teacher rating ───────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'save_feedback') {
    header('Content-Type: application/json');
    $teacher_id = intval($_POST['teacher_id'] ?? 0);
    $rating     = intval($_POST['rating']     ?? 0);
    $comments   = trim($_POST['comments']     ?? '');
    $meeting_id = $_POST['meeting_id']        ?? '';

    if ($teacher_id && $rating >= 1 && $rating <= 5) {
        // Insert feedback (id = teacher_id)
        $s = $conn->prepare("INSERT INTO `feedback` (`id`, `rating`, `comments`) VALUES (?, ?, ?)");
        $s->bind_param("iss", $teacher_id, $rating, $comments);
        $s->execute(); $s->close();

        // Recalculate teacher rating: (current_rating + new_rating) / total_completed_meetings_as_teacher
        $s = $conn->prepare("SELECT COUNT(*) as cnt FROM `meetings` WHERE `teacher_id`=? AND `status`='completed'");
        $s->bind_param("i", $teacher_id); $s->execute();
        $total = $s->get_result()->fetch_assoc()['cnt']; $s->close();

        $s = $conn->prepare("SELECT `rating` FROM `user_master` WHERE `user_id`=?");
        $s->bind_param("i", $teacher_id); $s->execute();
        $cur_rating = $s->get_result()->fetch_assoc()['rating']; $s->close();

        $total = max($total, 1);
        $new_avg = ($cur_rating + $rating) / $total;
        $new_avg = round($new_avg, 2);

        $s = $conn->prepare("UPDATE `user_master` SET `rating`=? WHERE `user_id`=?");
        $s->bind_param("di", $new_avg, $teacher_id);
        $s->execute(); $s->close();
    }
    echo json_encode(['ok' => true]); exit;
}

if (empty($_SESSION['isloggedin']) || $_SESSION['isloggedin'] !== true || empty($_SESSION['login_token'])) {
    session_unset(); session_destroy();
    header('Location: ../Auth/sign_in.php'); exit;
}

$stmt = $conn->prepare("SELECT * FROM `user_master` WHERE `user_id` = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$res = $stmt->get_result();
$user_detials = $res->fetch_assoc();
$name = explode(" ", $user_detials['full_name']);
$display_name = htmlspecialchars($user_detials['full_name']);

$stmt = $conn->prepare("SELECT m.meeting_id, m.teacher_id, sm.sub_name, m.`status`, m.`meeting_time` FROM `meetings` m
JOIN `subject_master` sm ON sm.sub_id = m.sub_id
WHERE `student_id` = ? AND (`status`='upcoming' OR `status`='started') AND `approved`='1'");
$stmt->bind_param("i", $_SESSION['user_id']); $stmt->execute();
$res_stud = $stmt->get_result(); $stmt->close();

$stmt_teacher = $conn->prepare("SELECT m.meeting_id, m.`status`, sm.sub_name, m.`meeting_time` FROM `meetings` m
JOIN `subject_master` sm ON sm.sub_id = m.sub_id
WHERE `teacher_id` = ? AND (`status`='upcoming' OR `status`='started') AND `approved`='1'");
$stmt_teacher->bind_param("i", $_SESSION['user_id']); $stmt_teacher->execute();
$res_teacher = $stmt_teacher->get_result(); $stmt_teacher->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Video Call Platform</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php include_once __DIR__ . '/../includes/scripts/common.php'; ?>
<link rel="stylesheet" href="../assets/styles/user/common.css">
<script src="../assets/js/tailwind.js.php" defer></script>
<style>
  :root {
    --blue-grad: linear-gradient(135deg,#1e3a8a 0%,#1d4ed8 55%,#3730a3 100%);
    --blue-grad-hover: linear-gradient(135deg,#1d4ed8 0%,#3730a3 55%,#1e3a8a 100%);
  }
  .gradient-btn { background:var(--blue-grad); transition:background .3s,transform .1s,box-shadow .2s; }
  .gradient-btn:hover { background:var(--blue-grad-hover); transform:translateY(-1px); box-shadow:0 4px 18px rgba(29,78,216,.4); }

  /* ---- Control bar ---- */
  #controlBar {
    display:flex; gap:.75rem; align-items:center; flex-wrap:wrap; justify-content:center;
    background:rgba(15,23,42,.85); backdrop-filter:blur(12px);
    border-radius:9999px; padding:.6rem 1.2rem;
    border:1px solid rgba(255,255,255,.08); box-shadow:0 8px 32px rgba(0,0,0,.4);
  }
  .ctrl-btn {
    display:flex; align-items:center; justify-content:center;
    width:48px; height:48px; border-radius:50%;
    background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.15);
    color:white; cursor:pointer; position:relative;
    transition:background .2s,transform .1s,box-shadow .2s;
  }
  .ctrl-btn:hover { background:rgba(255,255,255,.2); transform:scale(1.08); box-shadow:0 0 12px rgba(29,78,216,.4); }
  .ctrl-btn.btn-off  { background:rgba(239,68,68,.25); border-color:rgba(239,68,68,.5); color:#f87171; }
  .ctrl-btn.btn-active { background:rgba(29,78,216,.35); border-color:rgba(99,102,241,.6); color:#818cf8; }
  .ctrl-btn.btn-danger { background:rgba(220,38,38,.8); border-color:rgba(239,68,68,.7); }
  .ctrl-btn.btn-danger:hover { background:rgba(220,38,38,1); box-shadow:0 0 16px rgba(220,38,38,.5); }

  /* Unread badge on chat button */
  #chatUnread {
    position:absolute; top:-4px; right:-4px;
    background:#ef4444; color:white;
    font-size:.55rem; font-weight:700;
    width:16px; height:16px; border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    border:2px solid rgba(15,23,42,.9);
  }

  /* ---- Video layout ---- */
  #callLayout {
    display:flex; gap:1rem; width:100%; max-width:1300px; align-items:flex-start;
  }
  #callMain { flex:1; display:flex; flex-direction:column; gap:1rem; min-width:0; }

  #mainVideoArea {
    display:grid; grid-template-columns:1fr 1fr; gap:1rem; width:100%;
  }
  .video-wrapper {
    position:relative; background:#0f172a; border-radius:1rem; overflow:hidden;
    border:1px solid rgba(255,255,255,.08); box-shadow:0 4px 24px rgba(0,0,0,.5);
    aspect-ratio:16/9;
  }
  .video-wrapper video { width:100%; height:100%; object-fit:cover; }
  .video-label {
    position:absolute; bottom:.6rem; left:.8rem;
    background:rgba(0,0,0,.55); color:white;
    font-size:.75rem; padding:.2rem .6rem; border-radius:999px;
    backdrop-filter:blur(4px);
  }

  /* Screen share */
  #screenShareContainer {
    width:100%; background:#0f172a; border-radius:1rem; overflow:hidden;
    border:2px solid rgba(99,102,241,.4); box-shadow:0 0 30px rgba(99,102,241,.15); position:relative;
  }
  #screenVideo { width:100%; max-height:480px; object-fit:contain; background:#000; display:block; }
  .screen-label {
    position:absolute; top:.7rem; left:.9rem;
    background:rgba(99,102,241,.8); color:white;
    font-size:.72rem; padding:.2rem .65rem; border-radius:999px; letter-spacing:.05em;
  }

  /* Whiteboard */
  #whiteboardContainer {
    width:100%; border-radius:1rem; overflow:hidden;
    border:2px solid rgba(29,78,216,.4); box-shadow:0 0 30px rgba(29,78,216,.1); background:white;
  }
  #whiteboardToolbar {
    display:flex; align-items:center; gap:.6rem; padding:.5rem 1rem;
    background:#1e293b; flex-wrap:wrap;
  }
  .color-swatch {
    width:26px; height:26px; border-radius:50%; cursor:pointer;
    border:2px solid transparent; transition:transform .15s;
  }
  .color-swatch:hover { transform:scale(1.2); }
  #whiteboardCanvas { cursor:crosshair; display:block; width:100%; height:480px; background:white; touch-action:none; }

  /* ---- Chat Panel ---- */
  #chatPanel {
    width:300px; flex-shrink:0;
    background:rgba(15,23,42,.9); backdrop-filter:blur(16px);
    border-radius:1rem; border:1px solid rgba(255,255,255,.08);
    box-shadow:0 8px 32px rgba(0,0,0,.5);
    display:flex; flex-direction:column; overflow:hidden;
    height:600px; /* fixed height so it doesn't grow */
  }
  #chatHeader {
    display:flex; align-items:center; justify-content:space-between;
    padding:.6rem .9rem; border-bottom:1px solid rgba(255,255,255,.07);
    background:rgba(29,78,216,.15);
  }
  #chatHeader span { font-size:.82rem; font-weight:600; color:#e2e8f0; }
  #chatCloseBtn {
    width:24px; height:24px; border-radius:50%; background:rgba(255,255,255,.07);
    border:none; color:#94a3b8; cursor:pointer; display:flex; align-items:center; justify-content:center;
    transition:background .15s;
  }
  #chatCloseBtn:hover { background:rgba(255,255,255,.15); }
  #chatMessages {
    flex:1; overflow-y:auto; padding:.75rem .75rem 0 .75rem;
    scrollbar-width:thin; scrollbar-color:rgba(255,255,255,.1) transparent;
  }
  #chatMessages::-webkit-scrollbar { width:4px; }
  #chatMessages::-webkit-scrollbar-thumb { background:rgba(255,255,255,.1); border-radius:4px; }
  #chatInputArea {
    display:flex; gap:.4rem; padding:.6rem .75rem;
    border-top:1px solid rgba(255,255,255,.07);
    background:rgba(0,0,0,.2);
  }
  #chatInput {
    flex:1; background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1);
    border-radius:.6rem; padding:.4rem .7rem; color:#f1f5f9; font-size:.8rem; outline:none;
    transition:border-color .15s;
    resize:none; height:36px; line-height:1.4;
  }
  #chatInput:focus { border-color:rgba(99,102,241,.5); }
  #chatInput::placeholder { color:#475569; }
  #chatSendBtn {
    width:36px; height:36px; border-radius:.6rem; flex-shrink:0;
    background:rgba(29,78,216,.7); border:1px solid rgba(99,102,241,.4);
    color:white; cursor:pointer; display:flex; align-items:center; justify-content:center;
    transition:background .15s, transform .1s;
  }
  #chatSendBtn:hover { background:rgba(29,78,216,1); transform:scale(1.05); }

  /* ---- Meeting Cards ---- */
  .meeting-card {
    background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.1);
    border-radius:.85rem; transition:box-shadow .2s,transform .2s;
  }
  .meeting-card:hover { box-shadow:0 6px 28px rgba(29,78,216,.2); transform:translateY(-2px); }
  .badge {
    display:inline-block; padding:.15rem .7rem; border-radius:999px;
    font-size:.7rem; font-weight:600; letter-spacing:.05em; text-transform:uppercase;
  }
  .badge-upcoming { background:rgba(234,179,8,.15); color:#fbbf24; border:1px solid rgba(234,179,8,.3); }
  .badge-started  { background:rgba(34,197,94,.15);  color:#4ade80;  border:1px solid rgba(34,197,94,.3); }

  @media (max-width:900px) {
    #callLayout { flex-direction:column; }
    #chatPanel { width:100%; height:320px; }
  }
  @media (max-width:640px) {
    #mainVideoArea { grid-template-columns:1fr; }
  }

  /* ---- Feedback Modal ---- */
  #feedbackOverlay {
    position:fixed; inset:0; z-index:9999;
    background:rgba(0,0,0,.75); backdrop-filter:blur(6px);
    display:flex; align-items:center; justify-content:center;
  }
  #feedbackOverlay.hidden { display:none; }
  #feedbackBox {
    background:linear-gradient(145deg,#0f172a,#1e293b);
    border:1px solid rgba(99,102,241,.3);
    border-radius:1.25rem; padding:2rem 2.25rem;
    width:100%; max-width:420px;
    box-shadow:0 24px 64px rgba(0,0,0,.6);
    animation:fbIn .25s ease;
  }
  @keyframes fbIn { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:none} }
  .star-row { display:flex; gap:.45rem; margin:.5rem 0 1.25rem; }
  .star-btn {
    background:none; border:none; cursor:pointer;
    font-size:2rem; color:#334155; transition:color .15s, transform .1s;
    padding:0; line-height:1;
  }
  .star-btn:hover, .star-btn.active { color:#f59e0b; transform:scale(1.15); }
  #fbComments {
    width:100%; background:rgba(255,255,255,.05);
    border:1px solid rgba(255,255,255,.12); border-radius:.65rem;
    color:#f1f5f9; font-size:.85rem; padding:.6rem .85rem;
    resize:none; outline:none; transition:border-color .15s;
    box-sizing:border-box;
  }
  #fbComments:focus { border-color:rgba(99,102,241,.55); }
  #fbComments::placeholder { color:#475569; }
  #fbSubmitBtn {
    width:100%; margin-top:1rem;
    background:linear-gradient(135deg,#1e3a8a,#1d4ed8);
    color:white; border:none; border-radius:.75rem;
    padding:.7rem; font-size:.9rem; font-weight:600;
    cursor:pointer; transition:opacity .2s, transform .1s;
  }
  #fbSubmitBtn:hover { opacity:.9; transform:translateY(-1px); }
  #fbSkipBtn {
    width:100%; margin-top:.5rem;
    background:none; border:none; color:#475569;
    font-size:.78rem; cursor:pointer; padding:.3rem;
    transition:color .15s;
  }
  #fbSkipBtn:hover { color:#94a3b8; }
</style>
</head>
<body class="font-sans bg-page-light text-ink-light dark:bg-page-dark dark:text-ink-dark transition-colors duration-300 relative">

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
                        <h1 class="font-display font-bold text-xl sm:text-2xl text-royal-basic dark:text-royal-soft">Meeting</h1>
                        <p class="text-xs sm:text-sm text-muted-light dark:text-muted-dark hidden sm:block">Start Your learning journey!</p>
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
                                <a href="profile.php" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-royal-basic/5 dark:hover:bg-royal-soft/10 transition-colors">
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

  <!-- ===== MEETINGS LIST ===== -->
  <div id="meetingsList">
    <?php if ($res_stud->num_rows === 0 && $res_teacher->num_rows === 0): ?>
      <div class="text-center py-16 opacity-50">
        <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto mb-3 opacity-40" width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <p>No upcoming meetings</p>
      </div>
    <?php endif; ?>

    <?php while ($meeting = $res_stud->fetch_assoc()): ?>
      <div class="meeting-card p-5 mb-4 flex items-center justify-between gap-4">
        <div>
          <h3 class="text-lg font-semibold mb-1"><?= htmlspecialchars($meeting['sub_name']) ?></h3>
          <div class="flex items-center gap-2 text-sm opacity-70">
            <span class="meeting-status-badge badge badge-<?= $meeting['status'] ?>" data-meeting="<?= $meeting['meeting_id'] ?>" data-status="<?= $meeting['status'] ?>"><?= $meeting['status'] ?></span>
            <span><?= htmlspecialchars($meeting['meeting_time']) ?></span>
          </div>
        </div>
        <button class="gradient-btn meeting-join-btn text-white px-5 py-2.5 rounded-xl text-sm font-semibold flex items-center gap-2" data-meeting="<?= $meeting['meeting_id'] ?>"
                onclick="joinRoom('<?= $meeting['meeting_id'] ?>', false, '<?= addslashes($display_name) ?>', <?= intval($meeting['teacher_id']) ?>)">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
          Join
        </button>
      </div>
    <?php endwhile; ?>

    <?php while ($meeting = $res_teacher->fetch_assoc()): ?>
      <div class="meeting-card p-5 mb-4 flex items-center justify-between gap-4">
        <div>
          <h3 class="text-lg font-semibold mb-1"><?= htmlspecialchars($meeting['sub_name']) ?></h3>
          <div class="flex items-center gap-2 text-sm opacity-70">
            <span class="meeting-status-badge badge badge-<?= $meeting['status'] ?>" data-meeting="<?= $meeting['meeting_id'] ?>" data-status="<?= $meeting['status'] ?>"><?= $meeting['status'] ?></span>
            <span><?= htmlspecialchars($meeting['meeting_time']) ?></span>
          </div>
          <span class="text-xs text-indigo-400 font-medium mt-1 inline-block">👨‍🏫 Teacher</span>
        </div>
        <button class="gradient-btn meeting-join-btn text-white px-5 py-2.5 rounded-xl text-sm font-semibold flex items-center gap-2" data-meeting="<?= $meeting['meeting_id'] ?>"
                onclick="joinRoom('<?= $meeting['meeting_id'] ?>', true, '<?= addslashes($display_name) ?>')">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
          <?= $meeting['status'] === 'upcoming' ? 'Start' : 'Join' ?>
        </button>
      </div>
    <?php endwhile; ?>
  </div>

  <!-- ===== VIDEO CALL CONTAINER ===== -->
  <div id="videoCallContainer" class="hidden flex flex-col items-center gap-4">

    <!-- Call layout: videos + chat side by side -->
    <div id="callLayout">

      <!-- Left: video + controls + whiteboard -->
      <div id="callMain">

        <!-- Main videos -->
        <div id="mainVideoArea">
          <div class="video-wrapper">
            <video id="localVideo" autoplay muted playsinline></video>
            <div class="video-label">You</div>
          </div>
          <div class="video-wrapper">
            <video id="remoteVideo" autoplay playsinline></video>
            <div class="video-label">Remote</div>
          </div>
        </div>

        <!-- Screen share panel -->
        <div id="screenShareContainer" class="hidden">
          <div class="screen-label">🖥 Screen Share</div>
          <video id="screenVideo" autoplay playsinline></video>
        </div>

        <!-- Control Bar -->
        <div class="flex justify-center">
          <div id="controlBar">

            <!-- Mic -->
            <button id="micBtn" class="ctrl-btn" onclick="toggleMic()" title="Toggle Microphone">
              <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <rect x="9" y="2" width="6" height="12" rx="3"/>
                <path d="M5 10a7 7 0 0014 0"/><line x1="12" y1="19" x2="12" y2="22"/><line x1="8" y1="22" x2="16" y2="22"/>
              </svg>
            </button>

            <!-- Camera -->
            <button id="camBtn" class="ctrl-btn" onclick="toggleVideo()" title="Toggle Camera">
              <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/>
              </svg>
            </button>

            <!-- Chat (everyone) -->
            <button id="chatBtn" class="ctrl-btn" onclick="toggleChat()" title="Toggle Chat">
              <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
              </svg>
              <span id="chatUnread" class="hidden">0</span>
            </button>

            <!-- Screen Share (teacher only) -->
            <button id="shareScreenBtn" class="ctrl-btn teacher-only" onclick="shareScreen()" title="Share Screen" style="display:none;">
              <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>
              </svg>
            </button>

            <!-- Whiteboard (teacher only) -->
            <button id="whiteboardBtn" class="ctrl-btn teacher-only" onclick="toggleWhiteboard()" title="Toggle Whiteboard" style="display:none;">
              <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/>
              </svg>
            </button>

            <div style="width:1px;height:32px;background:rgba(255,255,255,.15);margin:0 4px;"></div>

            <!-- End Call -->
            <button class="ctrl-btn btn-danger" onclick="window.endCall()" title="End Call">
              <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path d="M10.68 13.31a16 16 0 003.01 3.01l1.27-1.27a2 2 0 012.18-.43 16 16 0 004.68 1.49 2 2 0 012 2v3a2 2 0 01-2.18 2A19.79 19.79 0 013 4.18 2 2 0 015 2h3a2 2 0 012 1.72 16 16 0 001.49 4.68 2 2 0 01-.43 2.18z"/>
                <line x1="23" y1="1" x2="1" y2="23"/>
              </svg>
            </button>
          </div>
        </div>

        <!-- Whiteboard -->
        <div id="whiteboardContainer" class="hidden">
          <div id="whiteboardToolbar">
            <span style="color:#94a3b8;font-size:.72rem;font-family:monospace;">Color:</span>
            <?php
            $colors = ['#1d4ed8','#dc2626','#16a34a','#d97706','#7c3aed','#0f172a','#ffffff'];
            foreach($colors as $i=>$c): ?>
              <div class="color-swatch <?= $i===0?'ring-2 ring-white':'' ?>"
                   style="background:<?=$c?>;"
                   onclick="setBrushColor('<?=$c?>', this)"></div>
            <?php endforeach; ?>
            <span style="color:#94a3b8;font-size:.72rem;font-family:monospace;margin-left:.5rem;">Size:</span>
            <input type="range" min="2" max="20" value="6" style="width:70px;accent-color:#1d4ed8;" oninput="setBrushSize(this.value)">
            <span id="brushSizeLabel" style="color:#94a3b8;font-size:.72rem;font-family:monospace;min-width:28px;">6px</span>
            <button onclick="clearWhiteboard()" style="margin-left:auto;background:rgba(239,68,68,.2);border:1px solid rgba(239,68,68,.4);color:#f87171;padding:.2rem .8rem;border-radius:999px;font-size:.72rem;cursor:pointer;display:flex;align-items:center;gap:4px;">
              <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
              Clear
            </button>
          </div>
          <canvas id="whiteboardCanvas"></canvas>
        </div>

      </div><!-- /callMain -->

      <!-- Right: Chat Panel (hidden until toggled) -->
      <div id="chatPanel" class="hidden">
        <div id="chatHeader">
          <span>
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display:inline;vertical-align:middle;margin-right:5px;">
              <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
            </svg>
            In-Call Chat
          </span>
          <button id="chatCloseBtn" type="button" onclick="toggleChat()" title="Close chat">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
          </button>
        </div>

        <div id="chatMessages"></div>

        <div id="chatInputArea">
          <textarea id="chatInput" placeholder="Type a message…" onkeydown="chatKeydown(event)" rows="1"></textarea>
          <button id="chatSendBtn" onclick="sendChatMessage()" title="Send">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
            </svg>
          </button>
        </div>
      </div><!-- /chatPanel -->

    </div><!-- /callLayout -->

  </div><!-- /videoCallContainer -->

  <!-- ===== FEEDBACK MODAL (student only, shown after call ends) ===== -->
  <div id="feedbackOverlay" class="hidden">
    <div id="feedbackBox">
      <h2 style="color:#e2e8f0;font-size:1.15rem;font-weight:700;margin:0 0 .25rem;">Rate Your Session ⭐</h2>
      <p style="color:#64748b;font-size:.8rem;margin:0 0 1rem;">How was your experience with the teacher?</p>

      <p style="color:#94a3b8;font-size:.78rem;margin-bottom:.25rem;">Your rating</p>
      <div class="star-row" id="starRow">
        <button class="star-btn" data-val="1" onclick="setRating(1)">★</button>
        <button class="star-btn" data-val="2" onclick="setRating(2)">★</button>
        <button class="star-btn" data-val="3" onclick="setRating(3)">★</button>
        <button class="star-btn" data-val="4" onclick="setRating(4)">★</button>
        <button class="star-btn" data-val="5" onclick="setRating(5)">★</button>
      </div>

      <p style="color:#94a3b8;font-size:.78rem;margin-bottom:.35rem;">Comments <span style="color:#334155;">(optional)</span></p>
      <textarea id="fbComments" rows="3" placeholder="Share your thoughts…"></textarea>

      <button id="fbSubmitBtn" onclick="submitFeedback()">Submit Feedback</button>
      <button id="fbSkipBtn"   onclick="closeFeedbackModal()">Skip for now</button>
    </div>
  </div>

</main>

<script src="https://nfr7183k-3000.inc1.devtunnels.ms/socket.io/socket.io.js"></script>
<script>
  const ROOM_PHP_URL = '<?= "http://" . $_SERVER['HTTP_HOST'] . "/skill_swap/user/room.php" ?>';
</script>
<script src="../videoCall/public/client.js"></script>
<script src="../assets/js/darkmodeToggle.js.php" defer></script>
<script src="../assets/js/dashboard.js.php" defer></script>
<!-- Feedback modal logic is handled by client.js -->
</body>
</html>