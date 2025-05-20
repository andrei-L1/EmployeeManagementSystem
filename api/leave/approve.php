<?php
header("Content-Type: application/json");
require_once '../../auth/check_login.php';
require_once '../../config/dbcon.php';

$response = ['success' => false, 'error' => ''];

try {
    // Only managers/HR can approve
    if (!hasRole('Manager') && !hasRole('HR')) {
        throw new Exception('Unauthorized access');
    }

    // Get request data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['leave_id']) || !isset($data['status'])) {
        throw new Exception('Missing required fields');
    }

    if (!in_array($data['status'], ['Approved', 'Rejected'])) {
        throw new Exception('Invalid status');
    }

    // Begin transaction
    $conn->beginTransaction();

    try {
        // Get current leave request data for audit log
        $stmt = $conn->prepare("
            SELECT lr.*, e.user_id, e.first_name, e.last_name, lt.type_name
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.employee_id
            JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
            WHERE lr.leave_id = ? AND lr.deleted_at IS NULL
        ");
        $stmt->execute([$data['leave_id']]);
        $leaveRequest = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$leaveRequest) {
            throw new Exception('Leave request not found');
        }

        if ($leaveRequest['status'] !== 'Pending') {
            throw new Exception('Leave request is already processed');
        }

        // Update leave request
        $stmt = $conn->prepare("
            UPDATE leave_requests 
            SET status = ?, 
                approved_by = ?, 
                comments = ?,
                updated_at = NOW()
            WHERE leave_id = ?
        ");
        $stmt->execute([
            $data['status'],
            $_SESSION['user_data']['employee_id'],
            $data['comments'] ?? null,
            $data['leave_id']
        ]);

        // Create notification for the employee
        $stmt = $conn->prepare("
            INSERT INTO notifications 
            (user_id, title, message, type)
            VALUES (?, ?, ?, 'leave')
        ");
        
        $title = "Leave Request " . $data['status'];
        $message = sprintf(
            "Your %s leave request for %s to %s has been %s by %s. %s",
            $leaveRequest['type_name'],
            date('M j, Y', strtotime($leaveRequest['start_date'])),
            date('M j, Y', strtotime($leaveRequest['end_date'])),
            strtolower($data['status']),
            $_SESSION['user_data']['full_name'],
            $data['comments'] ? "Comments: " . $data['comments'] : ""
        );
        
        $stmt->execute([
            $leaveRequest['user_id'],
            $title,
            $message
        ]);

        // Log action
        $stmt = $conn->prepare("
            INSERT INTO audit_logs 
            (user_id, action, table_affected, record_id, old_values, new_values, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_data']['user_id'],
            'LEAVE_' . strtoupper($data['status']),
            'leave_requests',
            $data['leave_id'],
            json_encode($leaveRequest),
            json_encode([
                'status' => $data['status'],
                'approved_by' => $_SESSION['user_data']['employee_id'],
                'comments' => $data['comments'] ?? null
            ]),
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