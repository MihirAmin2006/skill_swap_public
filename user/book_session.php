<?php
session_start();
include_once __DIR__ . '/validation.php';
require_once __DIR__ . '/../includes/scripts/connection.php';
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

?>

<!DOCTYPE html>
<html>

<head>
    <title>2 Person Video Call</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    include_once __DIR__ . '/../includes/scripts/common.php';
    ?>
    <link rel="stylesheet" href="../assets/styles/user/common.css">
    <script src="../assets/js/tailwind.js.php" defer></script>
    <style>
        .gradient-btn {
            background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 55%, #3730a3 100%);
        }

        .gradient-btn:hover {
            background: linear-gradient(135deg, #1d4ed8 0%, #3730a3 55%, #1e3a8a 100%);
        }

        /* ── Smart Search Styles ── */
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

        .search-section {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        /* Search Bar */
        .smart-search-bar {
            position: relative;
            background: rgba(255, 255, 255, 0.85);
            border: 1.5px solid rgba(30, 58, 138, 0.12);
            border-radius: 20px;
            box-shadow: 0 4px 32px rgba(30, 58, 138, 0.08), 0 1px 4px rgba(30, 58, 138, 0.06);
            transition: box-shadow 0.25s, border-color 0.25s;
            backdrop-filter: blur(12px);
        }

        .dark .smart-search-bar {
            background: rgba(30, 27, 75, 0.7);
            border-color: rgba(165, 180, 252, 0.15);
            box-shadow: 0 4px 32px rgba(0, 0, 0, 0.25);
        }

        .smart-search-bar:focus-within {
            border-color: rgba(29, 78, 216, 0.45);
            box-shadow: 0 0 0 4px rgba(29, 78, 216, 0.08), 0 4px 32px rgba(30, 58, 138, 0.1);
        }

        /* Filter Chips */
        .filter-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 99px;
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            border: 1.5px solid rgba(30, 58, 138, 0.13);
            background: rgba(255, 255, 255, 0.7);
            color: #334155;
            transition: all 0.18s;
            backdrop-filter: blur(6px);
            white-space: nowrap;
        }

        .dark .filter-chip {
            background: rgba(30, 27, 75, 0.5);
            border-color: rgba(165, 180, 252, 0.18);
            color: #c7d2fe;
        }

        .filter-chip:hover,
        .filter-chip.active {
            background: linear-gradient(135deg, #1e3a8a, #1d4ed8);
            color: #fff;
            border-color: transparent;
            box-shadow: 0 2px 12px rgba(29, 78, 216, 0.25);
        }

        /* Match Score Badge */
        .match-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 99px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.03em;
        }

        .match-badge.excellent {
            background: #dcfce7;
            color: #166534;
        }

        .match-badge.good {
            background: #dbeafe;
            color: #1e40af;
        }

        .match-badge.fair {
            background: #fef9c3;
            color: #854d0e;
        }

        .dark .match-badge.excellent {
            background: rgba(22, 101, 52, 0.3);
            color: #86efac;
        }

        .dark .match-badge.good {
            background: rgba(30, 64, 175, 0.3);
            color: #93c5fd;
        }

        .dark .match-badge.fair {
            background: rgba(133, 77, 14, 0.3);
            color: #fde047;
        }

        /* Mentor Card */
        .mentor-card {
            background: rgba(255, 255, 255, 0.88);
            border: 1.5px solid rgba(30, 58, 138, 0.09);
            border-radius: 20px;
            transition: transform 0.22s cubic-bezier(.34, 1.56, .64, 1), box-shadow 0.22s, border-color 0.22s;
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
        }

        .dark .mentor-card {
            background: rgba(23, 21, 63, 0.75);
            border-color: rgba(165, 180, 252, 0.12);
        }

        .mentor-card:hover {
            transform: translateY(-4px) scale(1.01);
            box-shadow: 0 16px 48px rgba(30, 58, 138, 0.14), 0 2px 8px rgba(30, 58, 138, 0.08);
            border-color: rgba(29, 78, 216, 0.28);
        }

        .mentor-card::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 20px;
            background: linear-gradient(135deg, rgba(29, 78, 216, 0.03) 0%, transparent 60%);
            pointer-events: none;
        }

        /* Top Pick ribbon */
        .top-pick-ribbon {
            position: absolute;
            top: 14px;
            right: -28px;
            background: linear-gradient(90deg, #1e3a8a, #1d4ed8);
            color: #fff;
            font-size: 0.65rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            padding: 4px 36px 4px 14px;
            transform: rotate(35deg);
            box-shadow: 0 2px 8px rgba(29, 78, 216, 0.4);
        }

        /* Avatar ring */
        .avatar-ring {
            border: 3px solid transparent;
            background-clip: padding-box;
            position: relative;
        }

        .avatar-ring::after {
            content: '';
            position: absolute;
            inset: -3px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1e3a8a, #6366f1);
            z-index: -1;
        }

        /* Star rating */
        .star-fill {
            color: #f59e0b;
        }

        /* Skill Tag */
        .skill-tag {
            font-size: 0.7rem;
            font-weight: 600;
            padding: 3px 9px;
            border-radius: 99px;
            background: rgba(30, 58, 138, 0.07);
            color: #1e3a8a;
            border: 1px solid rgba(30, 58, 138, 0.12);
        }

        .dark .skill-tag {
            background: rgba(165, 180, 252, 0.1);
            color: #a5b4fc;
            border-color: rgba(165, 180, 252, 0.18);
        }

        /* Credit pill */
        .credit-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px;
            border-radius: 99px;
            font-weight: 700;
            font-size: 0.78rem;
        }

        /* Book button */
        .book-btn {
            background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 55%, #3730a3 100%);
            color: #fff;
            border-radius: 12px;
            padding: 9px 20px;
            font-weight: 700;
            font-size: 0.82rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            letter-spacing: 0.02em;
        }

        .book-btn:hover {
            background: linear-gradient(135deg, #1d4ed8 0%, #3730a3 55%, #1e3a8a 100%);
            box-shadow: 0 4px 16px rgba(29, 78, 216, 0.35);
            transform: translateY(-1px);
        }

        .book-btn:disabled {
            background: #94a3b8;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Smart Score Bar */
        .score-bar-track {
            height: 5px;
            border-radius: 99px;
            background: rgba(30, 58, 138, 0.1);
            overflow: hidden;
        }

        .dark .score-bar-track {
            background: rgba(165, 180, 252, 0.1);
        }

        .score-bar-fill {
            height: 100%;
            border-radius: 99px;
            background: linear-gradient(90deg, #1e3a8a, #6366f1);
            transition: width 0.8s cubic-bezier(.34, 1.56, .64, 1);
        }

        /* Search loading shimmer */
        .shimmer {
            background: linear-gradient(90deg, rgba(30, 58, 138, 0.06) 25%, rgba(30, 58, 138, 0.13) 50%, rgba(30, 58, 138, 0.06) 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
        }

        .dark .shimmer {
            background: linear-gradient(90deg, rgba(165, 180, 252, 0.05) 25%, rgba(165, 180, 252, 0.12) 50%, rgba(165, 180, 252, 0.05) 75%);
            background-size: 200% 100%;
        }

        @keyframes shimmer {
            to {
                background-position: -200% 0;
            }
        }

        /* Stats summary bar */
        .stats-bar {
            background: rgba(255, 255, 255, 0.7);
            border: 1.5px solid rgba(30, 58, 138, 0.09);
            border-radius: 16px;
            backdrop-filter: blur(8px);
        }

        .dark .stats-bar {
            background: rgba(23, 21, 63, 0.55);
            border-color: rgba(165, 180, 252, 0.1);
        }

        /* Sort dropdown */
        .sort-select {
            background: rgba(255, 255, 255, 0.85);
            border: 1.5px solid rgba(30, 58, 138, 0.13);
            border-radius: 10px;
            color: #1e3a8a;
            font-weight: 600;
            font-size: 0.78rem;
            padding: 6px 32px 6px 12px;
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%231e3a8a' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            transition: border-color 0.2s;
        }

        .dark .sort-select {
            background-color: rgba(30, 27, 75, 0.6);
            border-color: rgba(165, 180, 252, 0.18);
            color: #a5b4fc;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23a5b4fc' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
        }

        .sort-select:focus {
            outline: none;
            border-color: rgba(29, 78, 216, 0.4);
        }

        /* Pagination */
        .page-btn {
            width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.8rem;
            cursor: pointer;
            border: 1.5px solid rgba(30, 58, 138, 0.13);
            background: rgba(255, 255, 255, 0.7);
            color: #1e3a8a;
            transition: all 0.18s;
        }

        .dark .page-btn {
            background: rgba(30, 27, 75, 0.5);
            border-color: rgba(165, 180, 252, 0.18);
            color: #a5b4fc;
        }

        .page-btn:hover,
        .page-btn.active {
            background: linear-gradient(135deg, #1e3a8a, #1d4ed8);
            color: #fff;
            border-color: transparent;
            box-shadow: 0 2px 8px rgba(29, 78, 216, 0.25);
        }

        .page-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        /* No results */
        .no-results-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(30, 58, 138, 0.07);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
        }

        /* Availability dot */
        .avail-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        .avail-dot.online {
            background: #22c55e;
            box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.25);
        }

        .avail-dot.busy {
            background: #f59e0b;
            box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.25);
        }

        .avail-dot.offline {
            background: #94a3b8;
        }

        /* Animated entrance for cards */
        @keyframes cardIn {
            from {
                opacity: 0;
                transform: translateY(24px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card-animate {
            animation: cardIn 0.4s ease both;
        }
    </style>
</head>

<body class="font-sans bg-page-light text-ink-light overflow-x-hidden dark:bg-page-dark dark:text-ink-dark transition-colors duration-300 relative">
    <!-- ════════════ MOBILE OVERLAY ════════════ -->
    <div id="mobileOverlay" onclick="closeMobileSidebar()"></div>

    <!-- ════════════ ANIMATED BACKGROUND WITH FLOATING ORBS ════════════ -->
    <?php
    include_once __DIR__ . '/../animated-bg.php';
    ?>

    <!-- ════════════ SIDEBAR ════════════ -->
    <?php include_once __DIR__ . '/navbar.php'; ?>

    <main class="lg:ml-64 min-h-screen relative z-10">
        <!-- ════════════ HEADER (UNCHANGED) ════════════ -->
        <header class="sticky top-0 z-40 bg-white/80 dark:bg-card-dark/80 backdrop-blur-xl
                 border-b border-royal-basic/10 dark:border-royal-violet/20
                 transition-colors duration-300">
            <div class="flex items-center justify-between px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center gap-3">
                    <!-- Mobile menu button -->
                    <button onclick="openMobileSidebar()" class="lg:hidden p-2 rounded-xl
                        bg-royal-basic/5 dark:bg-royal-soft/10
                        hover:bg-royal-basic/10 dark:hover:bg-royal-soft/15
                        text-royal-basic dark:text-royal-soft
                        transition-all duration-200">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="3" y1="12" x2="21" y2="12"></line>
                            <line x1="3" y1="6" x2="21" y2="6"></line>
                            <line x1="3" y1="18" x2="21" y2="18"></line>
                        </svg>
                    </button>

                    <div>
                        <h1 class="font-display font-bold text-xl sm:text-2xl text-royal-basic dark:text-royal-soft">Session Booking</h1>
                        <p class="text-xs sm:text-sm text-muted-light dark:text-muted-dark hidden sm:block">Find the mentor and Book the live session</p>
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

        <!-- ════════════ SMART SEARCH SECTION ════════════ -->
        <section class="search-section px-4 sm:px-6 lg:px-8 py-8 max-w-7xl mx-auto">

            <!-- User Context Banner -->
            <div class="stats-bar flex flex-wrap items-center gap-4 px-5 py-4 mb-7">
                <div class="flex items-center gap-2">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center text-white text-sm font-bold"
                        style="background:linear-gradient(135deg,#1e3a8a,#1d4ed8);">
                        <?= $name[0][0] . $name[1][0] ?>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Searching as</p>
                        <p class="text-sm font-bold text-slate-800 dark:text-slate-100"><?= $user_detials['full_name'] ?></p>
                    </div>
                </div>
                <div class="w-px h-8 bg-slate-200 dark:bg-slate-700 hidden sm:block"></div>
                <!-- Credits -->
                <div class="flex items-center gap-2">
                    <div class="p-1.5 rounded-lg" style="background:rgba(30,58,138,0.08);">
                        <svg class="w-4 h-4" style="color:#1d4ed8;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10" />
                            <path d="M12 6v6l4 2" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Your Credits</p>
                        <p class="text-sm font-bold text-blue-700 dark:text-blue-400" id="userCreditsDisplay">
                            <span id="creditsVal"><?= $user_detials['credit'] ?? 0 ?></span> Credits
                        </p>
                    </div>
                </div>
                <div class="w-px h-8 bg-slate-200 dark:bg-slate-700 hidden sm:block"></div>
                <!-- Rating -->
                <div class="flex items-center gap-2">
                    <div class="p-1.5 rounded-lg" style="background:rgba(245,158,11,0.1);">
                        <svg class="w-4 h-4 star-fill" viewBox="0 0 24 24" fill="currentColor">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Your Rating</p>
                        <p class="text-sm font-bold text-amber-600 dark:text-amber-400"><?= number_format($user_detials['rating'] ?? 0, 1) ?> / 5.0</p>
                    </div>
                </div>
                <div class="ml-auto">
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Mentors ranked by compatibility &amp; availability</span>
                </div>
            </div>

            <!-- Smart Search Bar -->
            <div class="smart-search-bar flex items-center gap-3 px-4 py-3 mb-5">
                <!-- Search Icon -->
                <svg class="w-5 h-5 flex-shrink-0" style="color:#1d4ed8;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8" />
                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                </svg>
                <input
                    type="text"
                    id="mentorSearchInput"
                    placeholder="Search by name, skill, subject…"
                    class="flex-1 bg-transparent outline-none text-sm font-medium text-slate-800 dark:text-slate-100 placeholder:text-slate-400 dark:placeholder:text-slate-500"
                    autocomplete="off"
                    oninput="handleSearchInput(this.value)" />
                <!-- Clear -->
                <button id="searchClearBtn" onclick="clearSearch()" class="hidden p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                    <svg class="w-4 h-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
                <!-- Divider -->
                <div class="w-px h-6 bg-slate-200 dark:bg-slate-700 hidden sm:block"></div>
                <!-- Sort -->
                <select id="sortSelect" class="sort-select hidden sm:block" onchange="applySort(this.value)">
                    <option value="smart">✦ Smart Match</option>
                    <option value="rating">⭐ Top Rated</option>
                    <option value="credits">💳 Credits: Low–High</option>
                    <option value="sessions">📅 Most Sessions</option>
                    <option value="name">🔤 Name A–Z</option>
                </select>
                <!-- Search Btn -->
                <button onclick="triggerSearch()" class="book-btn hidden sm:flex items-center gap-2">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8" />
                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                    </svg>
                    Search
                </button>
            </div>

            <!-- Filter Chips -->
            <div class="flex flex-wrap items-center gap-2 mb-6" id="filterChipsRow">
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 mr-1">Filter:</span>

                <button class="filter-chip active" data-filter="all" onclick="setFilter('all',this)">
                    All Mentors
                </button>
                <button class="filter-chip" data-filter="affordable" onclick="setFilter('affordable',this)">
                    💳 Affordable
                </button>
                <button class="filter-chip" data-filter="top-rated" onclick="setFilter('top-rated',this)">
                    ⭐ Top Rated (4.5+)
                </button>
                <button class="filter-chip" data-filter="available" onclick="setFilter('available',this)">
                    🟢 Available Now
                </button>
                <button class="filter-chip" data-filter="new" onclick="setFilter('new',this)">
                    ✨ New Mentors
                </button>

                <div class="ml-auto flex items-center gap-2">
                    <!-- Mobile sort -->
                    <select id="sortSelectMobile" class="sort-select sm:hidden" onchange="applySort(this.value)">
                        <option value="smart">✦ Smart Match</option>
                        <option value="rating">⭐ Top Rated</option>
                        <option value="credits">💳 Credits Low–High</option>
                        <option value="sessions">📅 Most Sessions</option>
                        <option value="name">🔤 Name A–Z</option>
                    </select>
                    <span id="resultCount" class="text-xs font-semibold text-slate-500 dark:text-slate-400"></span>
                </div>
            </div>

            <!-- Results Grid -->
            <div id="mentorGrid" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
                <!-- Skeleton loaders shown while fetching -->
                <?php for ($i = 0; $i < 6; $i++): ?>
                    <div class="mentor-card p-5" id="skeleton-<?= $i ?>">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-12 h-12 rounded-full shimmer"></div>
                            <div class="flex-1">
                                <div class="h-3.5 w-28 rounded-full shimmer mb-2"></div>
                                <div class="h-2.5 w-20 rounded-full shimmer"></div>
                            </div>
                            <div class="h-6 w-16 rounded-full shimmer"></div>
                        </div>
                        <div class="h-2.5 w-full rounded-full shimmer mb-2"></div>
                        <div class="h-2.5 w-3/4 rounded-full shimmer mb-4"></div>
                        <div class="flex gap-2 mb-4">
                            <div class="h-6 w-16 rounded-full shimmer"></div>
                            <div class="h-6 w-20 rounded-full shimmer"></div>
                            <div class="h-6 w-14 rounded-full shimmer"></div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="h-8 w-20 rounded-full shimmer"></div>
                            <div class="h-9 w-24 rounded-xl shimmer"></div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>

            <!-- No Results State (hidden by default) -->
            <div id="noResults" class="hidden text-center py-20">
                <div class="no-results-icon">
                    <svg class="w-9 h-9" style="color:#1d4ed8;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8" />
                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                        <line x1="8" y1="11" x2="14" y2="11" />
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-700 dark:text-slate-200 mb-2">No mentors found</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 max-w-xs mx-auto">Try adjusting your search or filters. We're always adding new mentors!</p>
                <button onclick="clearSearch()" class="book-btn mt-5 mx-auto inline-flex items-center gap-2">
                    Clear Search
                </button>
            </div>

            <!-- Pagination -->
            <div id="paginationRow" class="hidden flex items-center justify-center gap-2 mt-8">
                <button class="page-btn" id="prevPage" onclick="changePage(-1)" disabled>
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6" />
                    </svg>
                </button>
                <div id="pageNumbers" class="flex items-center gap-1.5"></div>
                <button class="page-btn" id="nextPage" onclick="changePage(1)">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6" />
                    </svg>
                </button>
            </div>

        </section><!-- /search-section -->
    </main>

    <!-- ════════════ BOOKING MODAL ════════════ -->
    <div id="bookingModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeBookingModal()"></div>

        <!-- Modal Card -->
        <div class="relative w-full max-w-md bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 overflow-hidden"
            style="animation: cardIn 0.3s ease both;">

            <!-- Header -->
            <div style="background:linear-gradient(135deg,#1e3a8a,#1d4ed8);" class="px-6 py-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-white font-bold text-lg">Book a Session</h2>
                    <button onclick="closeBookingModal()"
                        class="text-white/70 hover:text-white transition-colors p-1 rounded-lg hover:bg-white/10">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                            stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                    </button>
                </div>
                <!-- Mentor info -->
                <div class="flex items-center gap-3">
                    <div id="bm-avatar" class="flex-shrink-0 ring-2 ring-white/30 rounded-full"></div>
                    <div>
                        <p id="bm-name" class="text-white font-bold text-base"></p>
                        <p id="bm-role" class="text-blue-200 text-xs font-medium"></p>
                    </div>
                </div>
            </div>

            <!-- Body -->
            <div class="px-6 py-5 space-y-4">

                <!-- Credit summary -->
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div class="bg-slate-50 dark:bg-slate-800 rounded-xl p-3">
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mb-1">Session Cost</p>
                        <p id="bm-cost" class="text-sm font-bold text-blue-700 dark:text-blue-400"></p>
                    </div>
                    <div class="bg-slate-50 dark:bg-slate-800 rounded-xl p-3">
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mb-1">Your Balance</p>
                        <p id="bm-mybal" class="text-sm font-bold text-slate-700 dark:text-slate-200"></p>
                    </div>
                    <div class="bg-slate-50 dark:bg-slate-800 rounded-xl p-3">
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mb-1">After Booking</p>
                        <p id="bm-afterbal" class="text-sm font-bold text-slate-700 dark:text-slate-200"></p>
                    </div>
                </div>

                <!-- Subject -->
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">
                        Subject <span class="text-red-500">*</span>
                    </label>
                    <select id="bm-subject"
                        class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700
                                   rounded-xl px-4 py-2.5 text-sm font-medium text-slate-800 dark:text-slate-100
                                   focus:outline-none focus:border-blue-500 dark:focus:border-blue-400 transition-colors">
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">
                        Topic <span class="text-red-500">*</span>
                    </label>
                    <input id="bm-topic" type="text" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700
                                   rounded-xl px-4 py-2.5 text-sm font-medium text-slate-800 dark:text-slate-100
                                   focus:outline-none focus:border-blue-500 dark:focus:border-blue-400 transition-colors" placeholder="Topic-1, Topic-2...">
                </div>

                <!-- Date & Time -->
                <div>
                    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">
                        Date &amp; Time <span class="text-red-500">*</span>
                    </label>
                    <input type="datetime-local" id="bm-datetime"
                        class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700
                                  rounded-xl px-4 py-2.5 text-sm font-medium text-slate-800 dark:text-slate-100
                                  focus:outline-none focus:border-blue-500 dark:focus:border-blue-400 transition-colors" />
                </div>

                <!-- Error / Success -->
                <div id="bm-error"
                    class="hidden text-sm font-medium text-red-700 dark:text-red-400 bg-red-50 dark:bg-red-900/20
                            border border-red-200 dark:border-red-800 rounded-xl px-4 py-3"></div>
                <div id="bm-success"
                    class="hidden text-sm font-medium text-green-700 dark:text-green-400 bg-green-50 dark:bg-green-900/20
                            border border-green-200 dark:border-green-800 rounded-xl px-4 py-3"></div>

                <!-- Actions -->
                <div class="flex gap-3 pt-1">
                    <button onclick="closeBookingModal()"
                        class="flex-1 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700
                                   text-sm font-semibold text-slate-600 dark:text-slate-300
                                   hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                        Cancel
                    </button>
                    <button id="bm-submit" onclick="submitBooking()"
                        class="flex-1 py-2.5 rounded-xl text-sm font-bold text-white
                                   flex items-center justify-center gap-2 transition-all"
                        style="background:linear-gradient(135deg,#1e3a8a,#1d4ed8);">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                            stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                            <line x1="16" y1="2" x2="16" y2="6" />
                            <line x1="8" y1="2" x2="8" y2="6" />
                            <line x1="3" y1="10" x2="21" y2="10" />
                        </svg>
                        Confirm Booking
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/darkmodeToggle.js.php" defer></script>
    <script src="../assets/js/dashboard.js.php" defer></script>

    <!-- ════════════ SMART SEARCH ENGINE ════════════ -->
    <script>
        // ─── User context from PHP ───────────────────────────────────────────────
        const CURRENT_USER = {
            id: <?= (int)($_SESSION['user_id'] ?? 0) ?>,
            credits: <?= (int)($user_detials['credit'] ?? 0) ?>,
            rating: <?= (float)($user_detials['rating'] ?? 0) ?>,
            name: <?= json_encode($user_detials['full_name'] ?? '') ?>
        };

        // ─── State ───────────────────────────────────────────────────────────────
        let allMentors = []; // raw data from server
        let filteredList = []; // after search + filter
        let currentPage = 1;
        const PER_PAGE = 9;
        let activeFilter = 'all';
        let activeSort = 'smart';
        let searchQuery = '';
        let searchDebounce = null;

        // ─── Fetch mentors from backend ──────────────────────────────────────────
        async function fetchMentors() {
            try {
                const res = await fetch(window.location.origin + '/skill_swap/user/get_mentors');

                if (!res.ok) {
                    throw new Error(`HTTP ${res.status} - ${res.statusText}`);
                }

                const data = await res.json();

                // Open browser DevTools (F12 → Console) to see this
                console.group('🔍 get_mentors.php response');
                console.log('Success:', data.success);
                console.log('Debug:', data.debug);
                console.log('Count:', data.count);
                console.log('Mentors:', data.mentors);
                if (data.error) console.error('API Error:', data.error);
                console.groupEnd();

                if (!data.success) throw new Error(data.error || 'API returned success=false');

                allMentors = (data.mentors || []).filter(m => m.speciality !== 'Fellow Student');

                // Sync credits display with authoritative server value
                if (data.my_credit !== undefined) {
                    document.getElementById('creditsVal').textContent = data.my_credit;
                }

            } catch (e) {
                console.error('❌ fetchMentors failed:', e.message);
                console.warn('Falling back to demo data — fix api/get_mentors.php path');
                allMentors = generateDemoMentors();
            }
            computeSmartScores();
            applyFilterAndSort();
        }

        // ─── Smart Scoring Algorithm ─────────────────────────────────────────────
        /**
         * Computes a 0–100 "smart match" score for each mentor
         * based on:
         *   40% – Credit affordability  (mentor cost ≤ user credits → high score)
         *   35% – Mentor rating
         *   15% – Availability (online > busy > offline)
         *   10% – Session volume (social proof)
         */
        function computeSmartScores() {
            if (!allMentors.length) return;

            const maxSessions = Math.max(...allMentors.map(m => m.total_sessions || 0)) || 1;
            const maxSharedSubjects = Math.max(...allMentors.map(m => m.shared_subjects || 0)) || 1;

            allMentors.forEach(mentor => {
                let score = 0;

                // 1. Affordability (30 pts)
                //    - Free or within budget → more pts
                //    - Over budget → partial credit if close
                const cost = mentor.credits_per_session || 0;
                const userCredits = CURRENT_USER.credits;
                if (cost === 0) {
                    score += 30;
                } else if (cost <= userCredits) {
                    const ratio = 1 - Math.min(cost / Math.max(userCredits, 1), 1);
                    score += 15 + ratio * 15; // 15–30 pts
                } else {
                    const overRatio = Math.max(0, 1 - (cost - userCredits) / Math.max(userCredits, 50));
                    score += overRatio * 10; // 0–10 pts
                }

                // 2. Rating (30 pts)
                score += ((mentor.rating || 0) / 5) * 30;

                // 3. Shared subjects / relevance (20 pts)
                //    Users who teach/study the exact same subjects as me rank higher
                score += ((mentor.shared_subjects || 0) / maxSharedSubjects) * 20;

                // 4. Availability (15 pts)
                const availPts = {
                    online: 15,
                    busy: 7,
                    offline: 0
                };
                score += availPts[mentor.availability] || 0;

                // 5. Sessions / experience (5 pts – social proof)
                score += ((mentor.total_sessions || 0) / maxSessions) * 5;

                mentor.smart_score = Math.round(Math.min(score, 100));
                mentor.can_afford = cost <= userCredits || cost === 0;
            });
        }

        // ─── Filter + Sort + Search ──────────────────────────────────────────────
        function applyFilterAndSort() {
            let list = [...allMentors];

            // Text search
            if (searchQuery.trim()) {
                const q = searchQuery.toLowerCase();
                list = list.filter(m =>
                    (m.full_name || '').toLowerCase().includes(q) ||
                    (m.speciality || '').toLowerCase().includes(q) ||
                    (m.skills || []).some(s => s.toLowerCase().includes(q))
                );
            }

            // Filter chips
            if (activeFilter === 'affordable') {
                list = list.filter(m => m.can_afford);
            } else if (activeFilter === 'top-rated') {
                list = list.filter(m => (m.rating || 0) >= 4.5);
            } else if (activeFilter === 'available') {
                list = list.filter(m => m.availability === 'online');
            } else if (activeFilter === 'new') {
                list = list.filter(m => m.is_new);
            }

            // Sort
            const sorts = {
                smart: (a, b) => b.smart_score - a.smart_score,
                rating: (a, b) => (b.rating || 0) - (a.rating || 0),
                credits: (a, b) => (a.credits_per_session || 0) - (b.credits_per_session || 0),
                sessions: (a, b) => (b.total_sessions || 0) - (a.total_sessions || 0),
                name: (a, b) => (a.full_name || '').localeCompare(b.full_name || ''),
            };
            list.sort(sorts[activeSort] || sorts.smart);

            filteredList = list;
            currentPage = 1;
            renderGrid();
            renderPagination();
        }

        // ─── Render Mentor Cards ─────────────────────────────────────────────────
        function renderGrid() {
            const grid = document.getElementById('mentorGrid');
            const noR = document.getElementById('noResults');

            if (!filteredList.length) {
                grid.innerHTML = '';
                noR.classList.remove('hidden');
                document.getElementById('resultCount').textContent = '0 mentors';
                return;
            }
            noR.classList.add('hidden');

            const start = (currentPage - 1) * PER_PAGE;
            const page = filteredList.slice(start, start + PER_PAGE);

            document.getElementById('resultCount').textContent =
                filteredList.length + ' mentor' + (filteredList.length !== 1 ? 's' : '');

            grid.innerHTML = page.map((m, idx) => buildMentorCard(m, idx)).join('');

            // Animate entrance
            grid.querySelectorAll('.mentor-card').forEach((el, i) => {
                el.style.animationDelay = (i * 60) + 'ms';
                el.classList.add('card-animate');
            });

            // Animate score bars
            setTimeout(() => {
                grid.querySelectorAll('.score-bar-fill').forEach(bar => {
                    const w = bar.dataset.width;
                    bar.style.width = w + '%';
                });
            }, 100);
        }

        function buildMentorCard(m, idx) {
            const initials = (m.full_name || 'MN')
                .split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();

            const stars = buildStars(m.rating || 0);

            const badge = m.smart_score >= 80 ? 'excellent' :
                m.smart_score >= 55 ? 'good' :
                'fair';
            const badgeLabel = m.smart_score >= 80 ? '🎯 Excellent Match' :
                m.smart_score >= 55 ? '👍 Good Match' :
                '⚖️ Fair Match';

            const availClass = m.availability === 'online' ? 'online' :
                m.availability === 'busy' ? 'busy' :
                'offline';
            const availLabel = m.availability === 'online' ? 'Available Now' :
                m.availability === 'busy' ? 'In a Session' :
                'Offline';

            const skillTags = (m.skills || []).slice(0, 3).map(s =>
                `<span class="skill-tag">${escHtml(s)}</span>`
            ).join('');

            const costLabel = (m.credits_per_session || 0) === 0 ?
                `<span class="credit-pill" style="background:rgba(34,197,94,0.1);color:#16a34a;">Free</span>` :
                m.can_afford ?
                `<span class="credit-pill" style="background:rgba(30,58,138,0.08);color:#1d4ed8;">
                  <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                  ${m.credits_per_session} credits
               </span>` :
                `<span class="credit-pill" style="background:rgba(239,68,68,0.08);color:#dc2626;">
                  <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                  ${m.credits_per_session} credits
               </span>`;

            const bookDisabled = !m.can_afford ? 'disabled title="Not enough credits"' : '';

            const ribbonHtml = idx === 0 && activeSort === 'smart' ?
                `<div class="top-pick-ribbon">TOP PICK</div>` : '';

            return `
        <div class="mentor-card p-5" data-id="${m.user_id}">
            ${ribbonHtml}
            <!-- Top row: avatar + match -->
            <div class="flex items-start gap-3 mb-3">
                <div class="relative flex-shrink-0">
                    ${m.avatar_url
                        ? `<img src="${escHtml(m.avatar_url)}" alt="${escHtml(m.full_name)}"
                               class="w-12 h-12 rounded-full object-cover avatar-ring" />`
                        : `<div class="w-12 h-12 rounded-full avatar-ring flex items-center justify-center text-white font-bold text-sm"
                                style="background:linear-gradient(135deg,#1e3a8a,#6366f1);">${initials}</div>`
                    }
                    <span class="avail-dot ${availClass} absolute bottom-0 right-0 border-2 border-white dark:border-slate-800"
                          style="width:10px;height:10px;"></span>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="font-bold text-sm text-slate-800 dark:text-slate-100 truncate">${escHtml(m.full_name || 'Unknown')}</h3>
                        ${m.is_new ? `<span style="font-size:0.65rem;font-weight:800;background:linear-gradient(90deg,#f59e0b,#ef4444);color:#fff;padding:2px 7px;border-radius:99px;">NEW</span>` : ''}
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium truncate">${escHtml(m.speciality || 'Mentor')}</p>
                    <div class="flex items-center gap-1.5 mt-1">
                        <span class="avail-dot ${availClass}"></span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">${availLabel}</span>
                    </div>
                </div>
                <div>
                    <span class="match-badge ${badge}">${badgeLabel}</span>
                </div>
            </div>

            <!-- Smart Score Bar -->
            <div class="mb-3">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Match Score</span>
                    <span class="text-xs font-bold" style="color:#1d4ed8;">${m.smart_score}%</span>
                </div>
                <div class="score-bar-track">
                    <div class="score-bar-fill" data-width="${m.smart_score}" style="width:0%"></div>
                </div>
            </div>

            <!-- Rating + Sessions -->
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-1">
                    ${stars}
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-200 ml-1">${(m.rating||0).toFixed(1)}</span>
                    <span class="text-xs text-slate-400">(${m.review_count || 0})</span>
                </div>
                <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">${m.total_sessions || 0} sessions</span>
            </div>

            <!-- Skill Tags -->
            ${skillTags ? `<div class="flex flex-wrap gap-1.5 mb-4">${skillTags}</div>` : ''}

            <!-- Footer: Cost + Book -->
            <div class="flex items-center justify-between pt-3 border-t border-slate-100 dark:border-slate-700/50">
                ${costLabel}
                <button class="book-btn flex items-center gap-1.5" ${bookDisabled}
                    onclick="openBookingModal(${m.user_id})">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    Book Session
                </button>
            </div>
        </div>`;
        }

        function buildStars(rating) {
            let html = '';
            for (let i = 1; i <= 5; i++) {
                const full = i <= Math.floor(rating);
                const half = !full && i - 0.5 <= rating;
                const color = full || half ? '#f59e0b' : '#d1d5db';
                html += `<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="${full ? '#f59e0b' : 'none'}" stroke="${color}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
            </svg>`;
            }
            return html;
        }

        // ─── Pagination ──────────────────────────────────────────────────────────
        function renderPagination() {
            const totalPages = Math.ceil(filteredList.length / PER_PAGE);
            const row = document.getElementById('paginationRow');

            if (totalPages <= 1) {
                row.classList.add('hidden');
                return;
            }
            row.classList.remove('hidden');

            const nums = document.getElementById('pageNumbers');
            const prevBtn = document.getElementById('prevPage');
            const nextBtn = document.getElementById('nextPage');

            prevBtn.disabled = currentPage === 1;
            nextBtn.disabled = currentPage === totalPages;

            nums.innerHTML = '';
            for (let p = 1; p <= totalPages; p++) {
                const btn = document.createElement('button');
                btn.className = 'page-btn' + (p === currentPage ? ' active' : '');
                btn.textContent = p;
                btn.onclick = () => {
                    currentPage = p;
                    renderGrid();
                    renderPagination();
                };
                nums.appendChild(btn);
            }
        }

        function changePage(dir) {
            const totalPages = Math.ceil(filteredList.length / PER_PAGE);
            currentPage = Math.max(1, Math.min(totalPages, currentPage + dir));
            renderGrid();
            renderPagination();
            document.querySelector('.search-section').scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }

        // ─── UI Handlers ─────────────────────────────────────────────────────────
        function handleSearchInput(val) {
            document.getElementById('searchClearBtn').classList.toggle('hidden', !val);
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(() => {
                searchQuery = val;
                applyFilterAndSort();
            }, 280);
        }

        function clearSearch() {
            document.getElementById('mentorSearchInput').value = '';
            document.getElementById('searchClearBtn').classList.add('hidden');
            searchQuery = '';
            applyFilterAndSort();
        }

        function triggerSearch() {
            searchQuery = document.getElementById('mentorSearchInput').value;
            applyFilterAndSort();
        }

        function setFilter(filter, el) {
            activeFilter = filter;
            document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
            el.classList.add('active');
            applyFilterAndSort();
        }

        function applySort(val) {
            activeSort = val;
            // keep both selects in sync
            document.getElementById('sortSelect').value = val;
            document.getElementById('sortSelectMobile').value = val;
            applyFilterAndSort();
        }

        // ─── Booking Modal ────────────────────────────────────────────────────────
        let bookingMentor = null;

        function openBookingModal(mentorId) {
            bookingMentor = allMentors.find(m => m.user_id === mentorId);
            if (!bookingMentor) return;
            const m = bookingMentor;
            const initials = (m.full_name || 'MN').split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();

            document.getElementById('bm-avatar').innerHTML = m.avatar_url ?
                `<img src="${escHtml(m.avatar_url)}" class="w-14 h-14 rounded-full object-cover" />` :
                `<div class="w-14 h-14 rounded-full flex items-center justify-center text-white font-bold text-lg"
                    style="background:linear-gradient(135deg,#1e3a8a,#6366f1);">${initials}</div>`;
            document.getElementById('bm-name').textContent = m.full_name;
            document.getElementById('bm-role').textContent = m.speciality || 'Mentor';
            document.getElementById('bm-cost').textContent = m.credits_per_session === 0 ? 'Free' : m.credits_per_session + ' credits';
            document.getElementById('bm-mybal').textContent = CURRENT_USER.credits + ' credits';
            document.getElementById('bm-afterbal').textContent = (CURRENT_USER.credits - (m.credits_per_session || 0)) + ' credits';

            // Populate subject dropdown — auto-select when only one subject exists
            const subSel = document.getElementById('bm-subject');
            subSel.innerHTML = '';
            const subjects = (m.subject_ids_map && m.subject_ids_map.length) ? m.subject_ids_map : [];
            if (subjects.length === 0) {
                subSel.innerHTML = '<option value="">— No subjects available —</option>';
            } else if (subjects.length === 1) {
                // Only one subject: auto-select it so the user never sees a blank value
                subSel.innerHTML = `<option value="${subjects[0].id}">${escHtml(subjects[0].name)}</option>`;
            } else {
                subSel.innerHTML = '<option value="">— Select a subject —</option>';
                subjects.forEach(s => {
                    subSel.innerHTML += `<option value="${s.id}">${escHtml(s.name)}</option>`;
                });
            }

            document.getElementById('bm-datetime').value = '';
            document.getElementById('bm-error').classList.add('hidden');
            document.getElementById('bm-success').classList.add('hidden');
            document.getElementById('bm-submit').disabled = false;
            document.getElementById('bm-submit').innerHTML = `
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
            </svg> Confirm Booking`;

            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            document.getElementById('bm-datetime').min = now.toISOString().slice(0, 16);

            document.getElementById('bookingModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeBookingModal() {
            document.getElementById('bookingModal').classList.add('hidden');
            document.body.style.overflow = '';
            bookingMentor = null;
        }

        async function submitBooking() {
            const m = bookingMentor;
            const subId = document.getElementById('bm-subject').value;
            const topic = document.getElementById('bm-topic').value;
            const datetime = document.getElementById('bm-datetime').value;
            const errBox = document.getElementById('bm-error');
            const sucBox = document.getElementById('bm-success');
            const btn = document.getElementById('bm-submit');

            errBox.classList.add('hidden');
            sucBox.classList.add('hidden');

            if (!subId) {
                showBmError('Please select a subject.');
                return;
            }
            if (!topic) {
                showBmError('Please add a topic.');
                return;
            }
            if (!datetime) {
                showBmError('Please pick a date and time.');
                return;
            }

            if ((m.credits_per_session || 0) > CURRENT_USER.credits) {
                showBmError('You do not have enough credits for this session.');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = `<svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Booking…`;

            // Build payload
            const teacher_id = parseInt(m.user_id);
            const sub_id_val = parseInt(subId);
            const topic_of_sub = topic;
            const meeting_time = datetime.replace('T', ' ') + ':00';

            const payload = {
                teacher_id: teacher_id,
                sub_id: sub_id_val,
                topic: topic_of_sub,
                meeting_time: meeting_time,
            };

            console.log('📤 submitBooking payload:', payload);

            try {
                // Use absolute path derived from current page location to avoid any path ambiguity
                // No .php extension — Apache rewrites booking.php → booking (301),
                // and 301 redirects convert POST to GET, wiping the request body.
                // Use the final URL directly to avoid the redirect entirely.
                const bookingUrl = window.location.href.replace(/\/[^\/]*$/, '/booking');
                console.log('📡 POSTing to:', bookingUrl);
                const res = await fetch(bookingUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();

                if (data.success) {
                    // Use server-returned balance (authoritative) instead of client-side subtraction
                    CURRENT_USER.credits = (data.new_credit !== undefined) ? data.new_credit : CURRENT_USER.credits - (m.credits_per_session || 0);
                    document.getElementById('creditsVal').textContent = CURRENT_USER.credits;
                    document.getElementById('bm-mybal').textContent = CURRENT_USER.credits + ' credits';

                    sucBox.textContent = '✅ Session booked with ' + m.full_name + '!';
                    sucBox.classList.remove('hidden');
                    btn.innerHTML = '✓ Booked!';

                    computeSmartScores();
                    applyFilterAndSort();
                    setTimeout(closeBookingModal, 2800);
                } else {
                    showBmError(data.error || 'Booking failed. Please try again.');
                    btn.disabled = false;
                    btn.innerHTML = 'Confirm Booking';
                }
            } catch (e) {
                showBmError('Network error: ' + e.message);
                btn.disabled = false;
                btn.innerHTML = 'Confirm Booking';
            }
        }

        function showBmError(msg) {
            const b = document.getElementById('bm-error');
            b.textContent = '⚠ ' + msg;
            b.classList.remove('hidden');
        }

        // ─── Helpers ─────────────────────────────────────────────────────────────
        function escHtml(str) {
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        // ─── Demo data (shown when API endpoint isn't ready yet) ─────────────────
        function generateDemoMentors() {
            const names = ['Priya Sharma', 'Rahul Mehta', 'Anjali Patel', 'Vikram Singh', 'Sneha Gupta', 'Arjun Nair', 'Kavya Reddy', 'Rohan Joshi', 'Meera Iyer', 'Siddharth Das'];
            const specs = ['Web Development', 'Data Science', 'UI/UX Design', 'Machine Learning', 'Cloud Computing', 'Cybersecurity', 'Mobile Dev', 'DevOps', 'Blockchain', 'AI/ML'];
            const skills = [
                ['React', 'Node.js', 'MongoDB'],
                ['Python', 'Pandas', 'SQL'],
                ['Figma', 'Adobe XD', 'CSS'],
                ['TensorFlow', 'PyTorch', 'NLP'],
                ['AWS', 'Azure', 'Docker'],
                ['Penetration Testing', 'Network Security'],
                ['Flutter', 'Swift', 'Kotlin'],
                ['CI/CD', 'Kubernetes', 'Jenkins'],
                ['Solidity', 'Web3', 'Ethereum'],
                ['Deep Learning', 'Computer Vision']
            ];
            const avails = ['online', 'online', 'busy', 'online', 'offline', 'online', 'busy', 'online', 'offline', 'online'];

            return names.map((n, i) => ({
                user_id: i + 1,
                full_name: n,
                speciality: specs[i],
                skills: skills[i],
                rating: +(3.5 + Math.random() * 1.5).toFixed(1),
                review_count: Math.floor(10 + Math.random() * 200),
                credits_per_session: [0, 5, 10, 15, 20, 25, 30, 8, 12, 18][i],
                total_sessions: Math.floor(50 + Math.random() * 500),
                availability: avails[i],
                is_new: i === 6 || i === 9,
                avatar_url: null,
            }));
        }

        // ─── Init ─────────────────────────────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', fetchMentors);
    </script>
</body>

</html>