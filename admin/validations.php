<?php
/* ═══════════════════════════════════════════════════════════════
   validations.php  –  SkillSwap Admin  –  Security & Validation
   ═══════════════════════════════════════════════════════════════ */

session_start();

// ── Admin Authentication Check ──
function checkAdminAuth() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        $_SESSION['error'] = 'Access denied. Admin privileges required.';
        header('Location: ../Auth/sign_in.php');
        exit();
    }
    return true;
}

// ── Input Sanitization ──
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// ── Validate Email ──
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// ── Validate Username ──
function validateUsername($username) {
    return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
}

// ── Validate Password Strength ──
function validatePassword($password) {
    // At least 8 characters, 1 uppercase, 1 lowercase, 1 number
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password);
}

// ── Validate Subject Name ──
function validateSubjectName($name) {
    return preg_match('/^[a-zA-Z0-9\s&\-\.]{2,50}$/', $name);
}

// ── Validate User ID ──
function validateUserId($id) {
    return filter_var($id, FILTER_VALIDATE_INT) && $id > 0;
}

// ── Validate Credit Amount ──
function validateCredits($credits) {
    return filter_var($credits, FILTER_VALIDATE_INT) && $credits >= 0 && $credits <= 9999;
}

// ── XSS Protection ──
function xssProtect($data) {
    if (is_array($data)) {
        return array_map('xssProtect', $data);
    }
    return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ── CSRF Token Generation ──
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// ── CSRF Token Validation ──
function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

// ── Rate Limiting (Simple Implementation) ──
function checkRateLimit($action = 'general', $limit = 10, $window = 300) {
    $key = $action . '_' . ($_SESSION['user_id'] ?? $_SERVER['REMOTE_ADDR']);
    $now = time();
    
    if (!isset($_SESSION['rate_limit'][$key])) {
        $_SESSION['rate_limit'][$key] = ['count' => 0, 'start' => $now];
    }
    
    $rate_data = &$_SESSION['rate_limit'][$key];
    
    // Reset window if expired
    if ($now - $rate_data['start'] > $window) {
        $rate_data = ['count' => 0, 'start' => $now];
    }
    
    $rate_data['count']++;
    
    if ($rate_data['count'] > $limit) {
        return false;
    }
    
    return true;
}

// ── Log Admin Actions ──
function logAdminAction($action, $details = '') {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'admin_id' => $_SESSION['user_id'] ?? 'unknown',
        'action' => $action,
        'details' => $details,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    $log_file = __DIR__ . '/../logs/admin_actions.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
}

// ── Validate File Upload ──
function validateFileUpload($file, $allowed_types = ['jpg', 'jpeg', 'png', 'gif'], $max_size = 5242880) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'error' => 'File upload error'];
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        return ['valid' => false, 'error' => 'File size exceeds limit'];
    }
    
    // Check file type
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_ext, $allowed_types)) {
        return ['valid' => false, 'error' => 'File type not allowed'];
    }
    
    // Check MIME type
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowed_mimes)) {
        return ['valid' => false, 'error' => 'Invalid MIME type'];
    }
    
    return ['valid' => true];
}

// ── Validate Meeting Data ──
function validateMeetingData($data) {
    $errors = [];
    
    if (empty($data['title']) || strlen($data['title']) > 100) {
        $errors[] = 'Meeting title is required and must be under 100 characters';
    }
    
    if (empty($data['description']) || strlen($data['description']) > 1000) {
        $errors[] = 'Meeting description is required and must be under 1000 characters';
    }
    
    if (!validateUserId($data['teacher_id'])) {
        $errors[] = 'Invalid teacher ID';
    }
    
    if (!validateUserId($data['student_id'])) {
        $errors[] = 'Invalid student ID';
    }
    
    // Validate meeting date/time
    $meeting_time = strtotime($data['meeting_time'] ?? '');
    if (!$meeting_time || $meeting_time <= time()) {
        $errors[] = 'Meeting time must be in the future';
    }
    
    return $errors;
}

// ── Sanitize Search Query ──
function sanitizeSearchQuery($query) {
    $query = trim($query);
    $query = preg_replace('/[^a-zA-Z0-9\s@._-]/', '', $query);
    return substr($query, 0, 100); // Limit length
}

// ── Validate Pagination Parameters ──
function validatePaginationParams($page = 1, $limit = 10) {
    $page = max(1, (int)$page);
    $limit = max(1, min(100, (int)$limit)); // Max 100 items per page
    return ['page' => $page, 'limit' => $limit];
}

// ── Error Response Helper ──
function sendErrorResponse($message, $status_code = 400) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode(['error' => $message]);
    exit;
}

// ── Success Response Helper ──
function sendSuccessResponse($data = [], $message = 'Success') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => $message, 'data' => $data]);
    exit;
}

// ── Initialize Security Headers ──
function initSecurityHeaders() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// ── Apply Security Headers ──
initSecurityHeaders();

// ── Auto-check admin auth for all admin pages ──
checkAdminAuth();

// ── Generate CSRF token for forms ──
$csrf_token = generateCSRFToken();
?>
