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

    // Additional admin-specific stats
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role_id = (SELECT role_id FROM roles WHERE role_name = 'Admin') AND deleted_at IS NULL");
    $stmt->execute();
    $totalAdmins = $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COUNT(*) FROM audit_logs WHERE DATE(action_timestamp) = ?");
    $stmt->execute([$today]);
    $todayAuditLogs = $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt->execute();
    $activeUsers24h = $stmt->fetchColumn();

    // Get recent system activities
    $stmt = $conn->prepare("
        SELECT al.*, u.username 
        FROM audit_logs al 
        LEFT JOIN users u ON al.user_id = u.user_id 
        WHERE al.action_timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY al.action_timestamp DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recentActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

// Get user's notifications
$stmt = $conn->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    AND deleted_at IS NULL 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$_SESSION['user_data']['user_id']]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get monthly attendance data
$currentYear = date('Y');
$monthlyAttendance = [];

if (hasRole('Admin') || hasRole('HR')) {
    // Admin and HR see all employees
    $stmt = $conn->prepare("
        SELECT 
            MONTH(date) as month,
            status,
            COUNT(*) as count
        FROM attendance_records
        WHERE YEAR(date) = ?
        AND deleted_at IS NULL
        GROUP BY MONTH(date), status
        ORDER BY month
    ");
    $stmt->execute([$currentYear]);
} elseif (hasRole('Manager')) {
    // Manager sees only their department
    $stmt = $conn->prepare("
        SELECT 
            MONTH(ar.date) as month,
            ar.status,
            COUNT(*) as count
        FROM attendance_records ar
        JOIN employees e ON ar.employee_id = e.employee_id
        WHERE YEAR(ar.date) = ?
        AND e.department_id = ?
        AND ar.deleted_at IS NULL
        GROUP BY MONTH(ar.date), ar.status
        ORDER BY month
    ");
    $stmt->execute([$currentYear, $_SESSION['user_data']['employee_data']['department_id']]);
} else {
    // Employee sees only their own attendance
    $stmt = $conn->prepare("
        SELECT 
            MONTH(date) as month,
            status,
            COUNT(*) as count
        FROM attendance_records
        WHERE YEAR(date) = ?
        AND employee_id = ?
        AND deleted_at IS NULL
        GROUP BY MONTH(date), status
        ORDER BY month
    ");
    $stmt->execute([$currentYear, $_SESSION['user_data']['employee_id']]);
}

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize monthly data
$monthlyData = array_fill(1, 12, [
    'Present' => 0,
    'Late' => 0,
    'Absent' => 0
]);

// Process results
foreach ($results as $row) {
    $monthlyData[$row['month']][$row['status']] = $row['count'];
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

        .welcome-section {
            padding: 1.5rem 0;
        }

        .welcome-section h1 {
            color: var(--dark);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .welcome-section p {
            font-size: 0.95rem;
        }

        .welcome-section i {
            color: var(--primary);
        }

        .welcome-card {
            display: none;
        }

        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1.75rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
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

        .quick-action {
            background: white;
            border-radius: 1rem;
            padding: 1.75rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: all 0.2s ease;
            text-decoration: none;
            color: var(--dark);
            text-align: center;
            border: 1px solid rgba(0, 0, 0, 0.05);
            height: 100%;
        }

        .quick-action:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            color: var(--primary);
            border-color: var(--primary-light);
        }

        .quick-action i {
            transition: transform 0.2s ease;
        }

        .quick-action:hover i {
            transform: scale(1.1);
        }

        .notification-item {
            padding: 1.25rem;
            border-bottom: 1px solid #e2e8f0;
            transition: all 0.2s ease;
            cursor: pointer;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .notification-item:hover {
            background: rgba(79, 70, 229, 0.05);
            transform: translateX(5px);
        }

        .notification-item.unread {
            background: rgba(79, 70, 229, 0.05);
            border-left: 3px solid var(--primary);
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

        .avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e2e8f0;
            transition: transform 0.2s ease;
        }

        .avatar:hover {
            transform: scale(1.1);
        }

        .default-avatar {
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary), var(--info));
            color: white;
            font-size: 1.25rem;
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

        .btn-outline-primary {
            color: var(--primary);
            border-color: var(--primary);
        }

        .btn-outline-primary:hover {
            background: var(--primary);
            border-color: var(--primary);
            transform: translateY(-1px);
        }

        .badge {
            padding: 0.5em 0.75em;
            font-weight: 500;
            border-radius: 0.5rem;
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

            .stat-card, .chart-card, .quick-action {
                padding: 1.25rem;
            }
        }

        .profile-section {
            height: 100%;
            padding: 1.5rem 0;
        }

        .profile-section .avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-light);
            transition: transform 0.2s ease;
        }

        .profile-section .avatar:hover {
            transform: scale(1.05);
        }

        .profile-section .default-avatar {
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary), var(--info));
            color: white;
            font-size: 1.1rem;
        }

        .profile-section .badge {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
        }

        .profile-section .btn-link {
            padding: 0.5rem;
            color: var(--dark);
            transition: color 0.2s ease;
        }

        .profile-section .btn-link:hover {
            color: var(--primary);
        }

        .profile-section .dropdown-menu {
            border: none;
            border-radius: 0.75rem;
        }

        .profile-section .notification-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: background-color 0.2s ease;
        }

        .profile-section .notification-item:hover {
            background-color: rgba(79, 70, 229, 0.05);
        }

        .profile-section .notification-item.unread {
            background-color: rgba(79, 70, 229, 0.05);
        }

        .profile-section .notification-item:last-child {
            border-bottom: none;
        }

        /* Remove the old card styles */
        .card {
            display: none;
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="dashboard-container">
            <!-- Dashboard Header -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="welcome-section">
                        <h1 class="h3 mb-2">Welcome back, <?= htmlspecialchars($_SESSION['user_data']['full_name'] ?? 'User') ?></h1>
                        <p class="text-muted mb-0">
                            <i class="fas fa-calendar-alt me-2"></i>
                            <?= date('l, F j, Y') ?>
                            <?php if (isset($_SESSION['user_data']['department'])): ?>
                            <span class="mx-2">•</span>
                            <i class="fas fa-building me-2"></i>
                            <?= $_SESSION['user_data']['department'] ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="profile-section d-flex justify-content-end">
                        <div class="d-flex align-items-center">
                            <div class="d-flex align-items-center me-3">
                                <?php if (!empty($_SESSION['user_data']['employee_data']['profile_picture'])): ?>
                                    <img src="<?= htmlspecialchars($_SESSION['user_data']['employee_data']['profile_picture']) ?>" 
                                         class="avatar me-3" alt="Profile">
                                <?php else: ?>
                                    <div class="avatar default-avatar me-3">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <div class="fw-bold"><?= htmlspecialchars($_SESSION['user_data']['full_name'] ?? 'User') ?></div>
                                    <div class="badge bg-primary-subtle text-primary"><?= $_SESSION['user_data']['role'] ?? 'User' ?></div>
                                </div>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-link text-dark position-relative" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-bell fa-lg"></i>
                                    <?php if (!empty($notifications)): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                        <?= count($notifications) ?>
                                    </span>
                                    <?php endif; ?>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end shadow-sm" style="width: 320px;" aria-labelledby="notificationDropdown">
                                    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                                        <h6 class="mb-0">Notifications</h6>
                                        <button class="btn btn-link btn-sm text-primary p-0" id="markAllRead">Mark all read</button>
                                    </div>
                                    <div class="notification-list" style="max-height: 400px; overflow-y: auto;">
                                        <?php if (empty($notifications)): ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-bell-slash text-muted mb-2" style="font-size: 1.5rem;"></i>
                                            <p class="text-muted mb-0">No new notifications</p>
                                        </div>
                                        <?php else: ?>
                                        <?php foreach ($notifications as $notification): ?>
                                        <div class="notification-item <?= $notification['is_read'] ? '' : 'unread' ?>" 
                                             data-id="<?= $notification['notification_id'] ?>">
                                            <div class="d-flex justify-content-between mb-1">
                                                <h6 class="mb-0"><?= htmlspecialchars($notification['title']) ?></h6>
                                                <small class="text-muted">
                                                    <?= date('M j, g:i A', strtotime($notification['created_at'])) ?>
                                                </small>
                                            </div>
                                            <p class="mb-0 text-muted small"><?= htmlspecialchars($notification['message']) ?></p>
                                        </div>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="border-top px-3 py-2">
                                        <a href="#" class="text-primary text-decoration-none small">View all notifications</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <?php if (hasRole('Admin') || hasRole('HR') || hasRole('Manager')): ?>
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-muted mb-0"><?= hasRole('Manager') ? 'Team Members' : 'Total Employees' ?></h6>
                            <div class="stat-icon bg-primary">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                        <h3 class="mb-2"><?= $totalEmployees ?></h3>
                        <div class="text-success small">
                            <i class="fas fa-arrow-up"></i> 3.2% from last month
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-muted mb-0">Active <?= hasRole('Manager') ? 'Team' : 'Employees' ?></h6>
                            <div class="stat-icon bg-success">
                                <i class="fas fa-user-check"></i>
                            </div>
                        </div>
                        <h3 class="mb-2"><?= $activeEmployees ?></h3>
                        <div class="text-success small">
                            <i class="fas fa-arrow-up"></i> 1.1% from last month
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-muted mb-0">Present Today</h6>
                            <div class="stat-icon bg-info">
                                <i class="fas fa-clipboard-check"></i>
                            </div>
                        </div>
                        <h3 class="mb-2"><?= $presentToday ?></h3>
                        <div class="text-danger small">
                            <i class="fas fa-arrow-down"></i> 0.5% from yesterday
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-muted mb-0">Pending Leaves</h6>
                            <div class="stat-icon bg-warning">
                                <i class="fas fa-calendar-times"></i>
                            </div>
                        </div>
                        <h3 class="mb-2"><?= $pendingLeaves ?></h3>
                        <div class="text-success small">
                            <i class="fas fa-arrow-up"></i> 2.4% from last week
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="row">
                <!-- Main Content -->
                <div class="col-lg-8">
                    <!-- Role-Specific Content -->
                    <?php if (hasRole('Admin')): ?>
                    <div class="row g-4 mb-4">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="text-muted mb-0">Total Employees</h6>
                                    <div class="stat-icon bg-primary">
                                        <i class="fas fa-users"></i>
                                    </div>
                                </div>
                                <h3 class="mb-2"><?= $totalEmployees ?></h3>
                                <div class="text-success small">
                                    <i class="fas fa-arrow-up"></i> 3.2% from last month
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="text-muted mb-0">Active Users (24h)</h6>
                                    <div class="stat-icon bg-success">
                                        <i class="fas fa-user-clock"></i>
                                    </div>
                                </div>
                                <h3 class="mb-2"><?= $activeUsers24h ?></h3>
                                <div class="text-success small">
                                    <i class="fas fa-arrow-up"></i> 5.1% from yesterday
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="text-muted mb-0">Today's Audit Logs</h6>
                                    <div class="stat-icon bg-info">
                                        <i class="fas fa-history"></i>
                                    </div>
                                </div>
                                <h3 class="mb-2"><?= $todayAuditLogs ?></h3>
                                <div class="text-warning small">
                                    <i class="fas fa-exclamation-circle"></i> Monitor activity
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="text-muted mb-0">System Admins</h6>
                                    <div class="stat-icon bg-warning">
                                        <i class="fas fa-user-shield"></i>
                                    </div>
                                </div>
                                <h3 class="mb-2"><?= $totalAdmins ?></h3>
                                <div class="text-info small">
                                    <i class="fas fa-info-circle"></i> Active administrators
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- System Overview Card -->
                    <div class="chart-card mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="card-title mb-0"><i class="fas fa-tachometer-alt text-primary me-2"></i>System Overview</h5>
                            <div class="btn-group">
                                <a href="admin/audit-logs.php" class="btn btn-primary btn-sm">View All Logs</a>
                                <a href="admin/system-health.php" class="btn btn-outline-primary btn-sm">System Health</a>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <a href="admin/audit-logs.php" class="quick-action d-block">
                                    <i class="fas fa-history text-primary mb-2" style="font-size: 1.5rem;"></i>
                                    <div class="quick-action-label">Audit Logs</div>
                                    <small class="text-muted"><?= $todayAuditLogs ?> new today</small>
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="admin/user-management.php" class="quick-action d-block">
                                    <i class="fas fa-user-cog text-primary mb-2" style="font-size: 1.5rem;"></i>
                                    <div class="quick-action-label">User Management</div>
                                    <small class="text-muted"><?= $activeUsers24h ?> active users</small>
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="admin/database-backup.php" class="quick-action d-block">
                                    <i class="fas fa-database text-primary mb-2" style="font-size: 1.5rem;"></i>
                                    <div class="quick-action-label">Database Backup</div>
                                    <small class="text-muted">Last: 2 days ago</small>
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="admin/settings.php" class="quick-action d-block">
                                    <i class="fas fa-cogs text-primary mb-2" style="font-size: 1.5rem;"></i>
                                    <div class="quick-action-label">System Settings</div>
                                    <small class="text-muted">Configure system</small>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Recent System Activities -->
                    <div class="chart-card mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="card-title mb-0"><i class="fas fa-history text-primary me-2"></i>Recent System Activities</h5>
                            <a href="admin/audit-logs.php" class="btn btn-primary btn-sm">View All</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentActivities as $activity): ?>
                                    <tr>
                                        <td><?= date('M j, g:i A', strtotime($activity['action_timestamp'])) ?></td>
                                        <td><?= htmlspecialchars($activity['username'] ?? 'System') ?></td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $activity['action'] === 'Error' ? 'danger' : 
                                                ($activity['action'] === 'Update' ? 'warning' : 
                                                ($activity['action'] === 'Delete' ? 'danger' : 
                                                ($activity['action'] === 'Create' ? 'success' : 'info'))) 
                                            ?>">
                                                <?= htmlspecialchars($activity['action']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?= htmlspecialchars($activity['table_affected']) ?>
                                                <?= $activity['record_id'] ? ' (ID: ' . $activity['record_id'] . ')' : '' ?>
                                            </small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Attendance Chart -->
                    <div class="chart-card">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="card-title mb-0"><i class="fas fa-chart-line text-primary me-2"></i>Monthly Attendance</h5>
                            <div class="btn-group">
                                <button type="button" class="btn btn-primary btn-sm active">2025</button>
                                <button type="button" class="btn btn-outline-primary btn-sm">2024</button>
                            </div>
                        </div>
                        <div style="position: relative; height: 250px;">
                            <canvas id="attendanceChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Content -->
                <div class="col-lg-4">
                    <!-- Recent Attendance -->
                    <?php if (isset($_SESSION['user_data']['employee_id'])): ?>
                    <div class="chart-card mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="card-title mb-0"><i class="fas fa-calendar-day text-primary me-2"></i>Your Attendance</h5>
                            <a href="attendance/record.php" class="btn btn-primary btn-sm">View All</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Time In</th>
                                        <th>Time Out</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentAttendance as $record): ?>
                                    <tr>
                                        <td><?= date('M j', strtotime($record['date'])) ?></td>
                                        <td><?= $record['time_in'] ? date('H:i', strtotime($record['time_in'])) : '-' ?></td>
                                        <td><?= $record['time_out'] ? date('H:i', strtotime($record['time_out'])) : '-' ?></td>
                                        <td>
                                            <span class="status-badge <?= 
                                                $record['status'] === 'Present' ? 'bg-success-subtle text-success' : 
                                                ($record['status'] === 'Late' ? 'bg-warning-subtle text-warning' : 'bg-danger-subtle text-danger') 
                                            ?>">
                                                <i class="fas fa-circle me-1"></i>
                                                <?= $record['status'] ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Quick Actions -->
                    <div class="chart-card mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="card-title mb-0"><i class="fas fa-bolt text-primary me-2"></i>Quick Actions</h5>
                        </div>
                        <div class="row g-3">
                            <?php if (isset($_SESSION['user_data']['employee_id'])): ?>
                            <div class="col-6">
                                <a href="attendance/record.php" class="quick-action d-block">
                                    <i class="fas fa-fingerprint text-primary mb-2" style="font-size: 1.5rem;"></i>
                                    <div class="quick-action-label">Clock In/Out</div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="leave/request.php" class="quick-action d-block">
                                    <i class="fas fa-calendar-plus text-primary mb-2" style="font-size: 1.5rem;"></i>
                                    <div class="quick-action-label">Request Leave</div>
                                </a>
                            </div>
                            <?php endif; ?>

                            <?php if (hasRole('Admin') || hasRole('HR')): ?>
                            <div class="col-6">
                                <a href="employees/add.php" class="quick-action d-block">
                                    <i class="fas fa-user-plus text-primary mb-2" style="font-size: 1.5rem;"></i>
                                    <div class="quick-action-label">Add Employee</div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="payroll/process.php" class="quick-action d-block">
                                    <i class="fas fa-money-bill-wave text-primary mb-2" style="font-size: 1.5rem;"></i>
                                    <div class="quick-action-label">Process Payroll</div>
                                </a>
                            </div>
                            <?php endif; ?>

                            <!-- Add Payslip Quick Action -->
                            <div class="col-6">
                                <a href="payroll/payslip.php" class="quick-action d-block">
                                    <i class="fas fa-file-invoice-dollar text-primary mb-2" style="font-size: 1.5rem;"></i>
                                    <div class="quick-action-label">View Payslips</div>
                                </a>
                            </div>

                            <?php if (hasRole('HR') || hasRole('Manager')): ?>
                            <div class="col-6">
                                <a href="leave/approvals.php" class="quick-action d-block position-relative">
                                    <i class="fas fa-calendar-check text-primary mb-2" style="font-size: 1.5rem;"></i>
                                    <div class="quick-action-label">Leave Approvals</div>
                                    <?php if ($pendingLeaves > 0): ?>
                                    <span class="position-absolute top-0 end-0 badge rounded-pill bg-danger"><?= $pendingLeaves ?></span>
                                    <?php endif; ?>
                                </a>
                            </div>
                            <?php endif; ?>

                            <?php if (hasRole('Admin')): ?>
                            <div class="col-6">
                                <a href="admin/reports.php" class="quick-action d-block">
                                    <i class="fas fa-chart-bar text-primary mb-2" style="font-size: 1.5rem;"></i>
                                    <div class="quick-action-label">Generate Reports</div>
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script>
        // Attendance Chart
        const ctx = document.getElementById('attendanceChart').getContext('2d');
        const attendanceChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Present',
                    data: <?= json_encode(array_column($monthlyData, 'Present')) ?>,
                    backgroundColor: 'rgba(67, 97, 238, 0.7)',
                    borderColor: 'rgba(67, 97, 238, 1)',
                    borderWidth: 1
                }, {
                    label: 'Late',
                    data: <?= json_encode(array_column($monthlyData, 'Late')) ?>,
                    backgroundColor: 'rgba(247, 37, 133, 0.7)',
                    borderColor: 'rgba(247, 37, 133, 1)',
                    borderWidth: 1
                }, {
                    label: 'Absent',
                    data: <?= json_encode(array_column($monthlyData, 'Absent')) ?>,
                    backgroundColor: 'rgba(230, 57, 70, 0.7)',
                    borderColor: 'rgba(230, 57, 70, 1)',
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
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'end',
                        labels: {
                            boxWidth: 12,
                            padding: 15
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.raw;
                            }
                        }
                    }
                }
            }
        });

        // Handle notification dropdown
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', async () => {
                const notificationId = item.dataset.id;
                try {
                    const response = await fetch('../../api/notifications/mark-read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': '<?php echo $_SESSION['csrf_token']; ?>'
                        },
                        body: JSON.stringify({ notification_id: notificationId })
                    });
                    
                    const result = await response.json();
                    if (result.success) {
                        item.classList.remove('unread');
                        
                        // Update notification count
                        const badge = document.querySelector('#notificationDropdown .badge');
                        if (badge) {
                            const currentCount = parseInt(badge.textContent);
                            if (currentCount > 1) {
                                badge.textContent = currentCount - 1;
                            } else {
                                badge.remove();
                            }
                        }
                        
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: 'Notification marked as read',
                            showConfirmButton: false,
                            timer: 3000
                        });
                    }
                } catch (error) {
                    console.error('Error:', error);
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'error',
                        title: 'Failed to mark notification as read',
                        showConfirmButton: false,
                        timer: 3000
                    });
                }
            });
        });

        // Handle mark all as read
        document.getElementById('markAllRead').addEventListener('click', async (e) => {
            e.preventDefault();
            try {
                const response = await fetch('../../api/notifications/mark-all-read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': '<?php echo $_SESSION['csrf_token']; ?>'
                    }
                });
                
                const result = await response.json();
                if (result.success) {
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                    });
                    
                    // Remove notification count badge
                    const badge = document.querySelector('#notificationDropdown .badge');
                    if (badge) {
                        badge.remove();
                    }
                    
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'All notifications marked as read',
                        showConfirmButton: false,
                        timer: 3000
                    });
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'error',
                    title: 'Failed to mark all notifications as read',
                    showConfirmButton: false,
                    timer: 3000
                });
            }
        });
    </script>
</body>
</html>