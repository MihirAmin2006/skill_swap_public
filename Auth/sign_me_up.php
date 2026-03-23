<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/../includes/scripts/connection.php';

if ($_SERVER["REQUEST_METHOD"] == 'POST') {
    // Sanitize and trim inputs
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['user_name'] ?? '');
    $email = trim($_POST['user_email'] ?? '');
    $contact_no = trim($_POST['contact_no'] ?? '');
    $password = $_POST['user_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $terms_conditions = isset($_POST['terms_conditions']);

    // Initialize errors array
    $errors = [];

    // Validation
    if (empty($full_name)||empty($username)||empty($email)||empty($contact_no)||empty($password)||empty($confirm_password))
        $errors[] = "All fields are required.";
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format.";
    if (!empty($contact_no) && !preg_match('/^[0-9]{10}$/', $contact_no)) $errors[] = "Phone number must be 10 digits.";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match.";
    if (!$terms_conditions) $errors[] = "You must agree to the Terms of Service and Privacy Policy.";

    // Check if username or email already exists
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM user_master WHERE email = ? OR user_name = ?");
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_row()[0];
        $stmt->close();

        if ($count > 0) {
            $errors[] = "Email or Username already exists.";
        }
    }

    // If there are errors, redirect back with message
    if (!empty($errors)) {
        $_SESSION['error_msg'] = '● ' . implode('<br/>● ', $errors);
        header("Location: ./sign_in.php");
        exit();
    }


    // Insert user into database
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO `user_master`(`user_id`, `user_name`, `password`, `email`, `full_name`, `phone`, `credit`, `feedback`, `rating`, `join_date`, `last_login`) VALUES (NULL, ?, ?, ?, ?, ?, 100, NULL, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
    $stmt->bind_param("sssss", $username, $hashed_password, $email, $full_name, $contact_no);

    if ($stmt->execute()) {
        $_SESSION['success_msg'] = "You have successfully signed up!";
    } else {
        $_SESSION['error_msg'] = "Error signing up. Please try again.";
    }

    $stmt->close();
    header("Location: ./sign_in.php");
    exit();
}
