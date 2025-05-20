<?php
header("Content-Type: application/json");
require_once '../../auth/check_login.php';
require_once '../../config/dbcon.php';

$response = ['success' => false, 'error' => ''];

try {
    // Get request data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (!isset($data['leave_type_id']) || !isset($data['start_date']) || 
        !isset($data['end_date']) || !isset($data['reason'])) {
        throw new Exception("All fields are required");
    }
    
    // Validate dates
    $startDate = new DateTime($data['start_date']);
    $endDate = new DateTime($data['end_date']);
    $today = new DateTime();
    
    if ($startDate < $today) {
        throw new Exception("Start date cannot be in the past");
    }
    
    if ($endDate < $startDate) {
        throw new Exception("End date cannot be before start date");
    }
    
    // Calculate number of days
    $interval = $startDate->diff($endDate);
    $days = $interval->days + 1; // Include both start and end dates
    
    // Check leave balance
    $stmt = $conn->prepare("
        SELECT lt.days_allowed, 
               (lt.days_allowed - IFNULL(SUM(DATEDIFF(lr.end_date, lr.start_date) + 1), 0)) as remaining
        FROM leave_types lt
        LEFT JOIN leave_requests lr ON lt.leave_type_id = lr.leave_type_id 
            AND lr.employee_id = ? 
            AND lr.status = 'Approved'
            AND lr.deleted_at IS NULL
        WHERE lt.leave_type_id = ?
            AND lt.deleted_at IS NULL
        GROUP BY lt.leave_type_id
    ");
    $stmt->execute([$_SESSION['user_data']['employee_id'], $data['leave_type_id']]);
    $balance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$balance) {
        throw new Exception("Invalid leave type");
    }
    
    if ($days > $balance['remaining']) {
        throw new Exception("Insufficient leave balance");
    }
    
    // Check for overlapping leave requests
    $stmt = $conn->prepare("
        SELECT * FROM leave_requests 
        WHERE employee_id = ? 
        AND status != 'Rejected'
        AND deleted_at IS NULL
        AND ((start_date BETWEEN ? AND ?) 
        OR (end_date BETWEEN ? AND ?)
        OR (? BETWEEN start_date AND end_date))
    ");
    $stmt->execute([
        $_SESSION['user_data']['employee_id'],
        $data['start_date'],
        $data['end_date'],
        $data['start_date'],
        $data['end_date'],
        $data['start_date']
    ]);
    
    if ($stmt->rowCount() > 0) {
        throw new Exception("You already have a leave request for these dates");
    }
    
    // Begin transaction
    $conn->beginTransaction();
    
    try {
        // Insert leave request
        $stmt = $conn->prepare("
            INSERT INTO leave_requests 
            (employee_id, leave_type_id, start_date, end_date, reason, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'Pending', NOW())
        ");
        $stmt->execute([
            $_SESSION['user_data']['employee_id'],
            $data['leave_type_id'],
            $data['start_date'],
            $data['end_date'],
            $data['reason']
        ]);
        
        $leaveId = $conn->lastInsertId();
        
        // Log action
        $stmt = $conn->prepare("
            INSERT INTO audit_logs 
            (user_id, action, table_affected, record_id, ip_address)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_data']['user_id'],
            'LEAVE_REQUEST',
            'leave_requests',
            $leaveId,
            $_SERVER['REMOTE_ADDR']
        ]);
        
        $conn->commit();
        $response['success'] = true;
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?> 