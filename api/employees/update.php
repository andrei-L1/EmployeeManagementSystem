<?php
require_once '../../auth/check_login.php';
require_once '../../config/dbcon.php';

// Only Admin and HR can access this endpoint
if (!hasRole('Admin') && !hasRole('HR')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

try {
    // Start transaction
    $conn->beginTransaction();

    // Get employee ID
    $employee_id = $_POST['employee_id'] ?? null;
    if (!$employee_id) {
        throw new Exception('Employee ID is required');
    }

    // Get user_id from employee
    $stmt = $conn->prepare("SELECT user_id FROM employees WHERE employee_id = ?");
    $stmt->execute([$employee_id]);
    $user_id = $stmt->fetchColumn();

    if (!$user_id) {
        throw new Exception('User account not found for this employee');
    }

    // Handle profile picture upload
    $profile_picture = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_picture'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        
        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception('Invalid file type. Only JPG, PNG and GIF are allowed.');
        }
        
        if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
            throw new Exception('File size too large. Maximum size is 5MB.');
        }
        
        $upload_dir = '../../uploads/profile_pictures/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_filename = 'profile_' . $employee_id . '_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            $profile_picture = 'uploads/profile_pictures/' . $new_filename;
        } else {
            throw new Exception('Failed to upload profile picture');
        }
    }

    // Update employee information
    $stmt = $conn->prepare("
        UPDATE employees 
        SET first_name = ?,
            middle_name = ?,
            last_name = ?,
            contact_number = ?,
            birth_date = ?,
            gender = ?,
            address = ?,
            department_id = ?,
            position_id = ?,
            employment_status = ?,
            hire_date = ?
        " . ($profile_picture ? ", profile_picture = ?" : "") . "
        WHERE employee_id = ?
    ");

    $params = [
        $_POST['first_name'],
        $_POST['middle_name'] ?? null,
        $_POST['last_name'],
        $_POST['contact_number'],
        $_POST['birth_date'],
        $_POST['gender'],
        $_POST['address'],
        $_POST['department_id'],
        $_POST['position_id'],
        $_POST['employment_status'],
        $_POST['hire_date']
    ];

    if ($profile_picture) {
        $params[] = $profile_picture;
    }
    $params[] = $employee_id;

    $stmt->execute($params);

    // Update user account information
    $stmt = $conn->prepare("
        UPDATE users 
        SET username = ?,
            email = ?,
            role_id = ?
        WHERE user_id = ?
    ");

    $stmt->execute([
        $_POST['username'],
        $_POST['email'],
        $_POST['role_id'],
        $user_id
    ]);

    // Update password if provided
    if (!empty($_POST['password'])) {
        $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("
            UPDATE users 
            SET password_hash = ?
            WHERE user_id = ?
        ");
        $stmt->execute([$hashed_password, $user_id]);
    }

    // Commit transaction
    $conn->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollBack();
    
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 