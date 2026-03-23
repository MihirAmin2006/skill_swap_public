<?php
session_start();
include '../includes/scripts/common.php';
?>
<!DOCTYPE html>
<html lang="en" class="light">

<head>
    <link rel="stylesheet" href="../assets/styles/styles.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    /* ── fonts ── */
                    fontFamily: {
                        sans: ["'DM Sans'", 'sans-serif'],
                        display: ["'Playfair Display'", 'serif'],
                    },

                    /* ── brand colours ── */
                    colors: {
                        royal: {
                            DEFAULT: '#1e3a8a',
                            mid: '#1d4ed8',
                            light: '#eef2ff',
                            soft: '#a5b4fc',
                            violet: '#818cf8',
                            indigo: '#6366f1',
                            deep: '#3730a3'
                        },
                        gold: {
                            DEFAULT: '#f59e0b',
                            bright: '#fbbf24'
                        },
                        ink: {
                            light: '#1e293b',
                            dark: '#e2e8f0'
                        },
                        muted: {
                            light: '#64748b',
                            dark: '#94a3b8'
                        },
                        ghost: {
                            light: '#94a3b8',
                            dark: '#64748b'
                        },
                        page: {
                            light: '#f1f5f9',
                            dark: '#0f172a'
                        },
                        card: {
                            light: '#ffffff',
                            dark: '#1e293b'
                        },
                    },

                    /* ── shadows ── */
                    boxShadow: {
                        card: '0 8px 32px rgba(30,58,138,0.12)',
                        'card-dk': '0 8px 40px rgba(0,0,0,0.35)',
                        btn: '0 4px 16px rgba(30,58,138,0.42)',
                        'btn-hov': '0 6px 24px rgba(30,58,138,0.58)',
                        tab: '0 2px 8px rgba(30,58,138,0.40)',
                        knob: '0 1px 4px rgba(0,0,0,0.25)',
                        'focus-ring': '0 0 0 3px rgba(29,78,216,0.15)',
                        'focus-dk': '0 0 0 3px rgba(129,140,248,0.18)',
                    },

                    /* ── keyframes ── */
                    keyframes: {
                        floatUp: {
                            '0%': {
                                transform: 'translateY(0) rotate(0deg)',
                                opacity: '0'
                            },
                            '8%': {
                                opacity: '1'
                            },
                            '92%': {
                                opacity: '1'
                            },
                            '100%': {
                                transform: 'translateY(-105vh) rotate(540deg)',
                                opacity: '0'
                            },
                        },
                        cardIn: {
                            from: {
                                opacity: '0',
                                transform: 'translateY(24px)'
                            },
                            to: {
                                opacity: '1',
                                transform: 'translateY(0)'
                            },
                        },
                        panelIn: {
                            from: {
                                opacity: '0',
                                transform: 'translateY(10px)'
                            },
                            to: {
                                opacity: '1',
                                transform: 'translateY(0)'
                            },
                        },
                        slideKnob: {
                            from: {
                                transform: 'translateX(0px)'
                            },
                            to: {
                                transform: 'translateX(24px)'
                            },
                        },
                    },

                    /* ── named animations ── */
                    animation: {
                        'float-20': 'floatUp 20s linear infinite',
                        'float-25d2': 'floatUp 25s linear infinite 2s',
                        'float-28d5': 'floatUp 28s linear infinite 5s',
                        'float-22d8': 'floatUp 22s linear infinite 8s',
                        'float-26d1': 'floatUp 26s linear infinite 1s',
                        'float-21d4': 'floatUp 21s linear infinite 4s',
                        'card-in': 'cardIn 0.55s cubic-bezier(.22,.68,0,1.15) forwards',
                        'panel-in': 'panelIn 0.38s ease forwards',
                        'knob-out': 'slideKnob 0.35s cubic-bezier(.4,0,.2,1) forwards',
                        'knob-back': 'slideKnob 0.35s cubic-bezier(.4,0,.2,1) reverse forwards',
                    },

                    /* ── spacing / sizing helpers ── */
                    width: {
                        '42': '42px',
                        '22': '22px',
                        '52': '52px'
                    },
                    height: {
                        '28': '28px',
                        '22': '22px'
                    },
                    minWidth: {
                        '42': '42px'
                    },

                    /* ── translate ── */
                    translate: {
                        'knob': '24px'
                    },
                },
            },

            /* ── safelist: every class that JS toggles at runtime ── */
            safelist: [
                // dark-mode page / card / nav
                'dark:bg-page-dark', 'dark:text-ink-dark',
                'dark:bg-royal-soft/10', 'dark:border-royal-violet/16',
                'dark:bg-card-dark/94', 'dark:shadow-card-dk',
                'dark:border-royal-violet/18',
                // inputs dark
                'dark:bg-card-dark/70', 'dark:border-royal-indigo/28',
                'dark:placeholder-ghost-dark',
                'dark:text-ink-dark',
                'dark:focus-within:border-royal-violet',
                'dark:focus-within:shadow-focus-dk',
                'dark:text-royal-violet',
                'dark:text-royal-soft',
                // tab pill
                'left-0', 'left-1/2',
                // form panels
                'hidden', 'flex',
                // hint pills
                'bg-green-100', 'text-green-700', // .pass state
                'bg-royal-DEFAULT/7', 'text-muted-light', // idle light
                'bg-royal-indigo/13', 'text-muted-dark', // idle dark
                // knob animation classes
                'animate-knob-out', 'animate-knob-back',
                // eye icons
                'ico-eye-open', 'ico-eye-closed',
                // social SSO stroke swap
                'stroke-royal-soft',
            ],
        };
    </script>
