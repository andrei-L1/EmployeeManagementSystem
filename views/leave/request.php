<?php
require_once '../../auth/check_login.php';
require_once '../../config/dbcon.php';

// Get available leave types
$stmt = $conn->prepare("SELECT * FROM leave_types WHERE deleted_at IS NULL");
$stmt->execute();
$leaveTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get employee's leave balance
$stmt = $conn->prepare("SELECT lt.type_name, lt.days_allowed, 
                       (lt.days_allowed - IFNULL(SUM(DATEDIFF(lr.end_date, lr.start_date) + 1), 0)) as remaining
                       FROM leave_types lt
                       LEFT JOIN leave_requests lr ON lt.leave_type_id = lr.leave_type_id 
                       AND lr.employee_id = ? AND lr.status = 'Approved'
                       GROUP BY lt.leave_type_id");
$stmt->execute([$_SESSION['user_data']['employee_id']]);
$leaveBalances = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>