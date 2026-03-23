<?php
/**
 * user/submit_assignment.php
 * POST handler — scores the MCQ exam, updates student rating, redirects to results.
 */

date_default_timezone_set('Asia/Kolkata');
session_start();
require_once __DIR__ . '/../includes/scripts/connection.php';

// ── Auth ──────────────────────────────────────────────────────────────────────
if (empty($_SESSION['isloggedin']) || $_SESSION['isloggedin'] !== true || empty($_SESSION['login_token'])) {
    session_unset(); session_destroy();
    header('Location: ../Auth/sign_in.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: dashboard.php'); exit; }

$student_id    = (int) $_SESSION['user_id'];
$assignment_id = (int) ($_POST['assignment_id'] ?? 0);
$meeting_id    = trim($_POST['meeting_id'] ?? '');

if (!$assignment_id || !$meeting_id) { header('Location: dashboard.php'); exit; }

// ── Fetch assignment ──────────────────────────────────────────────────────────
// Note: mcq_assignments has a unique key on meeting_id, so we look up by
// assignment_id first, then verify the student matches.
$stmt = $conn->prepare("SELECT * FROM mcq_assignments WHERE assignment_id = ?");
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Verify this assignment belongs to this student
if (!$assignment || (int)$assignment['student_id'] !== $student_id) {
    header('Location: dashboard.php'); exit;
}

// ── Guard: already submitted ──────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT result_id FROM mcq_results WHERE assignment_id = ? AND student_id = ?");
$stmt->bind_param("ii", $assignment_id, $student_id);
$stmt->execute();
if ($stmt->get_result()->fetch_assoc()) {
    $stmt->close();
    header("Location: assignment.php?meeting_id=" . urlencode($meeting_id)); exit;
}
$stmt->close();

// ── Score answers ─────────────────────────────────────────────────────────────
$questions   = json_decode($assignment['questions_json'], true) ?? [];
$raw_answers = $_POST['answers'] ?? [];

$normalized = [];
foreach ($questions as $qi => $q) {
    $chosen = [];
    if (isset($raw_answers[$qi]) && is_array($raw_answers[$qi])) {
        foreach ($raw_answers[$qi] as $v) { $chosen[] = (int) $v; }
        sort($chosen);
    }
    $normalized[$qi] = $chosen;
}

$score = 0;
foreach ($questions as $qi => $q) {
    $correct = $q['correct'];
    sort($correct);
    $given = $normalized[$qi];
    if ($correct === $given) $score++;
}

$answers_json = json_encode($normalized);

// ── Save result ───────────────────────────────────────────────────────────────
$stmt = $conn->prepare("INSERT INTO mcq_results (assignment_id, student_id, answers_json, score, submitted_at) VALUES (?, ?, ?, ?, NOW())");
$stmt->bind_param("iisi", $assignment_id, $student_id, $answers_json, $score);
if (!$stmt->execute()) {
    // If insert failed due to duplicate (already submitted), still redirect gracefully
    $stmt->close();
    header("Location: assignment.php?meeting_id=" . urlencode($meeting_id)); exit;
}
$stmt->close();

// ── Update student rating ─────────────────────────────────────────────────────
// score 9-10 → +2 | 7-8 → +1 | 5-6 → 0 | 3-4 → -1 | 0-2 → -2
// rating clamped between 0 and 10
$delta = 0;
if      ($score >= 9) $delta =  2;
elseif  ($score >= 7) $delta =  1;
elseif  ($score >= 5) $delta =  0;
elseif  ($score >= 3) $delta = -1;
else                  $delta = -2;

if ($delta !== 0) {
    $stmt = $conn->prepare("UPDATE user_master SET rating = GREATEST(0, LEAST(10, rating + ?)) WHERE user_id = ?");
    $stmt->bind_param("ii", $delta, $student_id);
    $stmt->execute();
    $stmt->close();
}

// ── Notify student of score ───────────────────────────────────────────────────
$total_q = count($questions);
$pct     = round($score / $total_q * 100);
$msg     = "Assignment result: {$score}/{$total_q} ({$pct}%) on \"{$assignment['topic']}\".";
if      ($delta > 0) $msg .= " Your rating went up! ⭐";
elseif  ($delta < 0) $msg .= " Keep practising to improve your rating.";

$stmt = $conn->prepare("INSERT INTO notifications (user_id, msg, time, msg_read) VALUES (?, ?, NOW(), 'no')");
$stmt->bind_param("is", $student_id, $msg);
$stmt->execute();
$stmt->close();

// ── Redirect to results ───────────────────────────────────────────────────────
header("Location: assignment.php?meeting_id=" . urlencode($meeting_id));
exit;