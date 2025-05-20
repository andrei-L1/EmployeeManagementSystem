<?php
header("Content-Type: application/json");
require_once '../../auth/check_login.php';
require_once '../../config/dbcon.php';

$response = ['success' => false, 'error' => ''];

try {
    // Update all notifications for the user
    $stmt = $conn->prepare("
        UPDATE notifications 
        SET is_read = TRUE 
        WHERE user_id = ? 
        AND deleted_at IS NULL
    ");
    $stmt->execute([$_SESSION['user_data']['user_id']]);

    $response['success'] = true;
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?> 