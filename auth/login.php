<?php

require '../config/dbcon.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$identifier || !$password) {
        $_SESSION['login_error'] = "Please enter both username/email and password.";
        header("Location: ../index.php"); // Adjust as needed
        exit;
    }

    $stmt = $conn->prepare("
        SELECT u.user_id, u.username, u.password_hash, u.email, u.is_active, r.role_name
        FROM users u
        JOIN roles r ON u.role_id = r.role_id
        WHERE (u.username = :identifier OR u.email = :identifier)
          AND u.is_active = 1
          AND u.deleted_at IS NULL
        LIMIT 1
    ");

    $stmt->execute([':identifier' => $identifier]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        $_SESSION['login_error'] = "Invalid username/email or password.";
        header("Location: ../index.php"); // Adjust as needed
        exit;
    }

    // Update last_login timestamp
    $update = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = :user_id");
    $update->execute([':user_id' => $user['user_id']]);

    // Set session data
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role_name'];
    $_SESSION['logged_in'] = true;

    header("Location: ../views/dashboard.php"); // Adjust as needed
    exit;
} else {
    http_response_code(405);
    echo "Method not allowed";
}
