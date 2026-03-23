<?php
session_start();
require_once __DIR__ . '/../includes/scripts/connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Auth/sign_in.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == 'POST') {
    $sub_name = trim($_POST['subjectName']);
    $form_role = $_POST['role'];  // 'mentor', 'learner', 'both'
    $user_id = $_SESSION['user_id'];


    $subjects = array_filter(array_map('trim', explode(',', $sub_name)));

    $conn->begin_transaction();

    try {
        foreach ($subjects as $subject_name) {
            // STEP 1: CHECK if subject exists first
            $stmt_check = $conn->prepare("SELECT `sub_id` FROM `subject_master` WHERE `sub_name` = ?");
            $stmt_check->bind_param("s", $subject_name);
            $stmt_check->execute();
            $result = $stmt_check->get_result();
            
            if ($result->num_rows > 0) {
                // Subject EXISTS - get sub_id
                $sub_id = $result->fetch_row()[0];
                $stmt_check->close();
            } else {
                // Subject DOESN'T exist - INSERT new
                $stmt_insert = $conn->prepare("
                    INSERT INTO `subject_master` (`sub_name`, `total_teachers`, `total_students`) 
                    VALUES (?, 0, 0)
                ");
                $stmt_insert->bind_param("s", $subject_name);
                $stmt_insert->execute();
                $sub_id = $conn->insert_id;
                $stmt_insert->close();
                $stmt_check->close();
            }
                $user_role = 'student';
                
                $stmt2 = $conn->prepare("INSERT IGNORE INTO `user_roles` (`sub_id`, `user_id`, `user_role`) VALUES (?, ?, ?)");
                $stmt2->bind_param("iis", $sub_id, $user_id, $user_role);
                $stmt2->execute();
                $stmt2->close();

                
                    $stmt3 = $conn->prepare("UPDATE `subject_master` SET `total_students` = `total_students` + 1 WHERE `sub_id` = ?");

                $stmt3->bind_param("i", $sub_id);
                $stmt3->execute();
                $stmt3->close();

        }

        $conn->commit();
        header("Location: /skill_swap/user/dashboard.php");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>console.log('Error: " . $e->getMessage() . "'); alert('Failed to save skills');</script>";
    }
}

