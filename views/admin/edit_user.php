<?php
require_once('../../auth/check_login.php');
require_once('../../config/dbcon.php');

// Ensure only admin can access this page
if (!hasRole('Admin')) {
    header('Location: ../dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "CSRF token validation failed";
        header('Location: users.php');
        exit();
    }

    $user_id = $_POST['user_id'];
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role_id = $_POST['role_id'];
    
    $errors = [];
    
    // Validate username
    if (empty($username)) {
        $errors[] = "Username is required";
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $errors[] = "Username must be between 3 and 50 characters";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "Username can only contain letters, numbers, and underscores";
    }
    
    // Validate email
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Validate password if provided
    if (!empty($password) && strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    // Validate role
    if (empty($role_id)) {
        $errors[] = "Role is required";
    }
    
    // Check if username already exists (excluding current user)
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND user_id != ? AND deleted_at IS NULL");
    $stmt->execute([$username, $user_id]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Username already exists";
    }
    
    // Check if email already exists (excluding current user)
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND user_id != ? AND deleted_at IS NULL");
    $stmt->execute([$email, $user_id]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Email already exists";
    }
    
    // If no errors, proceed with user update
    if (empty($errors)) {
        try {
            $conn->beginTransaction();
            
            // Get old values for audit log
            $stmt = $conn->prepare("SELECT username, email, role_id FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $old_values = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Prepare update query
            if (!empty($password)) {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("
                    UPDATE users 
                    SET username = ?, email = ?, password_hash = ?, role_id = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$username, $email, $password_hash, $role_id, $user_id]);
            } else {
                $stmt = $conn->prepare("
                    UPDATE users 
                    SET username = ?, email = ?, role_id = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$username, $email, $role_id, $user_id]);
            }
            
            // Prepare new values for audit log
            $new_values = [
                'username' => $username,
                'email' => $email,
                'role_id' => $role_id
            ];
            
            // Log the action
            $stmt = $conn->prepare("
                INSERT INTO audit_logs (user_id, action, table_affected, record_id, old_values, new_values)
                VALUES (?, 'UPDATE', 'users', ?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION['user_data']['user_id'],
                $user_id,
                json_encode($old_values),
                json_encode($new_values)
            ]);
            
            $conn->commit();
            
            $_SESSION['success'] = "User updated successfully";
            header('Location: users.php');
            exit();
            
        } catch (PDOException $e) {
            $conn->rollBack();
            $_SESSION['error'] = "Error updating user: " . $e->getMessage();
            header('Location: users.php');
            exit();
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
        header('Location: users.php');
        exit();
    }
} else {
    header('Location: users.php');
    exit();
} 