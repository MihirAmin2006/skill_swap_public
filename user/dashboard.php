<?php
/**
 * user/dashboard.php — fixed & styled
 *
 * Schema facts confirmed from SKILL_SWAP.sql:
 *  - notifications: notification_id, user_id, msg, time, msg_read  (NO meeting_id col)
 *  - meetings:      meeting_id, teacher_id, student_id, sub_id, topic, approved ENUM('0','1','2'), status ENUM('completed','upcoming','started'), meeting_time
 *  - user_master:   user_id, user_name, password, email, full_name, phone, profile_pic, bio, credit, feedback, rating, join_date, last_login
 *  - subject_master: sub_id, sub_name, total_teachers, total_students
 *  - user_roles:    sub_id, user_id, user_role ENUM('teacher','student','admin')
 *  - NO reviews table
 *
 * Bugs fixed vs original:
 *  1. Duplicate #mobileOverlay div
 *  2. (!$total_msg['total_msg'] == 0) — always-true condition → changed to > 0
 *  3. Notification loop tried to look up $meetings[$msg['meeting_id']] but notifications has no meeting_id — removed
 *  4. $name[1][0] crashes if single-word name — guarded
 *  5. Quick Stats were all hardcoded fake numbers — replaced with real DB data
 *  6. Weekly chart was hardcoded fake percentages — replaced with real per-day counts
 *  7. Meetings table missing teacher name column — added JOIN + column
 *  8. Logout was a <button id="logoutButton"> with no href — added direct <a> to logout.php
 */

date_default_timezone_set('Asia/Kolkata');
session_start();
include_once __DIR__ . '/validation.php';
require_once __DIR__ . '/../includes/scripts/connection.php';

// ── Auth ──────────────────────────────────────────────────────────────────────
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

$user_id = (int) $_SESSION['user_id'];

// ── User details ──────────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM `user_master` WHERE `user_id` = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_details = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Bug fix #4: guard single-word names
$name_parts = explode(" ", trim($user_details['full_name'] ?? 'User'));
$first_name  = $name_parts[0];
$second_init = isset($name_parts[1]) ? strtoupper(substr($name_parts[1], 0, 1)) : strtoupper(substr($name_parts[0], 1, 1));
$initials    = strtoupper(substr($name_parts[0], 0, 1)) . $second_init;

// ── Aggregate counts ──────────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT
        COUNT(*)                                                                          AS count_lectures,
        SUM(status = 'upcoming'  AND student_id = ? AND DATE(meeting_time) = CURDATE())  AS count_upcoming,
        SUM(status = 'completed' AND student_id = ?)                                     AS count_completed,
        SUM(approved = '0'       AND student_id = ?)                                     AS count_pending
    FROM meetings
    WHERE student_id = ?
");
$stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$total_counts = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── Upcoming meetings ─────────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT sm.sub_name,
           m.approved,
           m.status,
           m.meeting_time,
           u.full_name AS teacher_name
    FROM meetings m
    JOIN subject_master sm ON sm.sub_id  = m.sub_id
    JOIN user_master    u  ON u.user_id  = m.teacher_id
    WHERE m.student_id = ?
      AND (m.status = 'upcoming' OR m.status = 'started')
    ORDER BY m.meeting_time ASC
    LIMIT 10
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$upcoming_meetings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Unread notification count ─────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT COUNT(*) AS total_msg FROM `notifications` WHERE `user_id` = ? AND `msg_read` = 'no'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_msg = (int) $stmt->get_result()->fetch_assoc()['total_msg'];
$stmt->close();

