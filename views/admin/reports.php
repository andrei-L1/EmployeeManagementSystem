<?php
require_once '../../auth/check_login.php';
require_once '../../config/dbcon.php';

// Ensure only admin can access this page
if ($_SESSION['role'] !== 'Admin') {
    header('Location: ../dashboard.php');
    exit();
}

// Get current month and year for default date filters
$currentMonth = date('m');
$currentYear = date('Y');

// Initialize variables for filters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$departmentId = isset($_GET['department_id']) ? $_GET['department_id'] : '';
$reportType = isset($_GET['report_type']) ? $_GET['report_type'] : 'employee_stats';

// Fetch departments for filter
$departmentsQuery = "SELECT * FROM departments WHERE deleted_at IS NULL";
$departments = $conn->query($departmentsQuery)->fetchAll(PDO::FETCH_ASSOC);

// Function to get employee statistics
function getEmployeeStats($conn, $startDate, $endDate) {
    $stats = [];
    
    // Total employees
    $totalQuery = "SELECT COUNT(*) as total FROM employees WHERE deleted_at IS NULL";
    $stats['total_employees'] = $conn->query($totalQuery)->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Employees by department
    $deptQuery = "SELECT d.department_name, COUNT(e.employee_id) as count 
                 FROM departments d 
                 LEFT JOIN employees e ON d.department_id = e.department_id 
                 WHERE e.deleted_at IS NULL 
                 GROUP BY d.department_id";
    $stats['by_department'] = $conn->query($deptQuery)->fetchAll(PDO::FETCH_ASSOC);
    
    // Employees by position
    $posQuery = "SELECT p.position_name, COUNT(e.employee_id) as count 
                FROM positions p 
                LEFT JOIN employees e ON p.position_id = e.position_id 
                WHERE e.deleted_at IS NULL 
                GROUP BY p.position_id";
    $stats['by_position'] = $conn->query($posQuery)->fetchAll(PDO::FETCH_ASSOC);
    
    return $stats;
}

