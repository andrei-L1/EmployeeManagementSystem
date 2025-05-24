<?php
require_once '../../auth/check_login.php';
require_once '../../config/dbcon.php';

// Get employee's payroll records
$stmt = $conn->prepare("
    SELECT p.*, 
           DATE_FORMAT(p.pay_period_start, '%M %d, %Y') as period_start,
           DATE_FORMAT(p.pay_period_end, '%M %d, %Y') as period_end,
           DATE_FORMAT(p.payment_date, '%M %d, %Y') as pay_date
    FROM payroll p
    WHERE p.employee_id = ? 
    AND p.deleted_at IS NULL
    ORDER BY p.pay_period_start DESC
");
$stmt->execute([$_SESSION['user_data']['employee_id']]);
$payrollRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get employee details
$stmt = $conn->prepare("
    SELECT e.*, d.department_name, p.position_name, p.base_salary
    FROM employees e
    JOIN departments d ON e.department_id = d.department_id
    JOIN positions p ON e.position_id = p.position_id
    WHERE e.employee_id = ? AND e.deleted_at IS NULL
");
$stmt->execute([$_SESSION['user_data']['employee_id']]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Payslips | EmployeeTrack Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .main-content {
            padding-left: 60px;
            padding-right: 60px;
            padding-top: 30px;
            transition: margin-left 0.3s;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }
        }
        .payslip-card {
            transition: all 0.3s ease;
        }
        .payslip-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>My Payslips</h2>
        </div>

        <!-- Employee Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Employee Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Name:</strong> <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></p>
                        <p><strong>Employee ID:</strong> <?= htmlspecialchars($employee['employee_id']) ?></p>
                        <p><strong>Department:</strong> <?= htmlspecialchars($employee['department_name']) ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Position:</strong> <?= htmlspecialchars($employee['position_name']) ?></p>
                        <p><strong>Base Salary:</strong> ₱<?= number_format($employee['base_salary'], 2) ?></p>
                        <p><strong>Employment Status:</strong> <?= htmlspecialchars($employee['employment_status']) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payslips List -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Payslip History</h5>
            </div>
            <div class="card-body">
                <?php if (empty($payrollRecords)): ?>
                    <p class="text-muted text-center">No payslips found.</p>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($payrollRecords as $record): ?>
                            <div class="col-md-6">
                                <div class="card payslip-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h6 class="mb-0">Pay Period: <?= $record['period_start'] ?> - <?= $record['period_end'] ?></h6>
                                            <span class="badge bg-<?= 
                                                $record['status'] === 'Paid' ? 'success' : 
                                                ($record['status'] === 'Processed' ? 'primary' : 'warning') 
                                            ?>">
                                                <?= $record['status'] ?>
                                            </span>
                                        </div>
                                        <div class="row mb-2">
                                            <div class="col-6">Basic Salary:</div>
                                            <div class="col-6 text-end">₱<?= number_format($record['basic_salary'], 2) ?></div>
                                        </div>
                                        <div class="row mb-2">
                                            <div class="col-6">Overtime Pay:</div>
                                            <div class="col-6 text-end">₱<?= number_format($record['overtime_pay'], 2) ?></div>
                                        </div>
                                        <div class="row mb-2">
                                            <div class="col-6">Allowances:</div>
                                            <div class="col-6 text-end">₱<?= number_format($record['total_allowances'], 2) ?></div>
                                        </div>
                                        <div class="row mb-2">
                                            <div class="col-6">Deductions:</div>
                                            <div class="col-6 text-end">₱<?= number_format($record['total_deductions'], 2) ?></div>
                                        </div>
                                        <hr>
                                        <div class="row mb-2">
                                            <div class="col-6"><strong>Net Pay:</strong></div>
                                            <div class="col-6 text-end"><strong>₱<?= number_format($record['net_pay'], 2) ?></strong></div>
                                        </div>
                                        <div class="text-muted small">
                                            Payment Date: <?= $record['pay_date'] ?>
                                        </div>
                                        <div class="mt-3">
                                            <button class="btn btn-sm btn-outline-primary" onclick="window.print()">
                                                <i class="fas fa-print"></i> Print Payslip
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 