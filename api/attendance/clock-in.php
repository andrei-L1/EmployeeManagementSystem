<?php
header("Content-Type: application/json");
require_once '../../auth/check_login.php';
require_once '../../config/dbcon.php';
require_once '../../config/pusher.php';

$response = ['success' => false, 'error' => ''];

try {
    // Validate employee
    $employeeId = $_SESSION['user_data']['employee_id'];
    $stmt = $conn->prepare("SELECT * FROM employees 
                          WHERE employee_id = ? AND deleted_at IS NULL");
    $stmt->execute([$employeeId]);
    if ($stmt->rowCount() === 0) {
        throw new Exception("Employee not found");
    }

    // Check for existing record today
    $stmt = $conn->prepare("SELECT * FROM attendance_records 
                          WHERE employee_id = ? AND date = CURDATE()");
    $stmt->execute([$employeeId]);
    if ($stmt->rowCount() > 0) {
        throw new Exception("Already clocked in today");
    }

    // Determine status (Late if after 9:30 AM)
    $currentHour = date('H');
    $status = ($currentHour >= 9 && date('i') > 30) ? 'Late' : 'Present';

    // Insert record
    $stmt = $conn->prepare("INSERT INTO attendance_records 
                          (employee_id, date, time_in, status, ip_address, device_info)
                          VALUES (?, CURDATE(), NOW(), ?, ?, ?)");
    $stmt->execute([
        $employeeId,
        $status,
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    ]);

    $recordId = $conn->lastInsertId();

    // Log action
    $stmt = $conn->prepare("INSERT INTO audit_logs 
                          (user_id, action, table_affected, record_id, ip_address)
                          VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['user_data']['user_id'],
        'CLOCK_IN',
        'attendance_records',
        $recordId,
        $_SERVER['REMOTE_ADDR']
    ]);

    // Get employee details for Pusher event
    $stmt = $conn->prepare("SELECT e.*, u.username FROM employees e 
                          JOIN users u ON e.user_id = u.user_id 
                          WHERE e.employee_id = ?");
    $stmt->execute([$employeeId]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    // Trigger Pusher event
    $data = [
        'employee_id' => $employeeId,
        'employee_name' => $employee['first_name'] . ' ' . $employee['last_name'],
        'time_in' => date('Y-m-d H:i:s'),
        'status' => $status
    ];
    $pusher->trigger('attendance-channel', 'clock-in-event', $data);

    $response['success'] = true;
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>