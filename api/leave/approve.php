<?php
header("Content-Type: application/json");
require_once '../../auth/check_login.php';
require_once '../../config/dbcon.php';

// Only managers/HR can approve
if (!hasRole('Manager') && !hasRole('HR')) {
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

// Validate and process approval
$data = json_decode(file_get_contents('php://input'), true);
$stmt = $conn->prepare("UPDATE leave_requests 
                      SET status = ?, approved_by = ?, comments = ?
                      WHERE leave_id = ?");
$stmt->execute([
    $data['status'],
    $_SESSION['user_data']['employee_id'],
    $data['comments'] ?? null,
    $data['leave_id']
]);
?>