// ── Notifications list (schema has NO meeting_id — bug fix #3) ────────────────
$stmt = $conn->prepare("
    SELECT msg, time
    FROM notifications
    WHERE user_id = ?
    ORDER BY time DESC
    LIMIT 20
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── My Subjects: roles + session count per subject ────────────────────────────
$stmt = $conn->prepare("
    SELECT sm.sub_id,
           sm.sub_name,
           ur.user_role,
           (SELECT COUNT(*) FROM meetings m
            WHERE m.sub_id = sm.sub_id
              AND (m.student_id = ? OR m.teacher_id = ?)) AS session_count
    FROM user_roles ur
    JOIN subject_master sm ON sm.sub_id = ur.sub_id
    WHERE ur.user_id = ?
    ORDER BY session_count DESC
");
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$my_subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Weekly activity ───────────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT DATE(meeting_time) AS day_date, COUNT(*) AS session_count
    FROM meetings
    WHERE (student_id = ? OR teacher_id = ?)
      AND meeting_time >= CURDATE() - INTERVAL 6 DAY
    GROUP BY DATE(meeting_time)
    ORDER BY day_date ASC
");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$weekly_raw = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$day_map = [];
foreach ($weekly_raw as $row) {
    $day_map[$row['day_date']] = (int) $row['session_count'];
}
$max_sessions = max(array_merge([1], array_values($day_map)));

$week_days = [];
for ($i = 6; $i >= 0; $i--) {
    $date  = date('Y-m-d', strtotime("-$i days"));
    $short = date('D', strtotime($date));
    $count = $day_map[$date] ?? 0;
    $pct   = (int) round(($count / $max_sessions) * 100);
    $week_days[] = ['short' => $short, 'count' => $count, 'pct' => max($pct, 4)];
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function timeAgo(string $dt): string {
    $diff = (new DateTime())->diff(new DateTime($dt));
    if ($diff->d > 0) return $diff->d . "d ago";
    if ($diff->h > 0) return $diff->h . "h ago";
    if ($diff->i > 0) return $diff->i . "m ago";
    return "Just now";
}

function friendlyTime(string $dt): string {
    $m   = new DateTime($dt);
    $now = new DateTime();
    $tmr = new DateTime('tomorrow');
    if ($m->format('Y-m-d') === $now->format('Y-m-d')) return 'Today, '    . $m->format('g:i A');
    if ($m->format('Y-m-d') === $tmr->format('Y-m-d')) return 'Tomorrow, ' . $m->format('g:i A');
    return $m->format('d M Y, g:i A');
}
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard – SkillSwap</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/styles/user/common.css">
    <script src="../assets/js/tailwind.js.php" defer></script>
    <style>
        body { font-family: 'DM Sans', sans-serif; }
        h1,h2,h3 { font-family: 'Playfair Display', serif; }

        @keyframes barGrow { from { width: 0 } }
        .bar-fill { animation: barGrow .9s cubic-bezier(.4,0,.2,1) both; }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fade-card { opacity: 0; animation: fadeUp .5s ease forwards; }
        .fade-card:nth-child(1) { animation-delay: .05s; }
        .fade-card:nth-child(2) { animation-delay: .12s; }
        .fade-card:nth-child(3) { animation-delay: .19s; }
        .fade-card:nth-child(4) { animation-delay: .26s; }

        .stat-card { transition: transform .2s, box-shadow .2s; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 12px 28px rgba(30,58,138,.12); }

        @keyframes pdot { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.35);opacity:.7} }
        .pdot { animation: pdot 2s infinite; }
    </style>
</head>

<body class="bg-gray-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 overflow-x-hidden transition-colors duration-300">

    <!-- Single mobile overlay — bug fix #1 -->
    <div id="mobileOverlay" onclick="closeMobileSidebar()"
         class="fixed inset-0 bg-black/40 z-40 hidden lg:hidden"></div>

    <?php include_once __DIR__ . '/../animated-bg.php'; ?>
    <?php include_once __DIR__ . '/navbar.php'; ?>

    <main class="lg:ml-64 min-h-screen relative z-10">

        <!-- Top bar -->
        <header class="sticky top-0 z-40 bg-white/80 dark:bg-slate-900/80 backdrop-blur-xl border-b border-slate-200 dark:border-slate-700 transition-colors">
            <div class="flex items-center justify-between px-4 sm:px-6 lg:px-8 py-4">

                <div class="flex items-center gap-3">
                    <button onclick="openMobileSidebar()"
                            class="lg:hidden p-2 rounded-xl bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-800 dark:text-blue-300 transition-all">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="3" y1="6"  x2="21" y2="6"/>
                            <line x1="3" y1="12" x2="21" y2="12"/>
                            <line x1="3" y1="18" x2="21" y2="18"/>
                        </svg>
                    </button>
                    <div>
                        <h1 class="font-bold text-xl sm:text-2xl text-blue-900 dark:text-blue-200">Dashboard</h1>
                        <p class="text-xs text-slate-500 dark:text-slate-400 hidden sm:block">Welcome back, <?= htmlspecialchars($first_name) ?>! 👋</p>
                    </div>
                </div>

                <div class="flex items-center gap-2 sm:gap-3">

                    <!-- Bell — bug fix #2 -->
                    <div class="relative">
                        <button onclick="toggleDropdown('notifDropdown')"
                                class="relative p-2.5 rounded-xl bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-800 dark:text-blue-300 transition-all">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                            </svg>
                            <?php if ($total_msg > 0): ?>
                                <span class="pdot absolute -top-1 -right-1 min-w-[18px] h-[18px] bg-red-500 rounded-full text-white text-[10px] font-bold flex items-center justify-center px-0.5">
                                    <?= min($total_msg, 99) ?>
                                </span>
                            <?php endif; ?>
                        </button>

                        <div id="notifDropdown"
                             class="hidden absolute right-0 mt-2 w-80 max-w-[calc(100vw-1rem)] bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-700 overflow-hidden z-50">
                            <div class="p-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                                <span class="font-semibold text-sm">Notifications</span>
                                <?php if ($total_msg > 0): ?>
                                    <span class="text-xs font-semibold bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 px-2 py-0.5 rounded-full"><?= $total_msg ?> new</span>
                                <?php endif; ?>
                            </div>

                            <!-- Bug fix #3: notifications has no meeting_id col -->
                            <div class="max-h-80 overflow-y-auto divide-y divide-slate-50 dark:divide-slate-700/50">
                                <?php if (empty($notifications)): ?>
                                    <p class="p-6 text-center text-sm text-slate-400">No notifications yet</p>
                                <?php else: ?>
                                    <?php foreach ($notifications as $n): ?>
                                        <div class="p-4 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-colors">
                                            <div class="flex gap-3">
                                                <div class="pdot w-2 h-2 mt-1.5 rounded-full bg-blue-500 shrink-0"></div>
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm text-slate-700 dark:text-slate-200 leading-snug"><?= htmlspecialchars($n['msg']) ?></p>
                                                    <p class="text-xs text-slate-400 mt-1"><?= timeAgo($n['time']) ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <div class="p-3 border-t border-slate-100 dark:border-slate-700 text-center">
                                <a href="notifications.php" class="text-xs font-semibold text-blue-700 dark:text-blue-400 hover:underline">View all →</a>
                            </div>
                        </div>
                    </div>

                    <!-- Dark mode -->
                    <div id="toggleWrap"
                         class="relative cursor-pointer rounded-full bg-slate-300 dark:bg-blue-800 transition-colors duration-300"
                         style="width:52px;height:28px;"
                         onclick="toggleTheme()" role="button" aria-label="Toggle dark mode" tabindex="0">
                        <div id="toggleKnob"
                             class="absolute top-0.5 left-0.5 rounded-full bg-white shadow flex items-center justify-center transition-transform duration-300"
                             style="width:22px;height:22px;">
                            <svg id="iconSun" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="#1e3a8a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="5"/>
                                <line x1="12" y1="1"  x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/>
                                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                                <line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/>
                                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                            </svg>
                            <svg id="iconMoon" class="w-3.5 h-3.5 hidden" viewBox="0 0 24 24" fill="none" stroke="#93c5fd" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                            </svg>
                        </div>
                    </div>

                    <!-- Profile — bug fix #4 initials guard; bug fix #8 logout link -->
                    <div class="relative hidden sm:block">
                        <button onclick="toggleDropdown('profileDropdown')"
                                class="flex items-center gap-2 p-1.5 pr-3 rounded-xl hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center text-white font-bold text-sm"
                                 style="background:linear-gradient(135deg,#1e3a8a,#1d4ed8);">
                                <?= htmlspecialchars($initials) ?>
                            </div>
                            <svg class="w-4 h-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9"/>
                            </svg>
                        </button>

                        <div id="profileDropdown"
                             class="hidden absolute right-0 mt-2 w-56 bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-700 overflow-hidden z-50">
                            <div class="p-4 border-b border-slate-100 dark:border-slate-700">
                                <p class="font-semibold text-sm"><?= htmlspecialchars($user_details['full_name']) ?></p>
                                <p class="text-xs text-slate-400 mt-0.5">@<?= htmlspecialchars($user_details['user_name']) ?></p>
                                <p class="text-xs text-blue-600 dark:text-blue-400 font-semibold mt-1">💳 <?= (int)$user_details['credit'] ?> credits</p>
                            </div>
                            <div class="p-2">
                                <a href="profile.php" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 text-sm transition-colors">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                                    </svg>
                                    View Profile
                                </a>
                            </div>
                            <div class="p-2 border-t border-slate-100 dark:border-slate-700">
                                <a href="../Auth/logout.php"
                                   class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 text-sm text-red-600 dark:text-red-400 transition-colors">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                                        <polyline points="16 17 21 12 16 7"/>
                                        <line x1="21" y1="12" x2="9" y2="12"/>
                                    </svg>
                                    Logout
                                </a>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="p-4 sm:p-6 lg:p-8 space-y-6 sm:space-y-8">

            <!-- STAT CARDS -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">

                <div class="stat-card fade-card bg-white dark:bg-slate-800 rounded-2xl p-5 sm:p-6 border border-slate-200 dark:border-slate-700 shadow-sm">
                    <div class="flex items-start justify-between mb-4">
                        <div class="p-3 rounded-xl" style="background:linear-gradient(135deg,#3b82f6,#1e3a8a);">
                            <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12.9494914,6 C13.4853936,6.52514205 13.8531598,7.2212202 13.9645556,8 L17.5,8 C17.7761424,8 18,8.22385763 18,8.5 C18,8.77614237 17.7761424,9 17.5,9 L13.9645556,9 C13.7219407,10.6961471 12.263236,12 10.5,12 L7.70710678,12 L13.8535534,18.1464466 C14.0488155,18.3417088 14.0488155,18.6582912 13.8535534,18.8535534 C13.6582912,19.0488155 13.3417088,19.0488155 13.1464466,18.8535534 L6.14644661,11.8535534 C5.83146418,11.538571 6.05454757,11 6.5,11 L10.5,11 C11.709479,11 12.7183558,10.1411202 12.9499909,9 L6.5,9 C6.22385763,9 6,8.77614237 6,8.5 C6,8.22385763 6.22385763,8 6.5,8 L12.9499909,8 C12.7183558,6.85887984 11.709479,6 10.5,6 L6.5,6 C6.22385763,6 6,5.77614237 6,5.5 C6,5.22385763 6.22385763,5 6.5,5 L10.5,5 L17.5,5 C17.7761424,5 18,5.22385763 18,5.5 C18,5.77614237 17.7761424,6 17.5,6 L12.9494914,6 Z"/>
                            </svg>
                        </div>
                        <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/20 px-2 py-1 rounded-lg">Balance</span>
                    </div>
                    <h3 class="text-2xl sm:text-3xl font-bold text-blue-900 dark:text-blue-200 mb-1"><?= (int)$user_details['credit'] ?></h3>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">Total Credits</p>
                </div>

                <div class="stat-card fade-card bg-white dark:bg-slate-800 rounded-2xl p-5 sm:p-6 border border-slate-200 dark:border-slate-700 shadow-sm">
                    <?php
                    $total_lec = (int)($total_counts['count_lectures'] ?? 0);
                    $done_lec  = (int)($total_counts['count_completed'] ?? 0);
                    $att_pct   = $total_lec > 0 ? round($done_lec / $total_lec * 100) : 0;
                    ?>
                    <div class="flex items-start justify-between mb-4">
                        <div class="p-3 rounded-xl" style="background:linear-gradient(135deg,#10b981,#059669);">
                            <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                            </svg>
                        </div>
                        <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/20 px-2 py-1 rounded-lg"><?= $att_pct ?>%</span>
                    </div>
                    <h3 class="text-2xl sm:text-3xl font-bold text-blue-900 dark:text-blue-200 mb-1">
                        <?= $done_lec ?><span class="text-base font-medium text-slate-400 ml-0.5">/<?= $total_lec ?></span>
                    </h3>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">Lectures Completed</p>
                </div>

                <div class="stat-card fade-card bg-white dark:bg-slate-800 rounded-2xl p-5 sm:p-6 border border-slate-200 dark:border-slate-700 shadow-sm">
                    <div class="flex items-start justify-between mb-4">
                        <div class="p-3 rounded-xl" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
                            <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                            </svg>
                        </div>
                        <span class="text-xs font-semibold text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 px-2 py-1 rounded-lg">Today</span>
                    </div>
                    <h3 class="text-2xl sm:text-3xl font-bold text-blue-900 dark:text-blue-200 mb-1"><?= (int)($total_counts['count_upcoming'] ?? 0) ?></h3>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">Upcoming Today</p>
                </div>

                <div class="stat-card fade-card bg-white dark:bg-slate-800 rounded-2xl p-5 sm:p-6 border border-slate-200 dark:border-slate-700 shadow-sm">
                    <div class="flex items-start justify-between mb-4">
                        <div class="p-3 rounded-xl" style="background:linear-gradient(135deg,#8b5cf6,#6d28d9);">
                            <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                            </svg>
                        </div>
                        <span class="text-xs font-semibold text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 px-2 py-1 rounded-lg">Awaiting</span>
                    </div>
                    <h3 class="text-2xl sm:text-3xl font-bold text-blue-900 dark:text-blue-200 mb-1"><?= (int)($total_counts['count_pending'] ?? 0) ?></h3>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">Pending Approvals</p>
                </div>

            </div>

            <!-- CHART + QUICK STATS -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">

                <!-- Weekly Activity — real DB data, bug fix #6 -->
                <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-2xl p-5 sm:p-6 border border-slate-200 dark:border-slate-700 shadow-sm">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-2">
                        <div>
                            <h3 class="font-bold text-base sm:text-lg text-blue-900 dark:text-blue-200">Weekly Activity</h3>
                            <p class="text-xs text-slate-400 mt-0.5">Sessions in the last 7 days</p>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-slate-400">
                            <span class="w-3 h-3 rounded-sm inline-block" style="background:linear-gradient(135deg,#3b82f6,#1e3a8a);"></span>
                            Sessions
                        </div>
                    </div>
                    <div class="space-y-3">
                        <?php foreach ($week_days as $day): ?>
                            <div class="flex items-center gap-3">
                                <span class="text-xs font-semibold text-slate-400 w-8 shrink-0"><?= $day['short'] ?></span>
                                <div class="flex-1 h-7 bg-slate-100 dark:bg-slate-700 rounded-lg overflow-hidden">
                                    <div class="bar-fill h-full rounded-lg"
                                         style="width:<?= $day['pct'] ?>%;background:linear-gradient(90deg,#3b82f6,#1e3a8a);"></div>
                                </div>
                                <span class="text-xs font-semibold text-blue-900 dark:text-blue-300 w-4 text-right shrink-0"><?= $day['count'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-[11px] text-slate-300 dark:text-slate-600 mt-3 text-right">* includes sessions as student or mentor</p>
                </div>

                <!-- My Subjects — from user_roles + subject_master + meetings -->
                <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 sm:p-6 border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-base sm:text-lg text-blue-900 dark:text-blue-200">My Subjects</h3>
                        <span class="text-xs text-slate-400"><?= count($my_subjects) ?> enrolled</span>
                    </div>

                    <?php if (empty($my_subjects)): ?>
                        <div class="flex-1 flex flex-col items-center justify-center py-6 text-center gap-2">
                            <svg class="w-10 h-10 text-slate-200 dark:text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                            </svg>
                            <p class="text-sm text-slate-400">No subjects yet</p>
                        </div>
                    <?php else: ?>
                        <?php
                        // colour palette cycles per subject
                        $palettes = [
                            ['bg' => '#eff6ff', 'text' => '#1d4ed8', 'dot' => '#3b82f6', 'dark_bg' => 'rgba(59,130,246,.15)'],
                            ['bg' => '#f0fdf4', 'text' => '#15803d', 'dot' => '#22c55e', 'dark_bg' => 'rgba(34,197,94,.15)'],
                            ['bg' => '#fefce8', 'text' => '#a16207', 'dot' => '#eab308', 'dark_bg' => 'rgba(234,179,8,.15)'],
                            ['bg' => '#fdf4ff', 'text' => '#7e22ce', 'dot' => '#a855f7', 'dark_bg' => 'rgba(168,85,247,.15)'],
                            ['bg' => '#fff7ed', 'text' => '#c2410c', 'dot' => '#f97316', 'dark_bg' => 'rgba(249,115,22,.15)'],
                        ];
                        $max_sessions_sub = max(array_column($my_subjects, 'session_count') ?: [1]);
                        $max_sessions_sub = max($max_sessions_sub, 1);
                        ?>
                        <div class="space-y-3 overflow-y-auto" style="max-height:260px;">
                            <?php foreach ($my_subjects as $idx => $sub):
                                $pal  = $palettes[$idx % count($palettes)];
                                $role = $sub['user_role'];
                                $pct  = (int) round(($sub['session_count'] / $max_sessions_sub) * 100);
                                $pct  = max($pct, 6);
                                $role_label = ucfirst($role);
                                $role_colors = $role === 'teacher'
                                    ? 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400'
                                    : 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400';
                            ?>
                                <div class="group rounded-xl p-3 transition-all hover:scale-[1.01]"
                                     style="background:<?= $pal['bg'] ?>;">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="w-2 h-2 rounded-full shrink-0" style="background:<?= $pal['dot'] ?>;"></span>
                                            <span class="text-sm font-semibold truncate" style="color:<?= $pal['text'] ?>;">
                                                <?= htmlspecialchars($sub['sub_name']) ?>
                                            </span>
                                        </div>
                                        <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full shrink-0 ml-2 <?= $role_colors ?>">
                                            <?= $role_label ?>
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 h-1.5 rounded-full" style="background:rgba(0,0,0,.08);">
                                            <div class="bar-fill h-full rounded-full" style="width:<?= $pct ?>%;background:<?= $pal['dot'] ?>;"></div>
                                        </div>
                                        <span class="text-[11px] font-semibold shrink-0" style="color:<?= $pal['text'] ?>;">
                                            <?= $sub['session_count'] ?> session<?= $sub['session_count'] != 1 ? 's' : '' ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- UPCOMING MEETINGS TABLE -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="p-5 sm:p-6 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                    <h3 class="font-bold text-base sm:text-lg text-blue-900 dark:text-blue-200">Upcoming Sessions</h3>
                    <a href="book_session.php" class="text-xs font-semibold text-blue-700 dark:text-blue-400 hover:underline">+ Book New</a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50 dark:bg-slate-700/40 text-xs font-semibold text-slate-400 uppercase tracking-wider">
                            <tr>
                                <th class="px-5 py-3">#</th>
                                <th class="px-5 py-3">Subject</th>
                                <th class="px-5 py-3">Mentor</th>
                                <th class="px-5 py-3">When</th>
                                <th class="px-5 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 dark:divide-slate-700">
                            <?php if (empty($upcoming_meetings)): ?>
                                <tr>
                                    <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-400">
                                        No upcoming sessions.
                                        <a href="book_session.php" class="text-blue-600 dark:text-blue-400 hover:underline ml-1">Book one now →</a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($upcoming_meetings as $i => $row):
                                    $approved = $row['approved'];
                                    if ($approved === '1') {
                                        $bc = 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400';
                                        $bt = 'Approved';
                                    } elseif ($approved === '0') {
                                        $bc = 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
                                        $bt = 'Rejected';
                                    } else {
                                        // approved='2' means auto/pending in this schema
                                        $bc = 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400';
                                        $bt = 'Pending';
                                    }
                                ?>
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                                        <td class="px-5 py-4 text-sm text-slate-400"><?= $i + 1 ?></td>
                                        <td class="px-5 py-4 text-sm font-semibold text-slate-800 dark:text-slate-100 whitespace-nowrap"><?= htmlspecialchars($row['sub_name']) ?></td>
                                        <td class="px-5 py-4 text-sm text-slate-500 dark:text-slate-400 whitespace-nowrap"><?= htmlspecialchars($row['teacher_name']) ?></td>
                                        <td class="px-5 py-4 text-sm text-slate-500 dark:text-slate-400 whitespace-nowrap"><?= friendlyTime($row['meeting_time']) ?></td>
                                        <td class="px-5 py-4">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold whitespace-nowrap <?= $bc ?>">
                                                <?= $bt ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <script src="../assets/js/dashboard.js.php" defer></script>
    <script src="../assets/js/darkmodeToggle.js.php" defer></script>
    <script>
    function toggleDropdown(id) {
        ['notifDropdown','profileDropdown'].forEach(d => {
            if (d !== id) document.getElementById(d)?.classList.add('hidden');
        });
        document.getElementById(id)?.classList.toggle('hidden');
    }
    document.addEventListener('click', function(e) {
        if (!e.target.closest('[onclick^="toggleDropdown"]') && !e.target.closest('[id$="Dropdown"]')) {
            document.querySelectorAll('[id$="Dropdown"]').forEach(d => d.classList.add('hidden'));
        }
    });
    </script>
</body>
</html>