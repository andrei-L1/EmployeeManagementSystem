<?php
header("Content-Type: application/json");
require_once '../../auth/check_login.php';
require_once '../../config/dbcon.php';
require_once '../../config/pusher.php';

$response = ['success' => false, 'error' => ''];

try {
    // Validate employee
    $employeeId = $_SESSION['user_data']['employee_id'];
    
    // Get today's record
    $stmt = $conn->prepare("SELECT * FROM attendance_records 
                          WHERE employee_id = ? AND date = CURDATE()");
    $stmt->execute([$employeeId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$record) {
        throw new Exception("No clock in found for today");
    }
    
    if ($record['time_out']) {
        throw new Exception("Already clocked out today");
    }

    // Calculate total hours
    $timeIn = new DateTime($record['time_in']);
    $timeOut = new DateTime();
    $interval = $timeIn->diff($timeOut);
    $totalHours = $interval->h + ($interval->i / 60);

    // Update record
    $stmt = $conn->prepare("UPDATE attendance_records 
                          SET time_out = NOW(), 
                              total_hours = ?,
                              updated_at = NOW()
                          WHERE record_id = ?");
    $stmt->execute([$totalHours, $record['record_id']]);

    // Log action
    $stmt = $conn->prepare("INSERT INTO audit_logs 
                          (user_id, action, table_affected, record_id, ip_address)
                          VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['user_data']['user_id'],
        'CLOCK_OUT',
        'attendance_records',
        $record['record_id'],
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
        'time_out' => date('Y-m-d H:i:s'),
        'total_hours' => $totalHours
    ];
    $pusher->trigger('attendance-channel', 'clock-out-event', $data);

    $response['success'] = true;
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>