// Function to get attendance statistics
function getAttendanceStats($conn, $startDate, $endDate) {
    $stats = [];
    
    // Attendance summary
    $attQuery = "SELECT 
                    COUNT(CASE WHEN status = 'Present' THEN 1 END) as present,
                    COUNT(CASE WHEN status = 'Late' THEN 1 END) as late,
                    COUNT(CASE WHEN status = 'Absent' THEN 1 END) as absent,
                    COUNT(CASE WHEN status = 'On Leave' THEN 1 END) as on_leave
                FROM attendance_records 
                WHERE date BETWEEN :start_date AND :end_date AND deleted_at IS NULL";
    
    $stmt = $conn->prepare($attQuery);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    $stmt->execute();
    $stats['summary'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $stats;
}

// Function to get leave statistics
function getLeaveStats($conn, $startDate, $endDate) {
    $stats = [];
    
    // Leave requests summary
    $leaveQuery = "SELECT 
                    lt.type_name,
                    COUNT(lr.leave_id) as total_requests,
                    COUNT(CASE WHEN lr.status = 'Approved' THEN 1 END) as approved,
                    COUNT(CASE WHEN lr.status = 'Rejected' THEN 1 END) as rejected,
                    COUNT(CASE WHEN lr.status = 'Pending' THEN 1 END) as pending
                FROM leave_types lt
                LEFT JOIN leave_requests lr ON lt.leave_type_id = lr.leave_type_id
                WHERE (lr.start_date BETWEEN :start_date AND :end_date OR lr.end_date BETWEEN :start_date AND :end_date)
                AND lr.deleted_at IS NULL
                GROUP BY lt.leave_type_id";
    
    $stmt = $conn->prepare($leaveQuery);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    $stmt->execute();
    $stats['summary'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $stats;
}

// Get report data based on selected report type
$reportData = [];
switch($reportType) {
    case 'employee_stats':
        $reportData = getEmployeeStats($conn, $startDate, $endDate);
        break;
    case 'attendance':
        $reportData = getAttendanceStats($conn, $startDate, $endDate);
        break;
    case 'leave':
        $reportData = getLeaveStats($conn, $startDate, $endDate);
        break;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - EmployeeTrack Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #818cf8;
            --primary-dark: #3730a3;
            --secondary: #f472b6;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #0ea5e9;
            --light: #f8fafc;
            --dark: #1e293b;
        }

        body {
            background-color: #f5f7fa;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        .dashboard-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1.75rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            transition: transform 0.2s ease;
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.1);
        }

        .chart-card {
            background: white;
            border-radius: 1rem;
            padding: 1.75rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .chart-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            color: #64748b;
            border-bottom-width: 1px;
        }

        .table td {
            vertical-align: middle;
            padding: 1rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            font-weight: 500;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-1px);
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .bg-primary-subtle {
            background-color: rgba(79, 70, 229, 0.1) !important;
        }

        .text-primary {
            color: var(--primary) !important;
        }

        .bg-success-subtle {
            background-color: rgba(16, 185, 129, 0.1) !important;
        }

        .text-success {
            color: var(--success) !important;
        }

        .bg-warning-subtle {
            background-color: rgba(245, 158, 11, 0.1) !important;
        }

        .text-warning {
            color: var(--warning) !important;
        }

        .bg-danger-subtle {
            background-color: rgba(239, 68, 68, 0.1) !important;
        }

        .text-danger {
            color: var(--danger) !important;
        }

        .bg-info-subtle {
            background-color: rgba(14, 165, 233, 0.1) !important;
        }

        .text-info {
            color: var(--info) !important;
        }

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 1rem;
            }

            .stat-card, .chart-card {
                padding: 1.25rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="dashboard-container">
            <!-- Page Header -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <h1 class="h3 mb-2">Reports & Analytics</h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-chart-bar me-2"></i>
                        Generate and analyze various reports
                    </p>
                </div>
            </div>

            <!-- Report Filters -->
            <div class="chart-card mb-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="card-title mb-0"><i class="fas fa-filter text-primary me-2"></i>Report Filters</h5>
                </div>
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="report_type" class="form-label">Report Type</label>
                        <select class="form-select" id="report_type" name="report_type">
                            <option value="employee_stats" <?php echo $reportType === 'employee_stats' ? 'selected' : ''; ?>>Employee Statistics</option>
                            <option value="attendance" <?php echo $reportType === 'attendance' ? 'selected' : ''; ?>>Attendance Report</option>
                            <option value="leave" <?php echo $reportType === 'leave' ? 'selected' : ''; ?>>Leave Report</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="department_id" class="form-label">Department</label>
                        <select class="form-select" id="department_id" name="department_id">
                            <option value="">All Departments</option>
                            <?php foreach($departments as $dept): ?>
                                <option value="<?php echo $dept['department_id']; ?>" <?php echo $departmentId == $dept['department_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary" id="generateReport">
                            <i class="fas fa-sync-alt me-2"></i>Generate Report
                        </button>
                        <button type="button" class="btn btn-success" onclick="exportToExcel()">
                            <i class="fas fa-file-excel me-2"></i>Export to Excel
                        </button>
                    </div>
                </form>
            </div>

            <!-- Report Content -->
            <div class="chart-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-bar text-primary me-2"></i>
                        <?php
                        echo match($reportType) {
                            'employee_stats' => 'Employee Statistics',
                            'attendance' => 'Attendance Report',
                            'leave' => 'Leave Report',
                            default => 'Report Results'
                        };
                        ?>
                    </h5>
                </div>

                <?php if($reportType === 'employee_stats'): ?>
                    <!-- Employee Statistics -->
                    <div class="row g-4 mb-4">
                        <div class="col-md-4">
                            <div class="stat-card">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="text-muted mb-0">Total Employees</h6>
                                    <div class="stat-icon bg-primary">
                                        <i class="fas fa-users"></i>
                                    </div>
                                </div>
                                <h3 class="mb-2"><?php echo $reportData['total_employees']; ?></h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="chart-card">
                                <h6 class="card-title mb-3">Employees by Department</h6>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Department</th>
                                                <th>Count</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($reportData['by_department'] as $dept): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($dept['department_name']); ?></td>
                                                    <td>
                                                        <span class="badge bg-primary-subtle text-primary">
                                                            <?php echo $dept['count']; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="chart-card">
                                <h6 class="card-title mb-3">Employees by Position</h6>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Position</th>
                                                <th>Count</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($reportData['by_position'] as $pos): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($pos['position_name']); ?></td>
                                                    <td>
                                                        <span class="badge bg-primary-subtle text-primary">
                                                            <?php echo $pos['count']; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php elseif($reportType === 'attendance'): ?>
                    <!-- Attendance Report -->
                    <div class="row g-4 mb-4">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="text-muted mb-0">Present</h6>
                                    <div class="stat-icon bg-success">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                </div>
                                <h3 class="mb-2"><?php echo $reportData['summary']['present']; ?></h3>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="text-muted mb-0">Late</h6>
                                    <div class="stat-icon bg-warning">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                </div>
                                <h3 class="mb-2"><?php echo $reportData['summary']['late']; ?></h3>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="text-muted mb-0">Absent</h6>
                                    <div class="stat-icon bg-danger">
                                        <i class="fas fa-times-circle"></i>
                                    </div>
                                </div>
                                <h3 class="mb-2"><?php echo $reportData['summary']['absent']; ?></h3>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="text-muted mb-0">On Leave</h6>
                                    <div class="stat-icon bg-info">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                </div>
                                <h3 class="mb-2"><?php echo $reportData['summary']['on_leave']; ?></h3>
                            </div>
                        </div>
                    </div>

                <?php elseif($reportType === 'leave'): ?>
                    <!-- Leave Report -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Leave Type</th>
                                    <th>Total Requests</th>
                                    <th>Approved</th>
                                    <th>Rejected</th>
                                    <th>Pending</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($reportData['summary'] as $leave): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($leave['type_name']); ?></td>
                                        <td>
                                            <span class="badge bg-primary-subtle text-primary">
                                                <?php echo $leave['total_requests']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success-subtle text-success">
                                                <?php echo $leave['approved']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-danger-subtle text-danger">
                                                <?php echo $leave['rejected']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning-subtle text-warning">
                                                <?php echo $leave['pending']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script>
    function exportToExcel() {
        // Get the table data based on report type
        let tableData = [];
        let headers = [];
        let fileName = '';
        
        <?php if($reportType === 'employee_stats'): ?>
            // Employee Statistics Export
            headers = ['Department', 'Count'];
            tableData = <?php echo json_encode($reportData['by_department']); ?>;
            
            // Add position data
            let positionData = <?php echo json_encode($reportData['by_position']); ?>;
            tableData = tableData.concat(positionData);
            fileName = 'employee_statistics';
            
        <?php elseif($reportType === 'attendance'): ?>
            // Attendance Report Export
            headers = ['Status', 'Count'];
            tableData = [
                { status: 'Present', count: <?php echo $reportData['summary']['present']; ?> },
                { status: 'Late', count: <?php echo $reportData['summary']['late']; ?> },
                { status: 'Absent', count: <?php echo $reportData['summary']['absent']; ?> },
                { status: 'On Leave', count: <?php echo $reportData['summary']['on_leave']; ?> }
            ];
            fileName = 'attendance_report';
            
        <?php elseif($reportType === 'leave'): ?>
            // Leave Report Export
            headers = ['Leave Type', 'Total Requests', 'Approved', 'Rejected', 'Pending'];
            tableData = <?php echo json_encode($reportData['summary']); ?>;
            fileName = 'leave_report';
        <?php endif; ?>

        // Convert data to CSV format
        let csvContent = headers.join(',') + '\n';
        
        tableData.forEach(row => {
            let values = Object.values(row);
            csvContent += values.join(',') + '\n';
        });

        // Create and download the file
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `${fileName}_${new Date().toISOString().split('T')[0]}.csv`;
        link.click();
    }

    // Add loading state to generate report button
    document.getElementById('generateReport').addEventListener('click', function() {
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generating...';
        
        // Re-enable after form submission
        setTimeout(() => {
            this.disabled = false;
            this.innerHTML = '<i class="fas fa-sync-alt me-2"></i>Generate Report';
        }, 1000);
    });
    </script>
</body>
</html>

