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
                SELECT e.*, p.position_name, p.base_salary
                FROM employees e
                JOIN positions p ON e.position_id = p.position_id
                WHERE e.employee_id = ? AND e.deleted_at IS NULL
            ");
            $stmt->execute([$employeeId]);
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$employee) {
                throw new Exception("Employee not found: ID $employeeId");
            }

            // Process payroll using stored procedure
            $stmt = $conn->prepare("CALL ProcessPayroll(?, ?, ?, @base_salary, @overtime_pay, @deductions, @bonuses, @net_pay)");
            $stmt->execute([$employeeId, $data['start_date'], $data['end_date']]);

            // Get the results
            $stmt = $conn->query("SELECT @base_salary as base_salary, @overtime_pay as overtime_pay, 
                                @deductions as deductions, @bonuses as bonuses, @net_pay as net_pay");
            $payrollData = $stmt->fetch(PDO::FETCH_ASSOC);

            // Insert payroll record
            $stmt = $conn->prepare("
                INSERT INTO payroll (
                    employee_id, pay_period_start, pay_period_end,
                    basic_salary, total_allowances, total_deductions,
                    overtime_pay, net_pay, status, payment_date,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, NOW())
            ");
            $stmt->execute([
                $employeeId,
                $data['start_date'],
                $data['end_date'],
                $payrollData['base_salary'],
                $payrollData['bonuses'],
                $payrollData['deductions'],
                $payrollData['overtime_pay'],
                $payrollData['net_pay'],
                $data['pay_date']
            ]);

            // Log the action
            $stmt = $conn->prepare("
                INSERT INTO audit_logs 
                (user_id, action, table_affected, record_id, new_values, ip_address)
                VALUES (?, 'CREATE_PAYROLL', 'payroll', ?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION['user_data']['user_id'],
                $conn->lastInsertId(),
                json_encode($payrollData),
                $_SERVER['REMOTE_ADDR']
            ]);
        }

        $conn->commit();
        $response['success'] = true;
        $response['message'] = 'Payroll processed successfully';
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