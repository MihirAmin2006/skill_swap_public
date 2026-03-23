<?php
session_start();
require_once __DIR__ . '/../includes/scripts/connection.php';

if ($_SERVER["REQUEST_METHOD"] == 'POST') {
    // Sanitize and trim inputs
    include_once __DIR__ . '/../user/update_status_meeting.php';
    $user_name = trim($_POST['user_name'] ?? '');
    $password = $_POST['password'] ?? '';

    $errors = [];

    // Validation
    if (empty($user_name) || empty($password))
        $errors[] = "All the fields are required.";

    // If there are validation errors, redirect back
    if (!empty($errors)) {
        $_SESSION['error_msg'] = '● ' . implode('<br/>● ', $errors);
        header("Location: ./sign_in.php");
        exit();
    }

    // Check if user exists
    $stmt = $conn->prepare("SELECT `user_id`, `full_name`, `user_name`, `email`, `password` FROM `user_master` WHERE user_name = ? OR email = ?");
    $stmt->bind_param("ss", $user_name, $user_name);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $_SESSION['error_msg'] = "Invalid Username/Email or Password.";
        header("Location: ./sign_in.php");
        exit();
    }

    $user = $result->fetch_assoc();

    $stmt = $conn->prepare("SELECT count(distinct `user_id`) as `role_count`, `user_role` from `user_roles` where `user_id` = ?");
    $stmt->bind_param("i", $user['user_id']);
    $stmt->execute();
    $res = $stmt->get_result();

    $user_roles = $res->fetch_assoc();

    // Verify password
    if (password_verify($password, $user['password'])) {
        // Login successful
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['user_name'] = $user['user_name'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['success_msg'] = "Welcome back, " . htmlspecialchars($user['full_name']) . "!";
        $_SESSION['isloggedin'] = true;
        $_SESSION['login_token'] = bin2hex(random_bytes(32));
        $_SESSION['login_time'] = time();
        $_SESSION['prev_path'] = 'sign_in';
        // Update last_login
        $update = $conn->prepare("UPDATE user_master SET last_login = CURRENT_TIMESTAMP WHERE user_id = ?");
        $update->bind_param("i", $user['user_id']);
        $update->execute();
        $update->close();
        require_once __DIR__ . '/../user/update_status_meeting.php';
        if($user_roles['role_count'] <= 0){
            header("Location: ../user/skill_pref_form.php");
            exit();
        }else{
            // Redirect to dashboard or homepage
            if($user_roles['user_role'] == 'admin'){
                $_SESSION['user_role'] = 'admin';
                header("Location: ../admin/index.php");
                exit();   
            }else{
                header("Location: ../user/dashboard.php");
                exit();
            }
        }
    } else {
        $_SESSION['error_msg'] = "Invalid Username/Email or Password.";
        header("Location: ./sign_in.php");
        exit();
    }

    $stmt->close();
}
