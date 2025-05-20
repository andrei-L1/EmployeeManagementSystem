<?php
header("Content-Type: application/json");
require_once '../../auth/check_login.php';
require_once '../../config/dbcon.php';

$response = ['success' => false, 'error' => ''];

try {
    if (!isset($_FILES['profile_picture'])) {
        throw new Exception('No file uploaded');
    }

    $file = $_FILES['profile_picture'];
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload failed');
    }

    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Invalid file type. Only JPG, PNG and GIF are allowed');
    }

    // Validate file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('File too large. Maximum size is 5MB');
    }

    // Create upload directory if it doesn't exist
    $uploadDir = '../../uploads/profile_pictures/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('profile_') . '.' . $extension;
    $filepath = $uploadDir . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to save file');
    }

    // Begin transaction
    $conn->beginTransaction();

    try {
        // Get old profile picture
        $stmt = $conn->prepare("SELECT profile_picture FROM employees WHERE employee_id = ?");
        $stmt->execute([$_SESSION['user_data']['employee_id']]);
        $oldPicture = $stmt->fetchColumn();

        // Update profile picture in database
        $stmt = $conn->prepare("
            UPDATE employees 
            SET profile_picture = ?,
                updated_at = NOW()
            WHERE employee_id = ?
        ");
        $stmt->execute(['uploads/profile_pictures/' . $filename, $_SESSION['user_data']['employee_id']]);

        // Delete old profile picture if exists
        if ($oldPicture && file_exists('../../' . $oldPicture)) {
            unlink('../../' . $oldPicture);
        }

        // Log action
        $stmt = $conn->prepare("
            INSERT INTO audit_logs 
            (user_id, action, table_affected, record_id, old_values, new_values, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_data']['user_id'],
            'PROFILE_PICTURE_UPDATE',
            'employees',
            $_SESSION['user_data']['employee_id'],
            json_encode(['profile_picture' => $oldPicture]),
            json_encode(['profile_picture' => 'uploads/profile_pictures/' . $filename]),
            $_SERVER['REMOTE_ADDR']
        ]);

        $conn->commit();
        $response['success'] = true;
    } catch (Exception $e) {
        $conn->rollBack();
        // Delete uploaded file if database update fails
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        throw $e;
    }
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?> 