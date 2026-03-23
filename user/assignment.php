<?php
/**
 * user/assignment.php
 *
 * Shown to the student after a meeting is completed.
 * URL: assignment.php?meeting_id=MTG-XXXX
 *
 * Flow:
 *  1. Verify the meeting belongs to this student & is completed
 *  2. Check if an mcq_assignments row already exists (cached) — if not, call Groq API to generate 10 MCQs
 *  3. If student already submitted → show results page
 *  4. Otherwise → show the exam UI
 */

date_default_timezone_set('Asia/Kolkata');
session_start();
include_once __DIR__ . '/validation.php';
require_once __DIR__ . '/../includes/scripts/connection.php';

// ── Auth ──────────────────────────────────────────────────────────────────────
if (empty($_SESSION['isloggedin']) || $_SESSION['isloggedin'] !== true || empty($_SESSION['login_token'])) {
    session_unset(); session_destroy();
    header('Location: ../Auth/sign_in.php'); exit;
}

$student_id = (int) $_SESSION['user_id'];
$meeting_id = trim($_GET['meeting_id'] ?? '');

if (!$meeting_id) {
    header('Location: dashboard.php'); exit;
}

// ── Verify meeting belongs to this student and is completed ───────────────────
$stmt = $conn->prepare("
    SELECT m.meeting_id, m.topic, m.status, m.teacher_id,
           sm.sub_name,
           u.full_name AS teacher_name
    FROM meetings m
    JOIN subject_master sm ON sm.sub_id  = m.sub_id
    JOIN user_master    u  ON u.user_id  = m.teacher_id
    WHERE m.meeting_id = ? AND m.student_id = ?
");
$stmt->bind_param("si", $meeting_id, $student_id);
$stmt->execute();
$meeting = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$meeting) {
    header('Location: dashboard.php'); exit;
}
if ($meeting['status'] !== 'completed') {
    // Meeting not done yet — redirect with message
    header('Location: dashboard.php?msg=meeting_not_done'); exit;
}

