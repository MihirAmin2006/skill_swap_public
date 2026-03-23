<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once __DIR__ . '/../includes/scripts/connection.php';
include_once __DIR__ . '/validation.php';

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

$stmt = $conn->prepare("SELECT * FROM `user_master` where `user_id` = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$res = $stmt->get_result();
$user_detials = $res->fetch_assoc();

$name = explode(" ", $user_detials['full_name']);
$stmt->close();

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT DISTINCT sm.sub_name, 
                        m.meeting_id, 
                        r.user_role, 
                        m.approved,
                        um_teacher.full_name AS teacher_name,
                        m.status, 
                        m.meeting_time 
                        FROM user_roles r 
                        JOIN user_master um ON um.user_id = r.user_id 
                        JOIN meetings m ON r.user_id = m.student_id
                        JOIN subject_master sm ON m.sub_id = sm.sub_id
                        JOIN user_master um_teacher ON m.teacher_id = um_teacher.user_id
                        WHERE m.student_id = ?
                    ");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$res_select = $stmt->get_result();

$stmt = $conn->prepare("SELECT COUNT(*) as `count_lectures`,
                        (SELECT COUNT(*) from meetings where status = 'upcoming' and `student_id` = ?) as `count_upcoming`,
                        (SELECT COUNT(*) from meetings where status = 'completed' and `student_id` = ?) as `count_completed` from meetings 
                        where student_id = ?;");
$stmt->bind_param("iii", $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$res1 = $stmt->get_result();
$total_counts = $res1->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en" class="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <?php include_once __DIR__ . '/../includes/scripts/common.php'; ?>

    <link rel="stylesheet" href="../assets/styles/user/common.css">
    <script src="../assets/js/tailwind.js.php" defer></script>
</head>

<body class="font-sans bg-page-light text-ink-light overflow-x-hidden dark:bg-page-dark dark:text-ink-dark transition-colors duration-300 relative">

    <div id="mobileOverlay" onclick="closeMobileSidebar()"></div>

    <?php include_once __DIR__ . '/../animated-bg.php'; ?>
    <?php include_once __DIR__ . '/navbar.php'; ?>

    <main class="lg:ml-64 min-h-screen relative z-10">

        <!-- HEADER -->
        <header class="sticky top-0 z-40 bg-white/80 dark:bg-card-dark/80 backdrop-blur-xl
            border-b border-royal-basic/10 dark:border-royal-violet/20">
            <div class="flex items-center justify-between px-4 sm:px-6 lg:px-8 py-4">

                <div class="flex items-center gap-3">
                    <button onclick="openMobileSidebar()" class="lg:hidden p-2 rounded-xl
                        bg-royal-basic/5 dark:bg-royal-soft/10
                        hover:bg-royal-basic/10 dark:hover:bg-royal-soft/15
                        text-royal-basic dark:text-royal-soft">
                        ☰
                    </button>

                    <div>
                        <h1 class="font-display font-bold text-xl sm:text-2xl text-royal-basic dark:text-royal-soft">
                            Lectures
                        </h1>
                        <p class="text-xs sm:text-sm text-muted-light dark:text-muted-dark hidden sm:block">
                            Browse & manage learning sessions
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 sm:gap-4">
                    <!-- Dark Mode Toggle (bottom) -->
                    <div id="toggleWrap"
                        class="relative cursor-pointer w-52px h-28px rounded-full
                bg-slate-300 dark:bg-royal-DEFAULT
                transition-colors duration-400"
                        style="width:52px;height:28px;"
                        onclick="toggleTheme()" role="button" aria-label="Toggle dark mode" tabindex="0">
                        <!-- knob -->
                        <div id="toggleKnob"
                            class="absolute top-0.5 left-0.5 w-22px h-22px rounded-full bg-white shadow-knob
                  flex items-center justify-center
                  transition-transform duration-300"
                            style="width:22px;height:22px;">
                            <!-- sun -->
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
                            <!-- moon -->
                            <svg id="iconMoon" class="w-3.5 h-3.5 hidden" viewBox="0 0 24 24" fill="none" stroke="#a5b4fc" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
                            </svg>
                        </div>
                    </div>

                    <!-- Profile -->
                    <div class="relative hidden sm:block">
                        <button onclick="toggleDropdown('profileDropdown')"
                            class="flex items-center gap-2 sm:gap-3 p-2 pr-3 rounded-xl
                         hover:bg-royal-basic/5 dark:hover:bg-royal-soft/10
                         transition-all duration-200">
                            <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-full flex items-center justify-center text-white font-semibold text-sm"
                                style="background:linear-gradient(135deg,#1e3a8a,#1d4ed8);">
                                <?= $name[0][0] . $name[1][0] ?>
                            </div>
                            <svg class="w-4 h-4 text-muted-light dark:text-muted-dark hidden sm:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </button>

                        <!-- Profile Dropdown -->
                        <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-64 bg-white dark:bg-card-dark rounded-2xl shadow-dropdown border border-royal-basic/10 dark:border-royal-violet/20 overflow-hidden animate-scale-in opacity-0">
                            <div class="p-4 border-b border-royal-basic/10 dark:border-royal-violet/20">
                                <p class="font-semibold text-sm"><?= $user_detials['full_name']; ?></p>
                                <p class="text-xs text-muted-light dark:text-muted-dark">@<?= $user_detials['user_name']; ?></p>
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

        <!-- CONTENT -->
        <div class="p-4 sm:p-6 lg:p-8 space-y-6">

            <!-- TOP STATS -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white/90 dark:bg-card-dark/90 rounded-2xl p-5 border border-royal-basic/10 shadow-card">
                    <p class="text-xs text-muted-light dark:text-muted-dark">Total Lectures</p>
                    <h3 class="text-2xl font-bold text-royal-basic dark:text-royal-soft"><?= htmlspecialchars($total_counts['count_lectures'] ?? 0) ?></h3>
                </div>

                <div class="bg-white/90 dark:bg-card-dark/90 rounded-2xl p-5 border border-royal-basic/10 shadow-card">
                    <p class="text-xs text-muted-light dark:text-muted-dark">Completed</p>
                    <h3 class="text-2xl font-bold text-green-600 dark:text-green-400"><?= htmlspecialchars($total_counts['count_completed'] ?? 0) ?></h3>
                </div>

                <div class="bg-white/90 dark:bg-card-dark/90 rounded-2xl p-5 border border-royal-basic/10 shadow-card">
                    <p class="text-xs text-muted-light dark:text-muted-dark">Upcoming</p>
                    <h3 class="text-2xl font-bold text-blue-600 dark:text-blue-400"><?= htmlspecialchars($total_counts['count_upcoming'] ?? 0) ?></h3>
                </div>
            </div>
            <div class="bg-white/90 dark:bg-card-dark/90 backdrop-blur-sm rounded-2xl border border-royal-basic/10 dark:border-royal-violet/20 shadow-card overflow-hidden opacity-0 animate-fade-in-d4">

                <div class="p-5 sm:p-6 border-b border-royal-basic/10 dark:border-royal-violet/20 bg-gray-50/30 dark:bg-white/5">
                    <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4">
                        <div>
                            <h3 class="font-display font-bold text-base sm:text-lg text-royal-basic dark:text-royal-soft">
                                Lecture Library
                            </h3>
                            <p class="text-xs text-muted-light dark:text-muted-dark">Filter by date, teacher, or subject</p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <div class="relative min-w-[180px]">
                                <input type="text" placeholder="Search topics..." class="w-full bg-white dark:bg-slate-800 border border-royal-basic/15 dark:border-royal-violet/20 rounded-lg pl-9 pr-3 py-2 text-xs outline-none focus:ring-2 focus:ring-royal-mid/50 transition-all text-ink-light dark:text-ink-dark">
                                <svg class="w-4 h-4 absolute left-3 top-2.5 text-muted-light dark:text-muted-dark" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>

                            <div class="flex items-center bg-white dark:bg-slate-800 border border-royal-basic/15 dark:border-royal-violet/20 rounded-lg px-2 py-1.5">
                                <span class="text-[10px] uppercase font-bold text-muted-light dark:text-muted-dark mr-2 ml-1">Date:</span>
                                <input type="date" class="bg-transparent text-xs font-medium outline-none cursor-pointer text-ink-light dark:text-ink-dark">
                            </div>

                            <select class="bg-white dark:bg-slate-800 border border-royal-basic/15 dark:border-royal-violet/20 rounded-lg px-3 py-2 text-xs font-medium outline-none focus:ring-2 focus:ring-royal-mid/50 cursor-pointer text-ink-light dark:text-ink-dark">
                                <option value="">Subject: All</option>

                            </select>

                            <select class="bg-white dark:bg-slate-800 border border-royal-basic/15 dark:border-royal-violet/20 rounded-lg px-3 py-2 text-xs font-medium outline-none focus:ring-2 focus:ring-royal-mid/50 cursor-pointer text-ink-light dark:text-ink-dark">
                                <option value="">Status: All</option>
                                <option>Completed</option>
                                <option>Started</option>
                                <option>Upcoming</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto table-container">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-royal-basic/5 dark:bg-royal-soft/5">
                            <tr>
                                <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-muted-light dark:text-muted-dark uppercase tracking-wider">Date</th>
                                <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-muted-light dark:text-muted-dark uppercase tracking-wider">Subject</th>
                                <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-muted-light dark:text-muted-dark uppercase tracking-wider">Topic</th>
                                <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-muted-light dark:text-muted-dark uppercase tracking-wider">Teacher</th>
                                <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-muted-light dark:text-muted-dark uppercase tracking-wider">Status</th>
                                <th class="px-4 sm:px-6 py-3 text-right text-xs font-semibold text-muted-light dark:text-muted-dark uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-royal-basic/5 dark:divide-royal-violet/10">
                            <?php
                            while ($meeting_details = $res_select->fetch_assoc()) {
                                $status = $meeting_details['status'];
                            ?>
                                <tr class="hover:bg-royal-basic/5 dark:hover:bg-royal-soft/5 transition-colors">
                                    <td class="px-4 sm:px-6 py-4 text-sm text-muted-light dark:text-muted-dark whitespace-nowrap"><?= date('d-m-Y', strtotime($meeting_details['meeting_time'])) ?></td>
                                    <td class="px-4 sm:px-6 py-4 text-sm font-medium text-ink-light dark:text-ink-dark whitespace-nowrap"><?= $meeting_details['sub_name'] ?></td>
                                    <td class="px-4 sm:px-6 py-4 text-sm text-muted-light dark:text-muted-dark whitespace-nowrap">-</td>
                                    <td class="px-4 sm:px-6 py-4 text-sm text-muted-light dark:text-muted-dark"><?= $meeting_details['teacher_name'] ?></td>
                                    <td class="px-4 sm:px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold uppercase 
                                        <?php if ($meeting_details['approved'] == '0'): ?>
                                            bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 border border-red-200/50 
                                            dark:border-red-500/20
                                        <?php elseif ($status == 'completed'): ?>
                                            bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 border border-green-200/50 dark:border-green-500/20
                                        <?php elseif ($status == 'upcoming'): ?>
                                                dark:border-yellow-500/20
                                                bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 border border-yellow-200/50 dark:border-yellow-500/20
                                        <?php elseif ($status == 'started'): ?>
                                            bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 border border-blue-200/50 dark:border-blue-500/20
                                        <?php endif; ?>
                                        whitespace-nowrap">
                                            <?php 
                                                if($meeting_details['approved'] == '0') echo "Rejected"; 
                                                elseif($meeting_details['approved'] == '2') echo "Pending Approval"; 
                                                else echo ucfirst($status);?>
                                        </span>
                                    </td>
                                    <td class="px-4 sm:px-6 py-4 text-right whitespace-nowrap">
                                        <div class="flex items-center justify-end gap-3">
                                            <?php if($meeting_details['approved'] == '0' or $status == 'completed'):
                                                    echo "-";
                                            ?>
                                            <?php elseif ($status == 'started'): ?>
                                                <a href="<?= empty($meeting_details['room_id']) ? 'room.php' : 'room' ?>" class="text-[14px] font-black text-royal-mid dark:text-royal-soft hover:underline">
                                                    Join
                                                </a>
                                            <?php elseif ($status == 'upcoming'): 
                                                    $start = new DateTime($meeting_details['meeting_time']);
                                                    $now   = new DateTime();

                                                    if ($start > $now) {
                                                        $diff = $now->diff($start);
                                                        echo "Starting in " . $diff->format('%d days %h hours %i minutes %s seconds');
                                                    }else{
                                                        ?>
                                                            <a href="<?= empty($meeting_details['room_id']) ? 'room.php' : '#' ?>" class="text-[14px] font-black text-royal-mid dark:text-royal-soft hover:underline">
                                                                Join
                                                            </a>
                                                        <?php
                                                    }
                                                ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/lectures.js.php" defer></script>
    <script src="../assets/js/darkmodeToggle.js.php" defer></script>
    <script src="../assets/js/dashboard.js.php" defer></script>
</body>

</html>