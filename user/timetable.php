<?php
session_start();
include_once __DIR__ . '/validation.php';
require_once __DIR__ . '/../includes/scripts/connection.php';

if (empty($_SESSION['isloggedin']) || $_SESSION['isloggedin'] !== true || empty($_SESSION['login_token'])) {
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

?>
<!DOCTYPE html>
<html lang="en" class="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timetable | Royal Academy</title>
    <?php include_once __DIR__ . '/../includes/scripts/common.php'; ?>
    <link rel="stylesheet" href="../assets/styles/user/common.css">
    <script src="../assets/js/tailwind.js.php" defer></script>
</head>

<body class="font-sans bg-page-light text-ink-light dark:bg-page-dark dark:text-ink-dark transition-colors duration-300 overflow-x-hidden">

    <div id="mobileOverlay" onclick="closeMobileSidebar()"></div>
    <?php include_once __DIR__ . '/../animated-bg.php'; ?>
    <?php include_once __DIR__ . '/navbar.php'; ?>

    <main class="lg:ml-64 min-h-screen relative z-10">
        <header class="sticky top-0 z-40 bg-white/80 dark:bg-card-dark/80 backdrop-blur-xl border-b border-royal-basic/10 dark:border-royal-violet/20">
            <div class="flex items-center justify-between px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center gap-3">
                    <button onclick="openMobileSidebar()" class="lg:hidden p-2 rounded-xl bg-royal-basic/5 dark:bg-royal-soft/10 text-royal-basic dark:text-royal-soft">☰</button>
                    <div>
                        <h1 class="font-display font-bold text-xl sm:text-2xl text-royal-basic dark:text-royal-soft">Timetable</h1>
                        <p class="text-xs sm:text-sm text-muted-light dark:text-muted-dark hidden sm:block">Interactive daily schedule</p>
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

        <div class="p-4 sm:p-6 lg:p-8 space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white/90 dark:bg-card-dark/90 rounded-2xl p-5 border border-royal-basic/10 shadow-card">
                    <p class="text-xs text-muted-light dark:text-muted-dark uppercase font-bold tracking-wider">Total Classes</p>
                    <h3 id="totalClasses" class="text-2xl font-bold text-royal-basic dark:text-royal-soft">0</h3>
                </div>
                <div class="bg-white/90 dark:bg-card-dark/90 rounded-2xl p-5 border border-royal-basic/10 shadow-card">
                    <p class="text-xs text-muted-light dark:text-muted-dark uppercase font-bold tracking-wider">Ongoing Now</p>
                    <h3 id="todayClasses" class="text-2xl font-bold text-blue-600 dark:text-blue-400">0</h3>
                </div>
                <div class="bg-white/90 dark:bg-card-dark/90 rounded-2xl p-5 border border-royal-basic/10 shadow-card">
                    <p class="text-xs text-muted-light dark:text-muted-dark uppercase font-bold tracking-wider">Next Session</p>
                    <h3 id="lastClassTime" class="text-2xl font-bold text-green-600 dark:text-green-400">—</h3>
                </div>
            </div>

            <div class="bg-white/90 dark:bg-card-dark/90 rounded-2xl border border-royal-basic/10 shadow-card p-5">
                <div class="flex items-center justify-between mb-4">
                    <button id="prevMonth" class="p-2 rounded-lg bg-royal-basic/5 hover:bg-royal-basic/10 text-royal-basic">◀</button>
                    <h3 id="calendarTitle" class="font-display font-bold text-royal-basic dark:text-royal-soft"></h3>
                    <button id="nextMonth" class="p-2 rounded-lg bg-royal-basic/5 hover:bg-royal-basic/10 text-royal-basic">▶</button>
                </div>
                <div class="grid grid-cols-7 text-center text-[11px] font-black uppercase text-muted-light dark:text-muted-dark mb-1">
                    <div class="py-2">Mon</div>
                    <div class="py-2">Tue</div>
                    <div class="py-2">Wed</div>
                    <div class="py-2">Thu</div>
                    <div class="py-2">Fri</div>
                    <div class="py-2">Sat</div>
                    <div class="py-2">Sun</div>
                </div>
                <div id="calendarGrid" class="grid grid-cols-7 text-center text-sm border-t border-l border-royal-basic/10 dark:border-royal-soft/10">
                </div>
            </div>

            <div class="bg-white/90 dark:bg-card-dark/90 rounded-2xl border border-royal-basic/10 shadow-card overflow-x-auto">
                <table class="w-full border-collapse min-w-[750px]">
                    <thead class="bg-muted-light/10 dark:bg-muted-dark/10 border-b border-royal-basic/10">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Time</th>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Subject</th>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Status</th>
                            <th class="px-6 py-4 text-center text-xs font-bold uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody id="timetableBody" class="divide-y divide-royal-basic/5">
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script src="../assets/js/timetable.js.php" defer></script>
    <script src="../assets/js/darkmodeToggle.js.php" defer></script>
    <script src="../assets/js/dashboard.js.php" defer></script>
</body>

</html>