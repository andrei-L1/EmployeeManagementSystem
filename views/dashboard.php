<?php
require_once '../auth/check_login.php';
require_once '../config/dbcon.php';

// Common stats for all roles
$today = date('Y-m-d');

// Get data based on user role
if (hasRole('Admin')) {
    // Admin sees everything
    $stmt = $conn->prepare("SELECT COUNT(*) FROM employees WHERE deleted_at IS NULL");
    $stmt->execute();
    $totalEmployees = $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COUNT(*) FROM employees WHERE employment_status IN ('Probationary', 'Regular', 'Contractual') AND deleted_at IS NULL");
    $stmt->execute();
    $activeEmployees = $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COUNT(DISTINCT employee_id) FROM attendance_records WHERE date = ? AND status = 'Present' AND deleted_at IS NULL");
    $stmt->execute([$today]);
    $presentToday = $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COUNT(*) FROM leave_requests WHERE status = 'Pending' AND deleted_at IS NULL");
    $stmt->execute();
    $pendingLeaves = $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COUNT(*) FROM departments WHERE deleted_at IS NULL");
    $stmt->execute();
    $totalDepartments = $stmt->fetchColumn();

} elseif (hasRole('HR')) {
    // HR sees similar to admin but with some restrictions
    $stmt = $conn->prepare("SELECT COUNT(*) FROM employees WHERE deleted_at IS NULL");
    $stmt->execute();
    $totalEmployees = $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COUNT(*) FROM employees WHERE employment_status IN ('Probationary', 'Regular', 'Contractual') AND deleted_at IS NULL");
    $stmt->execute();
    $activeEmployees = $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COUNT(DISTINCT employee_id) FROM attendance_records WHERE date = ? AND status = 'Present' AND deleted_at IS NULL");
    $stmt->execute([$today]);
    $presentToday = $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COUNT(*) FROM leave_requests WHERE status = 'Pending' AND deleted_at IS NULL");
    $stmt->execute();
    $pendingLeaves = $stmt->fetchColumn();

    $totalDepartments = 0; // HR doesn't need department count

} elseif (hasRole('Manager')) {
    // Manager sees only their department stats
    $dept_id = $_SESSION['user_data']['employee_data']['department_id'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM employees WHERE department_id = ? AND deleted_at IS NULL");
    $stmt->execute([$dept_id]);
    $totalEmployees = $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COUNT(*) FROM employees WHERE department_id = ? AND employment_status IN ('Probationary', 'Regular', 'Contractual') AND deleted_at IS NULL");
    $stmt->execute([$dept_id]);
    $activeEmployees = $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COUNT(DISTINCT ar.employee_id) FROM attendance_records ar JOIN employees e ON ar.employee_id = e.employee_id WHERE ar.date = ? AND ar.status = 'Present' AND ar.deleted_at IS NULL AND e.department_id = ?");
    $stmt->execute([$today, $dept_id]);
    $presentToday = $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COUNT(*) FROM leave_requests lr JOIN employees e ON lr.employee_id = e.employee_id WHERE lr.status = 'Pending' AND lr.deleted_at IS NULL AND e.department_id = ?");
    $stmt->execute([$dept_id]);
    $pendingLeaves = $stmt->fetchColumn();

    $totalDepartments = 0; // Manager doesn't need department count

} else {
    // Employee sees minimal stats
    $totalEmployees = 0;
    $activeEmployees = 0;
    $presentToday = 0;
    $pendingLeaves = 0;
    $totalDepartments = 0;
}

// Get recent attendance for current user (if employee)
$recentAttendance = [];
if (isset($_SESSION['user_data']['employee_id'])) {
    $stmt = $conn->prepare("SELECT date, time_in, time_out, status FROM attendance_records WHERE employee_id = ? AND deleted_at IS NULL ORDER BY date DESC LIMIT 5");
    $stmt->execute([$_SESSION['user_data']['employee_id']]);
    $recentAttendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - EmployeeTrack Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --success-color: #36b9cc;
            --info-color: #f6c23e;
            --warning-color: #e74a3b;
            --danger-color: #858796;
            --dark-color: #5a5c69;
            --light-color: #f8f9fc;
        }
        
        body {
            background-color: #f8f9fc;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        .main-content {
            margin-left: 14rem;
            padding: 1.5rem;
            width: calc(100% - 14rem);
        }
        
        .role-badge {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
            font-weight: 700;
            letter-spacing: 0.05em;
        }
        
        .badge-admin {
            background-color: #6f42c1;
        }
        
        .badge-hr {
            background-color: #d63384;
        }
        
        .badge-manager {
            background-color: #fd7e14;
        }
        
        .badge-employee {
            background-color: #20c997;
        }
        
        .card {
            border: none;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1.5rem 0 rgba(58, 59, 69, 0.2);
        }
        
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            padding: 1rem 1.35rem;
            font-weight: 700;
            color: var(--dark-color);
        }
        
        .stat-card {
            border-left: 0.25rem solid;
            transition: all 0.3s;
        }
        
        .stat-card.primary {
            border-left-color: var(--primary-color);
        }
        
        .stat-card.success {
            border-left-color: var(--secondary-color);
        }
        
        .stat-card.info {
            border-left-color: var(--success-color);
        }
        
        .stat-card.warning {
            border-left-color: var(--info-color);
        }
        
        .stat-card:hover.primary {
            background-color: rgba(78, 115, 223, 0.05);
        }
        
        .stat-card:hover.success {
            background-color: rgba(28, 200, 138, 0.05);
        }
        
        .stat-card:hover.info {
            background-color: rgba(54, 185, 204, 0.05);
        }
        
        .stat-card:hover.warning {
            background-color: rgba(246, 194, 62, 0.05);
        }
        
        .stat-icon {
            font-size: 2rem;
            opacity: 0.3;
            position: absolute;
            right: 1rem;
            top: 1rem;
        }
        
        .quick-action-btn {
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem 0;
            border-radius: 0.35rem;
            transition: all 0.3s;
        }
        
        .quick-action-btn i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        }
        
        .attendance-table tr:last-child td {
            border-bottom: none;
        }
        
        .announcement-item {
            transition: all 0.3s;
            border-radius: 0.35rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .announcement-item:hover {
            background-color: #f8f9fc;
            transform: translateX(5px);
        }
        
        .announcement-date {
            font-size: 0.8rem;
            color: var(--danger-color);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border: 2px solid #e3e6f0;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .main-content {
                margin-left: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold text-gray-800">Dashboard</h2>
            <div class="d-flex align-items-center">
                <div class="me-3 text-end">
                    <small class="text-muted">Logged in as</small>
                    <div>
                        <strong><?= htmlspecialchars($_SESSION['user_data']['full_name'] ?? 'User') ?></strong>
                        <?php if (isset($_SESSION['user_data']['department'])): ?>
                        <br>
                        <small class="text-muted"><?= $_SESSION['user_data']['department'] ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                <img src="<?= $_SESSION['user_data']['employee_data']['profile_picture'] ?? 'default.jpg' ?>" 
                     class="user-avatar rounded-circle" alt="Profile">
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <?php if (hasRole('Admin') || hasRole('HR') || hasRole('Manager')): ?>
            <div class="col-md-3 mb-3">
                <div class="card stat-card primary h-100">
                    <div class="card-body position-relative">
                        <h6 class="text-uppercase text-primary mb-2"><?= hasRole('Manager') ? 'Team Members' : 'Employees' ?></h6>
                        <h2 class="mb-2"><?= $totalEmployees ?></h2>
                        <p class="mb-0 text-muted">
                            <span class="text-success me-2">
                                <i class="fas fa-arrow-up"></i> 3.2%
                            </span>
                            <span>Since last month</span>
                        </p>
                        <i class="fas fa-users stat-icon text-primary"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card stat-card success h-100">
                    <div class="card-body position-relative">
                        <h6 class="text-uppercase text-success mb-2">Active <?= hasRole('Manager') ? 'Team' : '' ?></h6>
                        <h2 class="mb-2"><?= $activeEmployees ?></h2>
                        <p class="mb-0 text-muted">
                            <span class="text-success me-2">
                                <i class="fas fa-arrow-up"></i> 1.1%
                            </span>
                            <span>Since last month</span>
                        </p>
                        <i class="fas fa-user-check stat-icon text-success"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card stat-card info h-100">
                    <div class="card-body position-relative">
                        <h6 class="text-uppercase text-info mb-2">Present Today</h6>
                        <h2 class="mb-2"><?= $presentToday ?></h2>
                        <p class="mb-0 text-muted">
                            <span class="text-danger me-2">
                                <i class="fas fa-arrow-down"></i> 0.5%
                            </span>
                            <span>Since yesterday</span>
                        </p>
                        <i class="fas fa-clipboard-check stat-icon text-info"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card stat-card warning h-100">
                    <div class="card-body position-relative">
                        <h6 class="text-uppercase text-warning mb-2">Pending Leaves</h6>
                        <h2 class="mb-2"><?= $pendingLeaves ?></h2>
                        <p class="mb-0 text-muted">
                            <span class="text-success me-2">
                                <i class="fas fa-arrow-up"></i> 2.4%
                            </span>
                            <span>Since last week</span>
                        </p>
                        <i class="fas fa-calendar-times stat-icon text-warning"></i>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (hasRole('Admin')): ?>
            <div class="col-md-3 mb-3">
                <div class="card stat-card h-100" style="border-left-color: #6f42c1;">
                    <div class="card-body position-relative">
                        <h6 class="text-uppercase mb-2" style="color: #6f42c1;">Departments</h6>
                        <h2 class="mb-2"><?= $totalDepartments ?></h2>
                        <p class="mb-0 text-muted">
                            <span class="text-success me-2">
                                <i class="fas fa-arrow-up"></i> 0.0%
                            </span>
                            <span>Since last month</span>
                        </p>
                        <i class="fas fa-building stat-icon" style="color: #6f42c1;"></i>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="row">
            <!-- First Column -->
            <div class="col-lg-8">
                <div class="row">
                    <!-- Role-Specific Content -->
                    <?php if (hasRole('Admin')): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">System Overview</h5>
                                <a href="admin/audit-logs.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="list-group list-group-flush">
                                    <a href="admin/audit-logs.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-history me-2 text-primary"></i> Audit Logs</span>
                                        <span class="badge bg-primary rounded-pill">14 new</span>
                                    </a>
                                    <a href="admin/user-management.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-user-cog me-2 text-success"></i> User Management</span>
                                        <span class="badge bg-success rounded-pill">3 pending</span>
                                    </a>
                                    <a href="admin/database-backup.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-database me-2 text-info"></i> Database Backup</span>
                                        <span class="text-muted">Last: 2 days ago</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (hasRole('HR')): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">HR Tasks</h5>
                                <a href="hr/tasks.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="list-group list-group-flush">
                                    <a href="hr/onboarding.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-user-plus me-2 text-primary"></i> New Onboarding</span>
                                        <span class="badge bg-primary rounded-pill">2 pending</span>
                                    </a>
                                    <a href="hr/performance-reviews.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-chart-line me-2 text-success"></i> Performance Reviews</span>
                                        <span class="badge bg-success rounded-pill">5 due</span>
                                    </a>
                                    <a href="hr/benefits.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-heart me-2 text-info"></i> Benefits Updates</span>
                                        <span class="text-muted">1 update</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (hasRole('Manager')): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Team Management</h5>
                                <a href="manager/team.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="list-group list-group-flush">
                                    <a href="manager/team-performance.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-chart-pie me-2 text-primary"></i> Team Performance</span>
                                        <span class="text-muted">Updated today</span>
                                    </a>
                                    <a href="manager/schedule.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-calendar-alt me-2 text-success"></i> Schedule</span>
                                        <span class="badge bg-success rounded-pill">3 conflicts</span>
                                    </a>
                                    <a href="manager/leave-approvals.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-check-circle me-2 text-info"></i> Leave Approvals</span>
                                        <span class="badge bg-info rounded-pill"><?= $pendingLeaves ?> pending</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Attendance Chart -->
                    <div class="col-md-12 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">Monthly Attendance Overview</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="attendanceChart" height="250"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Second Column -->
            <div class="col-lg-4">
                <!-- Recent Attendance (for employees) -->
                <?php if (isset($_SESSION['user_data']['employee_id'])): ?>
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Your Recent Attendance</h5>
                        <a href="attendance/record.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 attendance-table">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="border-0">Date</th>
                                        <th class="border-0">Time In</th>
                                        <th class="border-0">Time Out</th>
                                        <th class="border-0">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentAttendance as $record): ?>
                                    <tr>
                                        <td><?= htmlspecialchars(date('M j', strtotime($record['date']))) ?></td>
                                        <td><?= $record['time_in'] ? date('H:i', strtotime($record['time_in'])) : '-' ?></td>
                                        <td><?= $record['time_out'] ? date('H:i', strtotime($record['time_out'])) : '-' ?></td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $record['status'] === 'Present' ? 'success' : 
                                                ($record['status'] === 'Late' ? 'warning' : 
                                                ($record['status'] === 'On Leave' ? 'info' : 'danger')) 
                                            ?> rounded-pill">
                                                <?= htmlspecialchars($record['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Quick Actions -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php if (isset($_SESSION['user_data']['employee_id'])): ?>
                            <div class="col-6">
                                <a href="attendance/record.php" class="btn btn-primary quick-action-btn text-white">
                                    <i class="fas fa-fingerprint"></i>
                                    <span>Clock In/Out</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="leave/request.php" class="btn btn-success quick-action-btn text-white">
                                    <i class="fas fa-calendar-plus"></i>
                                    <span>Request Leave</span>
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (hasRole('Admin') || hasRole('HR')): ?>
                            <div class="col-6">
                                <a href="employees/add.php" class="btn btn-info quick-action-btn text-white">
                                    <i class="fas fa-user-plus"></i>
                                    <span>Add Employee</span>
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (hasRole('Admin') || hasRole('HR')): ?>
                            <div class="col-6">
                                <a href="payroll/process.php" class="btn btn-warning quick-action-btn text-white">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <span>Process Payroll</span>
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (hasRole('Admin')): ?>
                            <div class="col-6">
                                <a href="admin/settings.php" class="btn btn-dark quick-action-btn text-white">
                                    <i class="fas fa-cogs"></i>
                                    <span>System Settings</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="admin/reports.php" class="btn btn-secondary quick-action-btn text-white">
                                    <i class="fas fa-chart-bar"></i>
                                    <span>Generate Reports</span>
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Announcements -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Announcements</h5>
                        <a href="announcements.php" class="btn btn-sm btn-outline-secondary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php foreach ($recentAnnouncements as $announcement): ?>
                        <div class="announcement-item">
                            <h6 class="mb-1"><?= htmlspecialchars($announcement['title']) ?></h6>
                            <small class="announcement-date">
                                <i class="far fa-clock me-1"></i>
                                <?= date('M j, Y', strtotime($announcement['created_at'])) ?>
                            </small>
                            <p class="mt-2 mb-0 text-muted"><?= htmlspecialchars(substr($announcement['content'], 0, 100)) ?>...</p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <script>
        // Attendance Chart
        const ctx = document.getElementById('attendanceChart').getContext('2d');
        const attendanceChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Present',
                    data: [85, 78, 92, 88, 95, 87, 90, 82, 89, 93, 91, 86],
                    backgroundColor: 'rgba(78, 115, 223, 0.7)',
                    borderColor: 'rgba(78, 115, 223, 1)',
                    borderWidth: 1
                }, {
                    label: 'Late',
                    data: [5, 7, 3, 6, 2, 8, 4, 9, 5, 3, 4, 7],
                    backgroundColor: 'rgba(246, 194, 62, 0.7)',
                    borderColor: 'rgba(246, 194, 62, 1)',
                    borderWidth: 1
                }, {
                    label: 'Absent',
                    data: [10, 15, 5, 6, 3, 5, 6, 9, 6, 4, 5, 7],
                    backgroundColor: 'rgba(231, 74, 59, 0.7)',
                    borderColor: 'rgba(231, 74, 59, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.raw + '%';
                            }
                        }
                    }
                }
            }
        });

        // Role-specific JavaScript can be added here
        <?php if (hasRole('Admin')): ?>
        console.log("Admin dashboard loaded");
        <?php elseif (hasRole('HR')): ?>
        console.log("HR dashboard loaded");
        <?php elseif (hasRole('Manager')): ?>
        console.log("Manager dashboard loaded");
        <?php else: ?>
        console.log("Employee dashboard loaded");
        <?php endif; ?>
    </script>
</body>
</html>