// ── Check for existing cached assignment for this meeting ────────────────────
$stmt = $conn->prepare("SELECT * FROM mcq_assignments WHERE meeting_id = ? AND student_id = ?");
$stmt->bind_param("si", $meeting_id, $student_id);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── Only call Gemini if no cached assignment exists yet ───────────────────────
if (!$assignment) {
    $topic    = $meeting['topic'] ?: $meeting['sub_name'];
    $sub_name = $meeting['sub_name'];

    $prompt = <<<PROMPT
You are an expert teacher creating a post-lecture assessment.

The student just completed a 1-on-1 tutoring session on the subject "{$sub_name}", specifically about the topic: "{$topic}".

Generate exactly 10 multiple-choice questions to test the student's understanding. Some questions may have MORE THAN ONE correct answer (multi-select). Make them varied in difficulty.

Return ONLY a valid JSON array with exactly this structure — no markdown, no explanation, no extra text:
[
  {
    "question": "Question text here?",
    "options": ["Option A", "Option B", "Option C", "Option D"],
    "correct": [0],
    "explanation": "Brief explanation of the correct answer(s)."
  }
]

Rules:
- "options" always has exactly 4 items
- "correct" is an array of zero-based indexes of the correct option(s) — can be [0], [1,2], [0,3], etc.
- At least 3 of the 10 questions should be multi-select (more than one correct answer)
- Questions must be specific to "{$topic}" in the context of "{$sub_name}"
- Do not repeat questions
PROMPT;

    // ════════════════════════════════════════════════════════════
    // ▼▼▼  PASTE YOUR GROQ API KEY HERE  ▼▼▼
    $groq_api_key = 'API_KEY_HERE';
    // ▲▲▲  Get a free key at: https://console.groq.com/keys  ▲▲▲
    // ════════════════════════════════════════════════════════════

    $groq_url = "https://api.groq.com/openai/v1/chat/completions";

    $payload = json_encode([
        'model'       => 'llama-3.3-70b-versatile',
        'temperature' => 0.7,
        'max_tokens'  => 2048,
        'messages'    => [
            [
                'role'    => 'user',
                'content' => $prompt,
            ]
        ],
    ]);

    $ch = curl_init($groq_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $groq_api_key,
        ],
        CURLOPT_TIMEOUT        => 30,
    ]);
    $raw_response = curl_exec($ch);
    $curl_err     = curl_error($ch);
    $http_code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $questions_json = null;
    $api_err_msg = null;

    if ($curl_err) {
        $api_err_msg = "cURL error: {$curl_err}";
    } elseif (!$raw_response) {
        $api_err_msg = "Empty response from Groq (HTTP {$http_code})";
    } else {
        $resp = json_decode($raw_response, true);

        // Capture API-level errors (bad key, quota, etc.)
        if (isset($resp['error'])) {
            $api_err_msg = "Groq API error: " . $resp['error']['message'];
        } else {
            $text = $resp['choices'][0]['message']['content'] ?? '';
            // Strip markdown fences if present
            $text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
            $text = preg_replace('/\s*```$/i', '', $text);
            $parsed = json_decode(trim($text), true);

            if (is_array($parsed) && count($parsed) === 10) {
                $questions_json = json_encode($parsed);
            } else {
                $count = is_array($parsed) ? count($parsed) : 0;
                $api_err_msg = "Groq returned {$count} questions (expected 10). Raw: " . substr($text, 0, 300);
            }
        }
    }

    // ── If Groq failed, show a visible error page (don't save to DB) ───────
    if (!$questions_json) {
        // Show a clear error screen instead of fake questions
        ?><!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Assignment Error</title>
            <script src="https://cdn.tailwindcss.com"></script>
        </head>
        <body class="bg-slate-900 text-white flex items-center justify-center min-h-screen p-6">
            <div class="max-w-xl w-full bg-slate-800 rounded-2xl p-8 border border-red-500/40">
                <h1 class="text-2xl font-bold text-red-400 mb-4">⚠️ Failed to Load Questions</h1>
                <p class="text-slate-300 mb-4">The Groq API could not generate questions. See the error below:</p>
                <div class="bg-slate-900 rounded-xl p-4 text-sm font-mono text-amber-300 break-all mb-6">
                    <?= htmlspecialchars($api_err_msg ?? 'Unknown error') ?>
                </div>
                <p class="text-slate-400 text-sm mb-2">Common fixes:</p>
                <ul class="text-slate-300 text-sm space-y-1 list-disc list-inside mb-6">
                    <li>Make sure <code class="text-amber-300">$groq_api_key</code> in <code>assignment.php</code> is your real key</li>
                    <li>Get a free key at <a href="https://aistudio.google.com/app/apikey" target="_blank" class="text-blue-400 underline">aistudio.google.com</a></li>
                    <li>Check your key has not exceeded free quota</li>
                </ul>
                <a href="assignment.php?meeting_id=<?= urlencode($meeting_id) ?>"
                   class="inline-block px-6 py-2.5 bg-blue-600 hover:bg-blue-700 rounded-xl font-semibold transition-colors">
                    🔄 Retry
                </a>
            </div>
        </body>
        </html><?php
        exit;
    } else {
        // ── Save real Save questions to DB (once per meeting) ───────────────
        $stmt = $conn->prepare("
            INSERT INTO mcq_assignments (meeting_id, student_id, topic, questions_json)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("siss", $meeting_id, $student_id, $topic, $questions_json);
        $stmt->execute();
        $assignment_id = $stmt->insert_id;
        $stmt->close();

        $assignment = [
            'assignment_id'  => $assignment_id,
            'topic'          => $topic,
            'questions_json' => $questions_json,
        ];
    }
}

$questions     = json_decode($assignment['questions_json'], true) ?? [];
$assignment_id = (int) $assignment['assignment_id'];

// ── Check if already submitted ────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM mcq_results WHERE student_id = ? ORDER BY submitted_at DESC LIMIT 1");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

// If submitted, reload the saved questions from DB for the review page
$already_submitted = false;
$submitted_answers = [];
$score             = null;

