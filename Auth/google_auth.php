<?php
ob_start();

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../includes/scripts/connection.php';

ob_end_clean();
header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed."]);
    exit();
}

function decodeFirebaseToken(string $idToken): array|false {
    // Firebase ID tokens are JWTs: header.payload.signature
    $parts = explode('.', $idToken);
    if (count($parts) !== 3) return false;

    // Decode the payload (middle part) — base64url encoded
    $payload = $parts[1];
    $payload = str_replace(['-', '_'], ['+', '/'], $payload);
    $payload = base64_decode(str_pad($payload, strlen($payload) + (4 - strlen($payload) % 4) % 4, '='));
    if (!$payload) return false;

    $data = json_decode($payload, true);
    if (!$data) return false;

    // Basic sanity checks
    if (empty($data['sub']))   return false; // no user ID
    if (empty($data['email'])) return false; // no email
    if (($data['exp'] ?? 0) < time()) return false; // token expired

    return $data;
}

function jsonExit(array $data): void {
    echo json_encode($data);
    exit();
}

// ── 1. Read token ─────────────────────────────────────────────────────────────
$input   = json_decode(file_get_contents("php://input"), true);
$idToken = trim($input['id_token'] ?? '');

if (empty($idToken)) {
    jsonExit(["success" => false, "message" => "No ID token provided."]);
}

// ── 2. Decode Firebase JWT locally ───────────────────────────────────────────
$payload = decodeFirebaseToken($idToken);
if (!$payload) {
    jsonExit(["success" => false, "message" => "Invalid or expired Firebase token."]);
}

$email       = filter_var($payload['email']   ?? '', FILTER_VALIDATE_EMAIL);
$full_name   = htmlspecialchars(trim($payload['name']    ?? 'Google User'), ENT_QUOTES, 'UTF-8');
$profile_pic = htmlspecialchars(trim($payload['picture'] ?? ''),            ENT_QUOTES, 'UTF-8');

if (!$email) {
    jsonExit(["success" => false, "message" => "Could not retrieve a valid email from token."]);
}

// ── 3. Existing user? ─────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT user_id, user_name, full_name FROM user_master WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$existingUser = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existingUser) {
    $stmt = $conn->prepare("UPDATE user_master SET last_login = CURRENT_TIMESTAMP WHERE user_id = ?");
    $stmt->bind_param("i", $existingUser['user_id']);
    $stmt->execute();
    $stmt->close();

    $_SESSION['user_id']   = $existingUser['user_id'];
    $_SESSION['user_name'] = $existingUser['user_name'];
    $_SESSION['full_name'] = $existingUser['full_name'];
    $_SESSION['email']     = $email;
    $_SESSION['isloggedin'] = true;
    $_SESSION['prev_path'] = 'sign_in';
    $_SESSION['login_token'] = bin2hex(random_bytes(32));

    jsonExit(["success" => true, "is_new" => false, "redirect" => "../user/dashboard.php"]); // returning user → dashboard // returning user
}

// ── 4. New user — generate unique username ────────────────────────────────────
$base     = substr(preg_replace('/[^a-z0-9_]/', '', strtolower(preg_replace('/\s+/', '_', $full_name))), 0, 20);
$username = $base;
$suffix   = 1;
while (true) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM user_master WHERE user_name = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $taken = $stmt->get_result()->fetch_row()[0];
    $stmt->close();
    if ($taken == 0) break;
    $username = $base . '_' . $suffix++;
}

// ── 5. Insert ─────────────────────────────────────────────────────────────────
$dummy_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
$phone          = 0;

$stmt = $conn->prepare("
    INSERT INTO user_master
        (user_id, user_name, password, email, full_name, phone, profile_pic, credit, feedback, rating, join_date, last_login)
    VALUES
        (NULL, ?, ?, ?, ?, ?, ?, 100, NULL, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
");
$stmt->bind_param("ssssss", $username, $dummy_password, $email, $full_name, $phone, $profile_pic);

if (!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    jsonExit(["success" => false, "message" => "Database error: $err"]);
}

$_SESSION['user_id']   = $stmt->insert_id;
$_SESSION['user_name'] = $username;
$_SESSION['full_name'] = $full_name;
$_SESSION['email']     = $email;
$stmt->close();

jsonExit(["success" => true, "is_new" => true, "redirect" => "../user/skill_pref_form.php"]); // first time → skill preferences