</head>

<body class="font-sans min-h-screen bg-page-light text-ink-light overflow-x-hidden relative transition-colors duration-400 dark:bg-page-dark dark:text-ink-dark">

    <!-- ════════════ BACKGROUND LAYER ════════════ -->
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

    <!-- ════════════ MAIN ════════════ -->
    <main class="relative z-10 min-h-screen flex items-center justify-center px-4 pt-20 pb-10">
        <div class="w-full max-w-md">

            <!-- ── card ── -->
            <div class="rounded-2xl overflow-hidden
              bg-white/92 dark:bg-card-dark/94
              border border-royal-DEFAULT/13 dark:border-royal-violet/18
              shadow-card dark:shadow-card-dk
              backdrop-blur-md
              animate-card-in opacity-0">

                <!-- top colour stripe -->
                <div class="h-1" style="background:linear-gradient(90deg,#1e3a8a,#1d4ed8,#f59e0b,#1d4ed8,#1e3a8a);"></div>

                <div class="px-7 pt-8 pb-9">
                        <?php
                        if (isset($_SESSION['error_msg'])) {
                            echo  '<p class="error_msg">'.$_SESSION['error_msg'].'</p>';
                        }
                        if (isset($_SESSION['success_msg'])) {
                            echo  '<p class="success_msg">'.$_SESSION['success_msg'].'</p>';
                        } 

                        unset($_SESSION['error_msg']);
                        unset($_SESSION['success_msg']);
                        ?>
                    <!-- heading -->
                    <h1 id="authHeading" class="font-display font-bold text-2xl text-center text-royal-DEFAULT dark:text-royal-soft">
                        Welcome Back
                    </h1>
                    <p id="authSub" class="text-center text-xs mt-1 text-muted-light dark:text-muted-dark">
                        Sign in to continue your learning journey
                    </p>

                    <!-- ── tab switcher ── -->
                    <div class="relative flex mt-6 p-1 rounded-full
                  bg-royal-DEFAULT/6 dark:bg-royal-indigo/10
                  border border-royal-DEFAULT/12 dark:border-royal-indigo/16">

                        <!-- sliding pill (JS moves left-0 ↔ left-1/2) -->
                        <div id="tabPill"
                            class="absolute top-1 bottom-1 w-1/2 rounded-full left-0 transition-all duration-300"
                            style="background:linear-gradient(135deg,#1e3a8a,#1d4ed8);box-shadow:0 2px 8px rgba(30,58,138,0.4);"></div>

                        <button type="button" id="tabLogin" onclick="switchTab('login')"
                            class="relative z-10 flex-1 py-2 text-xs font-semibold rounded-full
                       text-white transition-colors duration-300 cursor-pointer focus:outline-none">
                            Login
                        </button>
                        <button type="button" id="tabSignup" onclick="switchTab('signup')"
                            class="relative z-10 flex-1 py-2 text-xs font-semibold rounded-full
                       text-muted-light dark:text-muted-dark transition-colors duration-300 cursor-pointer focus:outline-none">
                            Sign Up
                        </button>
                    </div>

                    <!-- ════ LOGIN FORM ════ -->
                    <form action="./sign_me_in" method="POST" id="loginForm" class="flex flex-col gap-4 mt-5">
                        <!-- Username -->
                        <div class="flex flex-col">
                            <label class="text-xs font-semibold mb-1.5 text-muted-light dark:text-muted-dark tracking-wide">Username or Email</label>
                            <div class="flex items-center rounded-xl border border-indigo-200 dark:border-indigo-500/28
                      bg-white dark:bg-card-dark/70
                      focus-within:border-royal-mid dark:focus-within:border-royal-violet
                      focus-within:shadow-focus-ring dark:focus-within:shadow-focus-dk
                      transition-all duration-200 overflow-hidden">
                                <!-- icon -->
                                <div class="flex items-center justify-center w-11 shrink-0 text-indigo-300 dark:text-royal-indigo">
                                    <svg class="w-4.5 h-4.5" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12,1a11,11,0,0,0,0,22,1,1,0,0,0,0-2,9,9,0,1,1,9-9v2.857a1.857,1.857,0,0,1-3.714,0V7.714a1,1,0,1,0-2,0v.179A5.234,5.234,0,0,0,12,6.714a5.286,5.286,0,1,0,3.465,9.245A3.847,3.847,0,0,0,23,14.857V12A11.013,11.013,0,0,0,12,1Zm0,14.286A3.286,3.286,0,1,1,15.286,12,3.29,3.29,0,0,1,12,15.286Z"></path>
                                    </svg>
                                </div>
                                <input type="text" placeholder="john@college.edu.in or john_doe"
                                    class="flex-1 bg-transparent border-0 outline-none text-sm py-3 pr-3
                          text-ink-light dark:text-ink-dark
                          placeholder-ghost-light dark:placeholder-ghost-dark" name="user_name" />
                            </div>
                        </div>

                        <!-- Password -->
                        <div class="flex flex-col">
                            <label class="text-xs font-semibold mb-1.5 text-muted-light dark:text-muted-dark tracking-wide">Password</label>
                            <div class="flex items-center rounded-xl border border-indigo-200 dark:border-indigo-500/28
                      bg-white dark:bg-card-dark/70
                      focus-within:border-royal-mid dark:focus-within:border-royal-violet
                      focus-within:shadow-focus-ring dark:focus-within:shadow-focus-dk
                      transition-all duration-200 overflow-hidden">
                                <div class="flex items-center justify-center w-11 shrink-0 text-indigo-300 dark:text-royal-indigo">
                                    <svg class="w-4.5 h-4.5" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                                    </svg>
                                </div>
                                <input type="password" id="loginPass" placeholder="••••••••"
                                    class="flex-1 bg-transparent border-0 outline-none text-sm py-3 pr-1
                          text-ink-light dark:text-ink-dark
                          placeholder-ghost-light dark:placeholder-ghost-dark" name="password"/>
                                <!-- eye toggle -->
                                <button type="button" onclick="toggleVis('loginPass',this)"
                                    class="flex items-center justify-center w-10 h-full text-indigo-300 dark:text-royal-indigo hover:text-royal-mid dark:hover:text-royal-violet transition-colors cursor-pointer">
                                    <svg class="ico-eye-open w-4.5 h-4.5" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                    <svg class="ico-eye-closed w-4.5 h-4.5 hidden" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94" />
                                        <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19" />
                                        <line x1="1" y1="1" x2="23" y2="23" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Remember + Forgot -->
                        <div class="flex items-center justify-between">
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <input type="checkbox" class="w-4 h-4 rounded accent-royal-mid cursor-pointer" />
                                <span class="text-xs text-muted-light dark:text-muted-dark">Remember me</span>
                            </label>
                            <a href="#" class="text-xs font-semibold text-royal-mid dark:text-royal-violet hover:underline transition-colors">Forgot password?</a>
                        </div>

                        <!-- Sign In btn -->
                        <button type="submit"
                            class="w-full py-3 rounded-xl text-white text-sm font-semibold
                       shadow-btn hover:shadow-btn-hov active:shadow-md
                       hover:-translate-y-0.5 active:translate-y-0
                       transition-all duration-200 cursor-pointer focus:outline-none"
                            style="background:linear-gradient(135deg,#1e3a8a 0%,#1d4ed8 55%,#3730a3 100%);">
                            Sign In
                        </button>
                    </form>

                    <!-- ════ SIGNUP FORM ════ -->
                    <form action="./sign_me_up" method="POST" id="signupForm" class="hidden flex-col gap-4 mt-5">

                        <!-- Full Name -->
                        <div class="flex flex-col">
                            <label class="text-xs font-semibold mb-1.5 text-muted-light dark:text-muted-dark tracking-wide">Full Name</label>
                            <div class="flex items-center rounded-xl border border-indigo-200 dark:border-indigo-500/28
                      bg-white dark:bg-card-dark/70
                      focus-within:border-royal-mid dark:focus-within:border-royal-violet
                      focus-within:shadow-focus-ring dark:focus-within:shadow-focus-dk
                      transition-all duration-200 overflow-hidden">
                                <div class="flex items-center justify-center w-11 shrink-0 text-indigo-300 dark:text-royal-indigo">
                                    <svg class="w-4.5 h-4.5" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                        <circle cx="12" cy="8" r="4" />
                                    </svg>
                                </div>
                                <input type="text" placeholder="Ravi Mehta"
                                    class="flex-1 bg-transparent border-0 outline-none text-sm py-3 pr-3
                          text-ink-light dark:text-ink-dark
                          placeholder-ghost-light dark:placeholder-ghost-dark" name="full_name" />
                            </div>
                        </div>

                        <!-- User Name -->
                        <div class="flex flex-col">
                            <label class="text-xs font-semibold mb-1.5 text-muted-light dark:text-muted-dark tracking-wide">User Name</label>
                            <div class="flex items-center rounded-xl border border-indigo-200 dark:border-indigo-500/28
                      bg-white dark:bg-card-dark/70
                      focus-within:border-royal-mid dark:focus-within:border-royal-violet
                      focus-within:shadow-focus-ring dark:focus-within:shadow-focus-dk
                      transition-all duration-200 overflow-hidden">
                                <div class="flex items-center justify-center w-11 shrink-0 text-indigo-300 dark:text-royal-indigo">
                                    <svg class="w-4.5 h-4.5" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12,1a11,11,0,0,0,0,22,1,1,0,0,0,0-2,9,9,0,1,1,9-9v2.857a1.857,1.857,0,0,1-3.714,0V7.714a1,1,0,1,0-2,0v.179A5.234,5.234,0,0,0,12,6.714a5.286,5.286,0,1,0,3.465,9.245A3.847,3.847,0,0,0,23,14.857V12A11.013,11.013,0,0,0,12,1Zm0,14.286A3.286,3.286,0,1,1,15.286,12,3.29,3.29,0,0,1,12,15.286Z"></path>
                                    </svg>
                                </div>
                                <input type="text" placeholder="rm2006"
                                    class="flex-1 bg-transparent border-0 outline-none text-sm py-3 pr-3
                          text-ink-light dark:text-ink-dark
                          placeholder-ghost-light dark:placeholder-ghost-dark" name="user_name" />
                            </div>
                        </div>

                        <!-- College Email -->
                        <div class="flex flex-col">
                            <label class="text-xs font-semibold mb-1.5 text-muted-light dark:text-muted-dark tracking-wide">College Email</label>
                            <div class="flex items-center rounded-xl border border-indigo-200 dark:border-indigo-500/28
                      bg-white dark:bg-card-dark/70
                      focus-within:border-royal-mid dark:focus-within:border-royal-violet
                      focus-within:shadow-focus-ring dark:focus-within:shadow-focus-dk
                      transition-all duration-200 overflow-hidden">
                                <div class="flex items-center justify-center w-11 shrink-0 text-indigo-300 dark:text-royal-indigo">
                                    <svg class="w-4.5 h-4.5" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                                        <polyline points="22,6 12,13 2,6" />
                                    </svg>
                                </div>
                                <input type="email" placeholder="ravi@college.edu.in"
                                    class="flex-1 bg-transparent border-0 outline-none text-sm py-3 pr-3
                          text-ink-light dark:text-ink-dark
                          placeholder-ghost-light dark:placeholder-ghost-dark" name="user_email" />
                            </div>
                        </div>

                        <!-- Phone Number -->
                        <div class="flex flex-col">
                            <label class="text-xs font-semibold mb-1.5 text-muted-light dark:text-muted-dark tracking-wide">Contact</label>
                            <div class="flex items-center rounded-xl border border-indigo-200 dark:border-indigo-500/28
                      bg-white dark:bg-card-dark/70
                      focus-within:border-royal-mid dark:focus-within:border-royal-violet
                      focus-within:shadow-focus-ring dark:focus-within:shadow-focus-dk
                      transition-all duration-200 overflow-hidden">
                                <div class="flex items-center justify-center w-11 shrink-0 text-indigo-300 dark:text-royal-indigo">
                                    <svg class="w-4.5 h-4.5" width="20" height="20" viewBox="0 0 35 35" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M 8.65625 3 C 8.132813 3 7.617188 3.1875 7.1875 3.53125 L 7.125 3.5625 L 7.09375 3.59375 L 3.96875 6.8125 L 4 6.84375 C 3.035156 7.734375 2.738281 9.066406 3.15625 10.21875 C 3.160156 10.226563 3.152344 10.242188 3.15625 10.25 C 4.003906 12.675781 6.171875 17.359375 10.40625 21.59375 C 14.65625 25.84375 19.402344 27.925781 21.75 28.84375 L 21.78125 28.84375 C 22.996094 29.25 24.3125 28.960938 25.25 28.15625 L 28.40625 25 C 29.234375 24.171875 29.234375 22.734375 28.40625 21.90625 L 24.34375 17.84375 L 24.3125 17.78125 C 23.484375 16.953125 22.015625 16.953125 21.1875 17.78125 L 19.1875 19.78125 C 18.464844 19.433594 16.742188 18.542969 15.09375 16.96875 C 13.457031 15.40625 12.621094 13.609375 12.3125 12.90625 L 14.3125 10.90625 C 15.152344 10.066406 15.167969 8.667969 14.28125 7.84375 L 14.3125 7.8125 L 14.21875 7.71875 L 10.21875 3.59375 L 10.1875 3.5625 L 10.125 3.53125 C 9.695313 3.1875 9.179688 3 8.65625 3 Z M 8.65625 5 C 8.730469 5 8.804688 5.035156 8.875 5.09375 L 12.875 9.1875 L 12.96875 9.28125 C 12.960938 9.273438 13.027344 9.378906 12.90625 9.5 L 10.40625 12 L 9.9375 12.4375 L 10.15625 13.0625 C 10.15625 13.0625 11.304688 16.136719 13.71875 18.4375 L 13.9375 18.625 C 16.261719 20.746094 19 21.90625 19 21.90625 L 19.625 22.1875 L 22.59375 19.21875 C 22.765625 19.046875 22.734375 19.046875 22.90625 19.21875 L 27 23.3125 C 27.171875 23.484375 27.171875 23.421875 27 23.59375 L 23.9375 26.65625 C 23.476563 27.050781 22.988281 27.132813 22.40625 26.9375 C 20.140625 26.046875 15.738281 24.113281 11.8125 20.1875 C 7.855469 16.230469 5.789063 11.742188 5.03125 9.5625 C 4.878906 9.15625 4.988281 8.554688 5.34375 8.25 L 5.40625 8.1875 L 8.4375 5.09375 C 8.507813 5.035156 8.582031 5 8.65625 5 Z"></path>
                                    </svg>

                                </div>
                                <input type="text" placeholder="+91 1234567890"
                                    class="flex-1 bg-transparent border-0 outline-none text-sm py-3 pr-3
                          text-ink-light dark:text-ink-dark
                          placeholder-ghost-light dark:placeholder-ghost-dark" name="contact_no" />
                            </div>
                        </div>

                        <!-- Password -->
                        <div class="flex flex-col">
                            <label class="text-xs font-semibold mb-1.5 text-muted-light dark:text-muted-dark tracking-wide">Password</label>
                            <div class="flex items-center rounded-xl border border-indigo-200 dark:border-indigo-500/28
                      bg-white dark:bg-card-dark/70
                      focus-within:border-royal-mid dark:focus-within:border-royal-violet
                      focus-within:shadow-focus-ring dark:focus-within:shadow-focus-dk
                      transition-all duration-200 overflow-hidden">
                                <div class="flex items-center justify-center w-11 shrink-0 text-indigo-300 dark:text-royal-indigo">
                                    <svg class="w-4.5 h-4.5" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                                    </svg>
                                </div>
                                <input type="password" id="signupPass" placeholder="••••••••" oninput="checkHints()"
                                    class="flex-1 bg-transparent border-0 outline-none text-sm py-3 pr-1
                          text-ink-light dark:text-ink-dark
                          placeholder-ghost-light dark:placeholder-ghost-dark" name="user_password"
                                    pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&_])[A-Za-z\d@$!%*?&]{8,}$"
                                    title="Password must contain at least one uppercase, lowercase, digit, special character, and be at least 8 characters." />
                                <button type="button" onclick="toggleVis('signupPass',this)"
                                    class="flex items-center justify-center w-10 h-full text-indigo-300 dark:text-royal-indigo hover:text-royal-mid dark:hover:text-royal-violet transition-colors cursor-pointer">
                                    <svg class="ico-eye-open w-4.5 h-4.5" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                    <svg class="ico-eye-closed w-4.5 h-4.5 hidden" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94" />
                                        <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19" />
                                        <line x1="1" y1="1" x2="23" y2="23" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- hint pills -->
                        <div class="flex gap-1.5 flex-wrap">
                            <span id="hintLen" class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-royal-DEFAULT/7 text-muted-light dark:bg-royal-indigo/13 dark:text-muted-dark transition-colors duration-300">8+ chars</span>
                            <span id="hintUpper" class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-royal-DEFAULT/7 text-muted-light dark:bg-royal-indigo/13 dark:text-muted-dark transition-colors duration-300">Uppercase</span>
                            <span id="hintNum" class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-royal-DEFAULT/7 text-muted-light dark:bg-royal-indigo/13 dark:text-muted-dark transition-colors duration-300">Number</span>
                        </div>

                        <!-- Confirm Password -->
                        <div class="flex flex-col">
                            <label class="text-xs font-semibold mb-1.5 text-muted-light dark:text-muted-dark tracking-wide">Confirm Password</label>
                            <div class="flex items-center rounded-xl border border-indigo-200 dark:border-indigo-500/28
                      bg-white dark:bg-card-dark/70
                      focus-within:border-royal-mid dark:focus-within:border-royal-violet
                      focus-within:shadow-focus-ring dark:focus-within:shadow-focus-dk
                      transition-all duration-200 overflow-hidden">
                                <div class="flex items-center justify-center w-11 shrink-0 text-indigo-300 dark:text-royal-indigo">
                                    <svg class="w-4.5 h-4.5" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                                    </svg>
                                </div>
                                <input type="password" id="signupConfirm" placeholder="••••••••"
                                    class="flex-1 bg-transparent border-0 outline-none text-sm py-3 pr-1
                          text-ink-light dark:text-ink-dark
                          placeholder-ghost-light dark:placeholder-ghost-dark" name="confirm_password"
                                    pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&_])[A-Za-z\d@$!%*?&]{8,}$"
                                    title="Password must contain at least one uppercase, lowercase, digit, special character, and be at least 8 characters." />
                                <button type="button" onclick="toggleVis('signupConfirm',this)"
                                    class="flex items-center justify-center w-10 h-full text-indigo-300 dark:text-royal-indigo hover:text-royal-mid dark:hover:text-royal-violet transition-colors cursor-pointer">
                                    <svg class="ico-eye-open w-4.5 h-4.5" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                    <svg class="ico-eye-closed w-4.5 h-4.5 hidden" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94" />
                                        <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19" />
                                        <line x1="1" y1="1" x2="23" y2="23" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Terms -->
                        <label class="flex items-start gap-2.5 cursor-pointer select-none">
                            <input type="checkbox" class="w-4 h-4 rounded mt-0.5 accent-royal-mid shrink-0 cursor-pointer" name="terms_conditions" />
                            <span class="text-xs text-muted-light dark:text-muted-dark">
                                I agree to the <a href="#" class="font-semibold text-royal-mid dark:text-royal-violet hover:underline">Terms of Service</a>
                                and <a href="#" class="font-semibold text-royal-mid dark:text-royal-violet hover:underline">Privacy Policy</a>
                            </span>
                        </label>

                        <!-- Create Account btn -->
                        <button type="submit"
                            class="w-full py-3 rounded-xl text-white text-sm font-semibold
                       shadow-btn hover:shadow-btn-hov active:shadow-md
                       hover:-translate-y-0.5 active:translate-y-0
                       transition-all duration-200 cursor-pointer focus:outline-none"
                            style="background:linear-gradient(135deg,#1e3a8a 0%,#1d4ed8 55%,#3730a3 100%);">
                            Create Account
                        </button>
                    </form>

                    <!-- ── or continue with ── -->
                    <div class="flex items-center gap-3 mt-5">
                        <div class="flex-1 h-px bg-royal-DEFAULT/13 dark:bg-royal-violet/20"></div>
                        <span class="text-xs text-ghost-light dark:text-ghost-dark">or continue with</span>
                        <div class="flex-1 h-px bg-royal-DEFAULT/13 dark:bg-royal-violet/20"></div>
                    </div>

                    <!-- ── social ── -->
                    <div class="flex gap-2.5 mt-3.5">
                        <!-- Google -->
                        <button type="button" id="google-login-btn"
                            class="flex-1 flex items-center justify-center gap-2 py-2.5 rounded-xl
                       border border-royal-DEFAULT/14 dark:border-royal-indigo/22
                       bg-royal-DEFAULT/5 dark:bg-royal-indigo/8
                       text-xs font-semibold text-slate-600 dark:text-slate-300
                       hover:shadow-md transition-all duration-200 cursor-pointer">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24">
                                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
                                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                            </svg>
                            Google
                        </button>
                        <script type="module" src="../assets/js/google_login.js.php?v=3"></script>
                        <!-- College SSO -->
                        <!-- <button type="button" id="ssoBtn"
                            class="flex-1 flex items-center justify-center gap-2 py-2.5 rounded-xl
                       border border-royal-DEFAULT/14 dark:border-royal-indigo/22
                       bg-royal-DEFAULT/5 dark:bg-royal-indigo/8
                       text-xs font-semibold text-slate-600 dark:text-slate-300
                       hover:shadow-md transition-all duration-200 cursor-pointer">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="#1e3a8a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                                <polyline points="9 22 9 12 15 12 15 22" />
                            </svg>
                            College SSO
                        </button> -->
                    </div>

                    <!-- ── switch link ── -->
                    <p id="switchText" class="text-center text-xs mt-5 text-muted-light dark:text-muted-dark">
                        Don't have an account?
                        <a href="#" class="font-semibold text-royal-mid dark:text-royal-violet hover:underline" onclick="switchTab('signup');">Sign Up</a>
                    </p>
                </div><!-- end px-7 -->
            </div><!-- end card -->

            <!-- footer -->
            <p class="text-center text-xs mt-5 text-ghost-light dark:text-ghost-dark">© 2026 SkillSwap – College Micro-Mentoring Platform</p>
        </div><!-- end max-w -->
    </main>

    <!-- ════════════ JS ════════════ -->
    <script>
        /* ─── dark mode ─── */
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

        /* ─── tab switch ─── */
        function switchTab(tab) {
            const loginForm = document.getElementById('loginForm');
            const signupForm = document.getElementById('signupForm');
            const btnL = document.getElementById('tabLogin');
            const btnS = document.getElementById('tabSignup');
            const pill = document.getElementById('tabPill');
            const head = document.getElementById('authHeading');
            const sub = document.getElementById('authSub');
            const sw = document.getElementById('switchText');
            const dark = document.documentElement.classList.contains('dark');
            const linkCls = dark ? 'text-royal-violet' : 'text-royal-mid';

            if (tab === 'login') {
                // show login, hide signup
                loginForm.classList.remove('hidden');
                loginForm.classList.add('flex');
                signupForm.classList.add('hidden');
                signupForm.classList.remove('flex');
                loginForm.querySelectorAll('input').forEach(i => i.disabled = false);
                signupForm.querySelectorAll('input').forEach(i => i.disabled = true);
                // animate panel
                loginForm.classList.remove('animate-panel-in');
                void loginForm.offsetWidth;
                loginForm.classList.add('animate-panel-in');
                // tab colours
                btnL.classList.remove('text-muted-light', 'text-muted-dark');
                btnL.classList.add('text-white');
                btnS.classList.remove('text-white');
                btnS.classList.add(dark ? 'text-muted-dark' : 'text-muted-light');
                // pill slide
                pill.classList.remove('left-1/2');
                pill.classList.add('left-0');
                // text
                head.textContent = 'Welcome Back';
                sub.textContent = 'Sign in to continue your learning journey';
                sw.innerHTML = `Don't have an account? <a href="#" class="font-semibold ${linkCls} hover:underline" onclick="switchTab('signup');">Sign Up</a>`;
            } else {
                signupForm.classList.remove('hidden');
                signupForm.classList.add('flex');
                loginForm.classList.add('hidden');
                loginForm.classList.remove('flex');
                signupForm.classList.remove('animate-panel-in');
                void signupForm.offsetWidth;
                signupForm.classList.add('animate-panel-in');
                loginForm.querySelectorAll('input').forEach(i => i.disabled = true);
                signupForm.querySelectorAll('input').forEach(i => i.disabled = false);
                btnS.classList.remove('text-muted-light', 'text-muted-dark');
                btnS.classList.add('text-white');
                btnL.classList.remove('text-white');
                btnL.classList.add(dark ? 'text-muted-dark' : 'text-muted-light');
                pill.classList.remove('left-0');
                pill.classList.add('left-1/2');
                head.textContent = 'Create Account';
                sub.textContent = 'Join SkillSwap and start learning & teaching';
                sw.innerHTML = `Already have an account? <a href="#" class="font-semibold ${linkCls} hover:underline" onclick="switchTab('login');">Sign In</a>`;
            }
        }

        /* ─── eye toggle ─── */
        function toggleVis(id, btn) {
            const inp = document.getElementById(id);
            const open = btn.querySelector('.ico-eye-open');
            const close = btn.querySelector('.ico-eye-closed');
            if (inp.type === 'password') {
                inp.type = 'text';
                open.classList.add('hidden');
                close.classList.remove('hidden');
            } else {
                inp.type = 'password';
                open.classList.remove('hidden');
                close.classList.add('hidden');
            }
        }

        /* ─── password hints ─── */
        function checkHints() {
            const v = document.getElementById('signupPass').value;
            const dark = document.documentElement.classList.contains('dark');
            setH('hintLen', v.length >= 8, dark);
            setH('hintUpper', /[A-Z]/.test(v), dark);
            setH('hintNum', /[0-9]/.test(v), dark);
        }

        function setH(id, ok, dark) {
            const el = document.getElementById(id);
            // remove all state classes first
            el.classList.remove('bg-green-100', 'text-green-700',
                'bg-royal-DEFAULT/7', 'text-muted-light',
                'bg-royal-indigo/13', 'text-muted-dark');
            if (ok) {
                el.classList.add('bg-green-100', 'text-green-700');
            } else {
                if (dark) {
                    el.classList.add('bg-royal-indigo/13', 'text-muted-dark');
                } else {
                    el.classList.add('bg-royal-DEFAULT/7', 'text-muted-light');
                }
            }
        }
    </script>
</body>

</html>