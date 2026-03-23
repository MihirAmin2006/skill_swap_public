<?php
// session_start();

$uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$segments = explode('/', $uri);
$path = end($segments);

// map root
$path = $path === '' ? 'dashboard' : $path;

// get previous path
$prevPath = $_SESSION['prev_path'] ?? null;

// allowed pages
$allowedPrevPaths = [
    'sign_in',
    'skill_pref_form',
    'dashboard',
    'notifications',
    'room',
    'book_session',
    'lectures',
    'timetable',
    'profile',
    'review_request',
    'assignment',
    'submit_assignment',
];

// if previous path is NOT allowed → logout
if ($prevPath !== null && !in_array($prevPath, $allowedPrevPaths, true)) {
    $_SESSION = [];
    session_destroy();

    header('Location: ../Auth/sign_in.php');
    exit;
}

// update for next request
$_SESSION['prev_path'] = $path;
?>