if ($result) {
    // Check if this result belongs to a meeting assignment for this meeting
    $stmt = $conn->prepare("
        SELECT a.* FROM mcq_assignments a
        JOIN mcq_results r ON r.assignment_id = a.assignment_id
        WHERE r.result_id = ? AND a.meeting_id = ? AND a.student_id = ?
    ");
    $stmt->bind_param("isi", $result['result_id'], $meeting_id, $student_id);
    $stmt->execute();
    $saved_assignment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($saved_assignment) {
        $already_submitted = true;
        $submitted_answers = json_decode($result['answers_json'], true) ?? [];
        $score             = (int) $result['score'];
        // Use saved questions for review
        $questions         = json_decode($saved_assignment['questions_json'], true) ?? [];
        $assignment        = $saved_assignment;
    }
}

// ── Student name ──────────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT full_name FROM user_master WHERE user_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student_name = $stmt->get_result()->fetch_assoc()['full_name'];
$stmt->close();

$name_parts = explode(' ', trim($student_name));
$first_name = $name_parts[0];
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post-Session Assignment – SkillSwap</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/styles/user/common.css">
    <script src="../assets/js/tailwind.js.php" defer></script>
    <style>
        body { font-family: 'DM Sans', sans-serif; }

        /* Option cards */
        .opt-card {
            display: flex;
            align-items: flex-start;
            gap: .75rem;
            padding: .85rem 1rem;
            border-radius: .75rem;
            border: 2px solid #e2e8f0;
            cursor: pointer;
            transition: border-color .15s, background .15s, transform .1s;
            user-select: none;
        }
        .opt-card:hover { border-color: #3b82f6; background: #eff6ff; transform: translateX(2px); }
        .opt-card.selected { border-color: #3b82f6; background: #eff6ff; }
        .dark .opt-card { border-color: #334155; }
        .dark .opt-card:hover, .dark .opt-card.selected { border-color: #60a5fa; background: rgba(59,130,246,.12); }

        /* Review mode */
        .opt-card.review-correct { border-color: #22c55e !important; background: #f0fdf4 !important; }
        .opt-card.review-wrong   { border-color: #ef4444 !important; background: #fef2f2 !important; }
        .dark .opt-card.review-correct { background: rgba(34,197,94,.1) !important; }
        .dark .opt-card.review-wrong   { background: rgba(239,68,68,.1) !important; }

        /* Checkbox custom */
        .opt-checkbox {
            width: 20px; height: 20px;
            border: 2px solid #cbd5e1;
            border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; margin-top: 1px;
            transition: background .15s, border-color .15s;
        }
        .opt-card.selected .opt-checkbox,
        .opt-card.review-correct .opt-checkbox {
            background: #3b82f6; border-color: #3b82f6;
        }
        .opt-card.review-wrong .opt-checkbox { background: #ef4444; border-color: #ef4444; }

        /* Progress bar */
        @keyframes prog { from{width:0} }
        .prog-bar { animation: prog .6s ease both; }

        /* Score ring */
        .score-ring {
            width: 140px; height: 140px;
            border-radius: 50%;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            font-family: 'Playfair Display', serif;
        }

        /* Fade in */
        @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
        .fade-up { animation: fadeUp .4s ease both; }

        /* Explanation box */
        .explanation-box {
            margin-top: .6rem;
            padding: .65rem .9rem;
            background: #f8fafc;
            border-left: 3px solid #3b82f6;
            border-radius: 0 .5rem .5rem 0;
            font-size: .8rem;
            color: #475569;
        }
        .dark .explanation-box { background: #1e293b; color: #94a3b8; }

        /* Question card */
        .q-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .dark .q-card { background: #1e293b; border-color: #334155; }

        /* Timer */
        #timer.urgent { color: #ef4444; animation: pulse 1s infinite; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.5} }
    </style>
</head>
<body class="bg-gray-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 overflow-x-hidden transition-colors duration-300">

    <div id="mobileOverlay" onclick="closeMobileSidebar()"
         class="fixed inset-0 bg-black/40 z-40 hidden lg:hidden"></div>

    <?php include_once __DIR__ . '/../animated-bg.php'; ?>
    <?php include_once __DIR__ . '/navbar.php'; ?>

    <main class="lg:ml-64 min-h-screen relative z-10">

        <!-- Top bar -->
        <header class="sticky top-0 z-40 bg-white/80 dark:bg-slate-900/80 backdrop-blur-xl border-b border-slate-200 dark:border-slate-700 transition-colors">
            <div class="flex items-center justify-between px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center gap-3">
                    <button onclick="openMobileSidebar()" class="lg:hidden p-2 rounded-xl bg-blue-50 dark:bg-blue-900/20 text-blue-800 dark:text-blue-300">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
                        </svg>
                    </button>
                    <div>
                        <h1 class="font-bold text-xl sm:text-2xl text-blue-900 dark:text-blue-200" style="font-family:'Playfair Display',serif;">
                            <?= $already_submitted ? 'Assignment Results' : 'Post-Session Assignment' ?>
                        </h1>
                        <p class="text-xs text-slate-500 dark:text-slate-400 hidden sm:block">
                            <?= htmlspecialchars($meeting['sub_name']) ?> &mdash;
                            <?= htmlspecialchars($assignment['topic']) ?>
                        </p>
                    </div>
                </div>

                <?php if (!$already_submitted): ?>
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700">
                        <svg class="w-4 h-4 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                        </svg>
                        <span id="timer" class="text-sm font-bold text-amber-700 dark:text-amber-400">20:00</span>
                    </div>
                    <span class="text-xs text-slate-400" id="progressLabel">0 / <?= count($questions) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Progress bar -->
            <?php if (!$already_submitted): ?>
            <div class="h-1 bg-slate-100 dark:bg-slate-800">
                <div id="progressBar" class="prog-bar h-full rounded-full bg-gradient-to-r from-blue-500 to-blue-700 transition-all duration-300" style="width:0%"></div>
            </div>
            <?php endif; ?>
        </header>

        <div class="p-4 sm:p-6 lg:p-8 max-w-3xl mx-auto">

            <?php if ($already_submitted): ?>
            <!-- ══ RESULTS VIEW ══ -->
            <?php
                $pct    = round($score / count($questions) * 100);
                $grade  = $pct >= 90 ? ['A+','Exceptional! 🏆','from-emerald-400 to-green-600'] :
                         ($pct >= 75 ? ['A', 'Great work! 🎉',  'from-blue-400 to-blue-600'] :
                         ($pct >= 60 ? ['B', 'Good effort! 👍',  'from-amber-400 to-amber-600'] :
                         ($pct >= 40 ? ['C', 'Keep practising 💪','from-orange-400 to-orange-600'] :
                                       ['D', 'Review needed 📚',  'from-red-400 to-red-600'])));
            ?>
            <div class="fade-up">
                <!-- Score card -->
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6 sm:p-8 mb-6 text-center">
                    <div class="flex flex-col sm:flex-row items-center justify-center gap-6">
                        <div class="score-ring bg-gradient-to-br <?= $grade[2] ?> shadow-lg">
                            <span class="text-4xl font-bold text-white"><?= $score ?></span>
                            <span class="text-sm text-white/80">out of <?= count($questions) ?></span>
                        </div>
                        <div class="text-left">
                            <p class="text-4xl font-bold text-blue-900 dark:text-blue-200 mb-1"><?= $pct ?>%</p>
                            <p class="text-lg font-semibold text-slate-600 dark:text-slate-300 mb-1">Grade: <?= $grade[0] ?></p>
                            <p class="text-slate-500 dark:text-slate-400 text-sm"><?= $grade[1] ?></p>
                            <p class="text-xs text-slate-400 mt-2">
                                Topic: <span class="font-semibold text-slate-600 dark:text-slate-300"><?= htmlspecialchars($assignment['topic']) ?></span>
                            </p>
                            <p class="text-xs text-slate-400">
                                With: <span class="font-semibold text-slate-600 dark:text-slate-300"><?= htmlspecialchars($meeting['teacher_name']) ?></span>
                            </p>
                        </div>
                    </div>

                    <!-- Mini stat bar -->
                    <div class="mt-6 h-3 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
                        <div class="prog-bar h-full rounded-full bg-gradient-to-r <?= $grade[2] ?>"
                             style="width:<?= $pct ?>%"></div>
                    </div>

                    <div class="mt-4 flex justify-center">
                        <a href="dashboard.php" class="inline-flex items-center gap-2 px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold transition-colors">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
                            </svg>
                            Back to Dashboard
                        </a>
                    </div>
                </div>

                <!-- Answer Review -->
                <h2 class="text-lg font-bold text-blue-900 dark:text-blue-200 mb-4" style="font-family:'Playfair Display',serif;">Answer Review</h2>

                <?php foreach ($questions as $qi => $q): ?>
                <?php
                    $user_ans     = $submitted_answers[$qi] ?? [];
                    $correct_ans  = $q['correct'];
                    $is_correct   = (count(array_diff($user_ans, $correct_ans)) === 0 &&
                                     count(array_diff($correct_ans, $user_ans)) === 0);
                    $multi        = count($correct_ans) > 1;
                ?>
                <div class="q-card fade-up" style="animation-delay:<?= $qi * 0.04 ?>s">
                    <div class="flex items-start gap-3 mb-4">
                        <span class="shrink-0 w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold <?= $is_correct ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400' : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400' ?>">
                            <?= $is_correct ? '✓' : '✗' ?>
                        </span>
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                Q<?= $qi + 1 ?>. <?= htmlspecialchars($q['question']) ?>
                                <?php if ($multi): ?>
                                    <span class="ml-1 text-[10px] font-semibold bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-400 px-1.5 py-0.5 rounded-full">Multi-select</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <?php foreach ($q['options'] as $oi => $opt): ?>
                        <?php
                            $user_picked    = in_array($oi, $user_ans);
                            $is_correct_opt = in_array($oi, $correct_ans);
                            $card_class = '';
                            if ($user_picked && $is_correct_opt) $card_class = 'review-correct';
                            elseif ($user_picked && !$is_correct_opt) $card_class = 'review-wrong';
                            elseif (!$user_picked && $is_correct_opt) $card_class = 'review-correct opacity-60';
                        ?>
                        <div class="opt-card <?= $card_class ?>" style="cursor:default;">
                            <div class="opt-checkbox">
                                <?php if ($user_picked || $is_correct_opt): ?>
                                    <svg class="w-3 h-3 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                <?php endif; ?>
                            </div>
                            <span class="text-sm text-slate-700 dark:text-slate-200"><?= htmlspecialchars($opt) ?></span>
                            <?php if ($is_correct_opt): ?>
                                <span class="ml-auto text-xs font-semibold text-emerald-600 dark:text-emerald-400 shrink-0">✓ Correct</span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!empty($q['explanation'])): ?>
                        <div class="explanation-box">
                            💡 <?= htmlspecialchars($q['explanation']) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <?php else: ?>
            <!-- ══ EXAM VIEW ══ -->
            <div class="mb-6 fade-up">
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-5 flex items-start gap-4">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background:linear-gradient(135deg,#3b82f6,#1e3a8a);">
                        <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="font-bold text-slate-800 dark:text-slate-100">Instructions</h2>
                        <ul class="mt-1 text-xs text-slate-500 dark:text-slate-400 space-y-0.5">
                            <li>• 10 questions based on your session topic: <strong class="text-slate-700 dark:text-slate-200"><?= htmlspecialchars($assignment['topic']) ?></strong></li>
                            <li>• Some questions have <strong>multiple correct answers</strong> — select all that apply</li>
                            <li>• You have <strong>20 minutes</strong> — the form auto-submits when time runs out</li>
                            <li>• You can only attempt this assignment <strong>once</strong></li>
                            <li>• Your score will update your rating on the platform</li>
                        </ul>
                    </div>
                </div>
            </div>

            <form id="examForm" method="POST" action="submit_assignment.php">
                <input type="hidden" name="assignment_id" value="<?= $assignment_id ?>">
                <input type="hidden" name="meeting_id"    value="<?= htmlspecialchars($meeting_id) ?>">

                <?php foreach ($questions as $qi => $q): ?>
                <?php $multi = count($q['correct']) > 1; ?>
                <div class="q-card fade-up" style="animation-delay:<?= $qi * 0.05 ?>s" id="qcard-<?= $qi ?>">
                    <p class="text-sm font-bold text-slate-500 dark:text-slate-400 mb-1">
                        Question <?= $qi + 1 ?> of <?= count($questions) ?>
                        <?php if ($multi): ?>
                            <span class="ml-2 text-[10px] font-semibold bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-400 px-2 py-0.5 rounded-full">Select all correct answers</span>
                        <?php else: ?>
                            <span class="ml-2 text-[10px] font-semibold bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 px-2 py-0.5 rounded-full">Single answer</span>
                        <?php endif; ?>
                    </p>
                    <p class="text-base font-semibold text-slate-800 dark:text-slate-100 mb-4">
                        <?= htmlspecialchars($q['question']) ?>
                    </p>

                    <div class="space-y-2" id="opts-<?= $qi ?>">
                        <?php foreach ($q['options'] as $oi => $opt): ?>
                        <label class="opt-card" id="opt-<?= $qi ?>-<?= $oi ?>"
                               onclick="toggleOption(event, <?= $qi ?>, <?= $oi ?>, <?= $multi ? 'true' : 'false' ?>)">
                            <div class="opt-checkbox" id="chk-<?= $qi ?>-<?= $oi ?>"></div>
                            <span class="text-sm text-slate-700 dark:text-slate-200 select-none"><?= htmlspecialchars($opt) ?></span>
                            <!-- Hidden inputs submitted with form -->
                            <input type="checkbox" name="answers[<?= $qi ?>][]" value="<?= $oi ?>"
                                   id="inp-<?= $qi ?>-<?= $oi ?>"
                                   class="hidden exam-input"
                                   data-q="<?= $qi ?>">
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="sticky bottom-4 mt-2">
                    <button type="button" onclick="confirmSubmit()"
                            class="w-full py-3.5 rounded-2xl bg-gradient-to-r from-blue-600 to-blue-800 hover:from-blue-700 hover:to-blue-900 text-white font-bold text-base shadow-lg shadow-blue-500/20 transition-all">
                        Submit Assignment →
                    </button>
                </div>
            </form>
            <?php endif; ?>

        </div>
    </main>

    <script src="../assets/js/darkmodeToggle.js.php" defer></script>
    <script src="../assets/js/dashboard.js.php" defer></script>

    <?php if (!$already_submitted): ?>
    <script>
    // ── Selected answers state ───────────────────────────────────────────────
    const selected = {}; // {qIndex: Set of option indexes}
    const totalQ   = <?= count($questions) ?>;

    function toggleOption(e, qi, oi, multi) {
        e.preventDefault();
        if (!selected[qi]) selected[qi] = new Set();

        if (!multi) {
            // Single select: clear others
            selected[qi].forEach(prev => {
                document.getElementById(`opt-${qi}-${prev}`)?.classList.remove('selected');
                const inp = document.getElementById(`inp-${qi}-${prev}`);
                if (inp) inp.checked = false;
            });
            selected[qi].clear();
        }

        const card  = document.getElementById(`opt-${qi}-${oi}`);
        const inp   = document.getElementById(`inp-${qi}-${oi}`);

        if (selected[qi].has(oi)) {
            selected[qi].delete(oi);
            card?.classList.remove('selected');
            if (inp) inp.checked = false;
        } else {
            selected[qi].add(oi);
            card?.classList.add('selected');
            if (inp) inp.checked = true;
        }

        updateProgress();
    }

    function updateProgress() {
        const answered = Object.values(selected).filter(s => s.size > 0).length;
        const pct = Math.round(answered / totalQ * 100);
        document.getElementById('progressBar').style.width = pct + '%';
        document.getElementById('progressLabel').textContent = answered + ' / ' + totalQ;
    }

    // ── Timer (20 min) ───────────────────────────────────────────────────────
    let _submitting = false;
    let seconds = 20 * 60;
    const timerEl = document.getElementById('timer');

    const timerInterval = setInterval(() => {
        seconds--;
        const m = String(Math.floor(seconds / 60)).padStart(2, '0');
        const s = String(seconds % 60).padStart(2, '0');
        timerEl.textContent = `${m}:${s}`;
        if (seconds <= 60) timerEl.classList.add('urgent');
        if (seconds <= 0) {
            _submitting = true;
            clearInterval(timerInterval);
            document.getElementById('examForm').submit();
        }
    }, 1000);

    // ── Submit confirmation ──────────────────────────────────────────────────
    function confirmSubmit() {
        const answered = Object.values(selected).filter(s => s.size > 0).length;
        const unanswered = totalQ - answered;
        if (unanswered > 0) {
            if (!confirm(`You have ${unanswered} unanswered question(s). Submit anyway?`)) return;
        }
        _submitting = true;
        clearInterval(timerInterval);
        document.getElementById('examForm').submit();
    }

    // ── Warn before leaving ──────────────────────────────────────────────────
    window.addEventListener('beforeunload', e => {
        if (_submitting) return;
        e.preventDefault();
        e.returnValue = '';
    });
    </script>
    <?php endif; ?>

</body>
</html>