<?php
function getAttendanceStatus($employeeId, $conn) {
    $stmt = $conn->prepare("SELECT * FROM attendance_records 
                          WHERE employee_id = ? AND date = CURDATE()");
    $stmt->execute([$employeeId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getWeeklyAttendance($employeeId, $conn) {
    $stmt = $conn->prepare("SELECT * FROM attendance_records 
                          WHERE employee_id = ? 
                          AND date BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND CURDATE()
                          ORDER BY date DESC");
    $stmt->execute([$employeeId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function calculateMonthlyHours($employeeId, $month, $year, $conn) {
    $stmt = $conn->prepare("SELECT SUM(total_hours) as total FROM attendance_records 
                          WHERE employee_id = ? 
                          AND MONTH(date) = ? 
                          AND YEAR(date) = ?");
    $stmt->execute([$employeeId, $month, $year]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'] ?? 0;
}
?>