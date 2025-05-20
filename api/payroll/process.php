<?php
// Prevent any output before headers
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set headers first
header("Content-Type: application/json");

// Include required files
require_once '../../auth/check_login.php';
require_once '../../config/dbcon.php';

$response = ['success' => false, 'error' => ''];

try {
    // Only HR/Admin can process payroll
    if (!hasRole('HR') && !hasRole('Admin')) {
        throw new Exception('Unauthorized access');
    }

    // Get request data
    $input = file_get_contents('php://input');
    if (empty($input)) {
        throw new Exception('No data received');
    }

    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data received: ' . json_last_error_msg());
    }
    
    if (!isset($data['start_date']) || !isset($data['end_date']) || 
        !isset($data['pay_date']) || !isset($data['employee_ids'])) {
        throw new Exception('Missing required fields');
    }

    // Validate dates
    $startDate = new DateTime($data['start_date']);
    $endDate = new DateTime($data['end_date']);
    $payDate = new DateTime($data['pay_date']);
    
    if ($endDate < $startDate) {
        throw new Exception('End date cannot be before start date');
    }
    
    if ($payDate < $endDate) {
        throw new Exception('Pay date cannot be before end date');
    }

    // Begin transaction
    $conn->beginTransaction();

    try {
        foreach ($data['employee_ids'] as $employeeId) {
            // Get employee details
            $stmt = $conn->prepare("
                SELECT e.*, p.base_salary, p.position_name
                FROM employees e
                JOIN positions p ON e.position_id = p.position_id
                WHERE e.employee_id = ? AND e.deleted_at IS NULL
            ");
            $stmt->execute([$employeeId]);
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$employee) {
                throw new Exception("Employee not found: ID $employeeId");
            }

            // Calculate base salary
            $baseSalary = $employee['base_salary'];
            $workingDays = 22; // Standard working days per month
            $dailyRate = $baseSalary / $workingDays;

            // Get attendance records
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(CASE WHEN status = 'Present' THEN 1 END) as present_days,
                    COUNT(CASE WHEN status = 'Late' THEN 1 END) as late_days,
                    COUNT(CASE WHEN status = 'Absent' THEN 1 END) as absent_days
                FROM attendance_records
                WHERE employee_id = ?
                AND date BETWEEN ? AND ?
                AND deleted_at IS NULL
            ");
            $stmt->execute([$employeeId, $data['start_date'], $data['end_date']]);
            $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

            // Calculate overtime
            $stmt = $conn->prepare("
                SELECT SUM(hours) as total_overtime_hours
                FROM overtime_requests
                WHERE employee_id = ?
                AND date BETWEEN ? AND ?
                AND status = 'Approved'
                AND deleted_at IS NULL
            ");
            $stmt->execute([$employeeId, $data['start_date'], $data['end_date']]);
            $overtime = $stmt->fetch(PDO::FETCH_ASSOC);
            $overtimeHours = $overtime['total_overtime_hours'] ?? 0;
            $overtimePay = ($dailyRate / 8) * 1.25 * $overtimeHours; // 1.25x rate for overtime

            // Calculate deductions
            $stmt = $conn->prepare("
                SELECT SUM(amount) as total_deductions
                FROM salary_adjustments
                WHERE employee_id = ?
                AND adjustment_type = 'Deduction'
                AND effective_date BETWEEN ? AND ?
                AND deleted_at IS NULL
            ");
            $stmt->execute([$employeeId, $data['start_date'], $data['end_date']]);
            $deductions = $stmt->fetch(PDO::FETCH_ASSOC);
            $totalDeductions = $deductions['total_deductions'] ?? 0;

            // Calculate bonuses
            $stmt = $conn->prepare("
                SELECT SUM(amount) as total_bonuses
                FROM salary_adjustments
                WHERE employee_id = ?
                AND adjustment_type = 'Bonus'
                AND effective_date BETWEEN ? AND ?
                AND deleted_at IS NULL
            ");
            $stmt->execute([$employeeId, $data['start_date'], $data['end_date']]);
            $bonuses = $stmt->fetch(PDO::FETCH_ASSOC);
            $totalBonuses = $bonuses['total_bonuses'] ?? 0;

            // Calculate net salary
            $attendancePay = $dailyRate * $attendance['present_days'];
            $lateDeduction = ($dailyRate * 0.25) * $attendance['late_days'];
            $grossSalary = $attendancePay + $overtimePay + $totalBonuses;
            $netSalary = $grossSalary - $lateDeduction - $totalDeductions;

            // Insert payroll record
            $stmt = $conn->prepare("
                INSERT INTO payroll (
                    employee_id, pay_period_start, pay_period_end,
                    basic_salary, total_allowances, total_deductions,
                    overtime_pay, net_pay, status, payment_date,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, NOW())
            ");
            
            $result = $stmt->execute([
                $employeeId,
                $data['start_date'],
                $data['end_date'],
                $baseSalary,
                $totalBonuses,
                $totalDeductions + $lateDeduction,
                $overtimePay,
                $netSalary,
                $data['pay_date']
            ]);

            if (!$result) {
                throw new Exception("Failed to insert payroll record for employee ID: $employeeId");
            }

            $payrollId = $conn->lastInsertId();

            // Create notification for employee
            $stmt = $conn->prepare("
                INSERT INTO notifications (
                    user_id, title, message, type
                ) VALUES (?, ?, ?, 'payroll')
            ");
            
            $notificationResult = $stmt->execute([
                $employee['user_id'],
                'Payroll Generated',
                "Your payroll for period " . $data['start_date'] . " to " . $data['end_date'] . 
                " has been generated. Net salary: ₱" . number_format($netSalary, 2)
            ]);

            if (!$notificationResult) {
                throw new Exception("Failed to create notification for employee ID: $employeeId");
            }
        }

        // Log action
        $stmt = $conn->prepare("
            INSERT INTO audit_logs (
                user_id, action, table_affected, new_values, ip_address
            ) VALUES (?, ?, ?, ?, ?)
        ");
        
        $logResult = $stmt->execute([
            $_SESSION['user_data']['user_id'],
            'GENERATE_PAYROLL',
            'payroll',
            json_encode([
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'pay_date' => $data['pay_date'],
                'employee_count' => count($data['employee_ids'])
            ]),
            $_SERVER['REMOTE_ADDR']
        ]);

        if (!$logResult) {
            throw new Exception("Failed to log payroll generation action");
        }

        $conn->commit();
        $response['success'] = true;
        $response['payroll_period'] = $data['start_date'] . ' to ' . $data['end_date'];
    } catch (Exception $e) {
        $conn->rollBack();
        throw new Exception("Payroll processing failed: " . $e->getMessage());
    }
} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = $e->getMessage();
    error_log("Payroll Error: " . $e->getMessage());
}

// Ensure clean output
ob_clean();
echo json_encode($response);
exit;
?> 