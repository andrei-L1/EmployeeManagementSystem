<?php
require_once '../../auth/check_login.php';
require_once '../../config/dbcon.php';

// Only HR/Admin can access this page
if (!hasRole('HR') && !hasRole('Admin')) {
    header('Location: ../dashboard.php');
    exit();
}

// Get payroll records for the specified period
$period = isset($_GET['period']) ? $_GET['period'] : null;
$startDate = isset($_GET['start']) ? $_GET['start'] : null;
$endDate = isset($_GET['end']) ? $_GET['end'] : null;

if ($period) {
    list($startDate, $endDate) = explode(' to ', $period);
}

if ($startDate && $endDate) {
    // Get payroll records for the specified period
    $stmt = $conn->prepare("
        SELECT p.*, e.first_name, e.last_name, d.department_name, pos.position_name
        FROM payroll p
        JOIN employees e ON p.employee_id = e.employee_id
        JOIN departments d ON e.department_id = d.department_id
        JOIN positions pos ON e.position_id = pos.position_id
        WHERE p.pay_period_start = ? AND p.pay_period_end = ?
        AND p.deleted_at IS NULL
        ORDER BY d.department_name, e.last_name, e.first_name
    ");
    $stmt->execute([$startDate, $endDate]);
    $payrollRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Get recent payroll periods
    $stmt = $conn->prepare("
        SELECT DISTINCT pay_period_start, pay_period_end,
               COUNT(*) as employee_count,
               SUM(net_pay) as total_amount
        FROM payroll
        WHERE deleted_at IS NULL
        GROUP BY pay_period_start, pay_period_end
        ORDER BY pay_period_start DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recentPeriods = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Reports | EmployeeTrack Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
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
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Payroll Reports</h2>
            <div class="no-print">
                <a href="process.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i> Back to Process
                </a>
            </div>
        </div>

        <?php if ($startDate && $endDate): ?>
        <!-- Payroll Details View -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    Payroll Period: <?= date('M j, Y', strtotime($startDate)) ?> - 
                    <?= date('M j, Y', strtotime($endDate)) ?>
                </h5>
                <button class="btn btn-primary no-print" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($payrollRecords)): ?>
                <p class="text-muted">No payroll records found for this period.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Position</th>
                                <th>Basic Salary</th>
                                <th>Allowances</th>
                                <th>Deductions</th>
                                <th>Overtime</th>
                                <th>Net Pay</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalBasic = 0;
                            $totalAllowances = 0;
                            $totalDeductions = 0;
                            $totalOvertime = 0;
                            $totalNet = 0;
                            
                            foreach ($payrollRecords as $record): 
                                $totalBasic += $record['basic_salary'];
                                $totalAllowances += $record['total_allowances'];
                                $totalDeductions += $record['total_deductions'];
                                $totalOvertime += $record['overtime_pay'];
                                $totalNet += $record['net_pay'];
                            ?>
                            <tr>
                                <td><?= $record['first_name'] . ' ' . $record['last_name'] ?></td>
                                <td><?= $record['department_name'] ?></td>
                                <td><?= $record['position_name'] ?></td>
                                <td class="text-end">₱<?= number_format($record['basic_salary'], 2) ?></td>
                                <td class="text-end">₱<?= number_format($record['total_allowances'], 2) ?></td>
                                <td class="text-end">₱<?= number_format($record['total_deductions'], 2) ?></td>
                                <td class="text-end">₱<?= number_format($record['overtime_pay'], 2) ?></td>
                                <td class="text-end">₱<?= number_format($record['net_pay'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold">
                                <td colspan="3" class="text-end">Totals:</td>
                                <td class="text-end">₱<?= number_format($totalBasic, 2) ?></td>
                                <td class="text-end">₱<?= number_format($totalAllowances, 2) ?></td>
                                <td class="text-end">₱<?= number_format($totalDeductions, 2) ?></td>
                                <td class="text-end">₱<?= number_format($totalOvertime, 2) ?></td>
                                <td class="text-end">₱<?= number_format($totalNet, 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <!-- Payroll Periods List -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Recent Payroll Periods</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recentPeriods)): ?>
                <p class="text-muted">No payroll periods found.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th>Employees</th>
                                <th>Total Amount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPeriods as $period): ?>
                            <tr>
                                <td>
                                    <?= date('M j, Y', strtotime($period['pay_period_start'])) ?> - 
                                    <?= date('M j, Y', strtotime($period['pay_period_end'])) ?>
                                </td>
                                <td><?= $period['employee_count'] ?></td>
                                <td>₱<?= number_format($period['total_amount'], 2) ?></td>
                                <td>
                                    <a href="?start=<?= $period['pay_period_start'] ?>&end=<?= $period['pay_period_end'] ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        View Details
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
</body>
</html>