?>
<!DOCTYPE html>
<html lang="en" class="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkillSwap – Add Skill</title>
    <link rel="stylesheet" href="../assets/styles/styles.css">
    <?php
    include_once __DIR__ . '/../includes/scripts/common.php';
    ?>
    <script src="../assets/js/tailwind.js.php" defer></script>
    <style>
        /* ═══ backgrounds ═══ */
        .mesh-bg {
            position: fixed;
            inset: 0;
            z-index: 0;
            background:
                radial-gradient(circle at 12% 50%, rgba(30, 58, 138, 0.08), transparent 50%),
                radial-gradient(circle at 88% 25%, rgba(245, 158, 11, 0.07), transparent 50%),
                radial-gradient(circle at 50% 85%, rgba(99, 102, 241, 0.06), transparent 50%);
        }

        .dark .mesh-bg {
            background:
                radial-gradient(circle at 12% 50%, rgba(30, 58, 138, 0.20), transparent 50%),
                radial-gradient(circle at 88% 25%, rgba(245, 158, 11, 0.11), transparent 50%),
                radial-gradient(circle at 50% 85%, rgba(99, 102, 241, 0.14), transparent 50%);
        }

        /* ═══ orbs ═══ */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(40px);
        }

        .orb1 {
            width: 110px;
            height: 110px;
            background: linear-gradient(135deg, #1e3a8a, #6366f1);
            top: 10%;
            left: 15%;
        }

        .orb2 {
            width: 75px;
            height: 75px;
            background: linear-gradient(135deg, #f59e0b, #fbbf24);
            top: 60%;
            right: 20%;
        }

        .orb3 {
            width: 140px;
            height: 140px;
            background: linear-gradient(135deg, #1e3a8a, #3730a3);
            bottom: 15%;
            left: 25%;
        }

        .orb4 {
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, #fbbf24, #1e3a8a);
            top: 30%;
            right: 35%;
        }

        .orb5 {
            width: 90px;
            height: 90px;
            background: linear-gradient(135deg, #6366f1, #1e3a8a);
            bottom: 40%;
            right: 15%;
        }

        .orb6 {
            width: 65px;
            height: 65px;
            background: linear-gradient(135deg, #1e3a8a, #f59e0b);
            top: 50%;
            left: 10%;
        }

        /* ═══ top stripe ═══ */
        .top-stripe {
            height: 3px;
            background: linear-gradient(90deg, #1e3a8a, #1d4ed8, #f59e0b, #1d4ed8, #1e3a8a);
        }

        /* ═══ button gradient ═══ */
        .btn-gradient {
            background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 55%, #3730a3 100%);
        }
    </style>
</head>

<body class="font-sans bg-page-light dark:bg-page-dark text-ink-light dark:text-ink-dark transition-colors duration-300">
    <div class="fixed inset-0 z-0 bg-page-light dark:bg-page-dark transition-colors duration-400"
        style="background-image:
       radial-gradient(ellipse 70% 55% at 12% 50%, rgba(30,58,138,0.08) 0%, transparent 70%),
       radial-gradient(ellipse 55% 70% at 88% 25%, rgba(245,158,11,0.07) 0%, transparent 70%),
       radial-gradient(ellipse 45% 45% at 50% 85%, rgba(99,102,241,0.06) 0%, transparent 60%);">

        <!-- 6 floating orbs – sizes / positions / durations all via inline style; shape & anim via Tailwind -->
        <div class="absolute rounded-full pointer-events-none animate-float-20"
            style="width:110px;height:110px;left:4%;bottom:-60px;background:linear-gradient(135deg,rgba(30,58,138,0.2),rgba(99,102,241,0.08));"></div>
        <div class="absolute rounded-full pointer-events-none animate-float-25d2"
            style="width:75px;height:75px;left:18%;bottom:-50px;background:linear-gradient(135deg,rgba(245,158,11,0.25),rgba(251,191,36,0.1));"></div>
        <div class="absolute rounded-full pointer-events-none animate-float-28d5"
            style="width:140px;height:140px;left:40%;bottom:-70px;background:linear-gradient(135deg,rgba(30,58,138,0.14),rgba(29,78,216,0.06));"></div>
        <div class="absolute rounded-full pointer-events-none animate-float-22d8"
            style="width:55px;height:55px;left:60%;bottom:-40px;background:linear-gradient(135deg,rgba(245,158,11,0.2),rgba(30,58,138,0.1));"></div>
        <div class="absolute rounded-full pointer-events-none animate-float-26d1"
            style="width:90px;height:90px;left:78%;bottom:-55px;background:linear-gradient(135deg,rgba(99,102,241,0.18),rgba(30,58,138,0.08));"></div>
        <div class="absolute rounded-full pointer-events-none animate-float-21d4"
            style="width:65px;height:65px;left:90%;bottom:-45px;background:linear-gradient(135deg,rgba(30,58,138,0.22),rgba(245,158,11,0.12));"></div>
    </div>


    <!-- ════════════ NAVBAR ════════════ -->
    <nav class="fixed top-0 left-0 right-0 z-50
            bg-page-light/75 dark:bg-page-dark/75
            backdrop-blur-xl
            border-b border-royal-DEFAULT/13 dark:border-royal-violet/18
            transition-colors duration-400">
        <div class="max-w-5xl mx-auto px-5 py-3 flex items-center justify-between">

            <!-- logo -->
            <a href="#" class="flex items-center gap-2.5 no-underline">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center"
                    style="background:linear-gradient(135deg,#1e3a8a,#1d4ed8);">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                </div>
                <span class="font-display font-bold text-lg text-royal-DEFAULT dark:text-royal-soft">
                    Skill<span class="text-gold-DEFAULT">Swap</span>
                </span>
            </a>

            <!-- dark-mode toggle -->
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
        </div>
    </nav>


    <!-- ═══ top stripe ═══ -->
    <div class="top-stripe fixed top-0 left-0 w-full z-50"></div>

    <!-- ═══ main content ═══ -->
    <main class="relative z-10 min-h-screen flex flex-col items-center justify-center px-4 py-20">
        <div class="w-full max-w-md">
            <!-- card -->
            <div class="bg-white/92 dark:bg-card-dark/94 backdrop-blur-sm
                   rounded-2xl shadow-card dark:shadow-card-dk
                   border border-royal-DEFAULT/13 dark:border-royal-violet/18
                   overflow-hidden animate-card-in">
                <!-- heading -->
                <div class="px-7 pt-7 pb-5 border-b border-royal-DEFAULT/13 dark:border-royal-violet/20">
                    <h2 id="formHeading" class="font-display text-2xl font-bold text-royal-DEFAULT dark:text-royal-soft">
                        Add Your Skill
                    </h2>
                    <p id="formSub" class="text-sm text-muted-light dark:text-muted-dark mt-1.5">
                        Share what you can teach or want to learn
                    </p>
                </div>

                <!-- form body -->
                <div class="px-7 py-6">
                    <form method="post" action=#" id="skillForm" class="flex flex-col gap-5">
                        <!-- Subject Name -->
                        <div>
                            <label for="subjectName" class="block text-xs font-semibold uppercase tracking-wide mb-2
                                   text-muted-light dark:text-muted-dark">
                                Subject Name
                            </label>
                            <div class="relative">
                                <input type="text" id="subjectName" name="subjectName" required
                                    placeholder="e.g., Web Development, Guitar, Photography"
                                    class="w-full pl-11 pr-4 py-3 rounded-xl
                                       bg-white dark:bg-card-dark/70
                                       border border-indigo-200 dark:border-indigo-500/28
                                       text-ink-light dark:text-ink-dark
                                       placeholder-ghost-light dark:placeholder-ghost-dark
                                       focus:border-royal-mid dark:focus:border-royal-violet
                                       focus:shadow-focus-ring dark:focus:shadow-focus-dk
                                       transition-all duration-200 outline-none">
                                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-5 h-5 text-royal-mid dark:text-royal-indigo pointer-events-none"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                </svg>
                            </div>
                        </div>

                        <!-- Role -->
                        <div>
                            <label for="role" class="block text-xs font-semibold uppercase tracking-wide mb-2
                                   text-muted-light dark:text-muted-dark">
                                Role
                            </label>
                            <div class="relative">
                                <select id="role" name="role" required
                                    class="w-full pl-11 pr-10 py-3 rounded-xl appearance-none cursor-pointer
                                       bg-white dark:bg-card-dark/70
                                       border border-indigo-200 dark:border-indigo-500/28
                                       text-ink-light dark:text-ink-dark
                                       focus:border-royal-mid dark:focus:border-royal-violet
                                       focus:shadow-focus-ring dark:focus:shadow-focus-dk
                                       transition-all duration-200 outline-none">
                                    <option value="learner" disabled selected>Learner – I want to learn this skill</option>
                                </select>
                                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-5 h-5 text-royal-mid dark:text-royal-indigo pointer-events-none"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-royal-mid dark:text-royal-indigo pointer-events-none"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>

                        <!-- divider -->
                        <div class="h-px bg-royal-DEFAULT/13 dark:bg-royal-violet/20"></div>

                        <!-- submit button -->
                        <button type="submit"
                            class="w-full py-3.5 px-6 rounded-xl
                               btn-gradient text-white font-semibold
                               shadow-btn hover:shadow-btn-hov
                               hover:scale-[1.02] active:scale-[0.98]
                               transition-all duration-200">
                            Submit Skill
                        </button>
                    </form>
                </div>
            </div>

            <!-- footer -->
            <p class="text-center text-xs mt-5 text-ghost-light dark:text-ghost-dark">
                © 2026 SkillSwap – College Micro-Mentoring Platform
            </p>
        </div>
    </main>

    <!-- ════════════ JS ════════════ -->
    <script>
        function toggleTheme() {
            const html = document.documentElement;
            html.classList.toggle('dark');
            const dark = html.classList.contains('dark');

            // sun ↔ moon
            document.getElementById('iconSun').classList.toggle('hidden', dark);
            document.getElementById('iconMoon').classList.toggle('hidden', !dark);

            // knob slide
            const knob = document.getElementById('toggleKnob');
            knob.style.transform = dark ? 'translateX(24px)' : 'translateX(0px)';

            // SSO icon stroke colour
            const ssoSvg = document.querySelector('#ssoBtn svg');
            ssoSvg.setAttribute('stroke', dark ? '#a5b4fc' : '#1e3a8a');

            // hint pills – re-apply idle colour for current theme
            ['hintLen', 'hintUpper', 'hintNum'].forEach(id => {
                const el = document.getElementById(id);
                if (!el.classList.contains('bg-green-100')) {
                    // still idle – swap dark/light idle classes
                    el.classList.toggle('bg-royal-DEFAULT/7', !dark);
                    el.classList.toggle('text-muted-light', !dark);
                    el.classList.toggle('bg-royal-indigo/13', dark);
                    el.classList.toggle('text-muted-dark', dark);
                }
            });
        }
    </script>
</body>

</html>