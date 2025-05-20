<?php
header("Content-Type: application/json");
require_once '../../auth/check_login.php';
require_once '../../config/dbcon.php';

$response = ['success' => false, 'error' => ''];

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['notification_id'])) {
        throw new Exception('Missing notification ID');
    }

    // Update notification
    $stmt = $conn->prepare("
        UPDATE notifications 
        SET is_read = TRUE 
        WHERE notification_id = ? 
        AND user_id = ? 
        AND deleted_at IS NULL
    ");
    $stmt->execute([$data['notification_id'], $_SESSION['user_data']['user_id']]);

    $response['success'] = true;
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?> 