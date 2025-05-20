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
            --primary-color: #6366f1;
            --primary-light: #818cf8;
            --primary-dark: #4f46e5;
            --secondary-color: #0ea5e9;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --light-color: #f8fafc;
            --dark-color: #1e293b;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --card-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --card-shadow-hover: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --transition-speed: 0.3s;
        }

        body {
            background-color: var(--gray-50);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: var(--gray-800);
            min-height: 100vh;
        }

        .main-content {
            padding: 2rem;
            transition: all var(--transition-speed) ease;
            min-height: 100vh;
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-100) 100%);
        }

        .dashboard-header {
            background: white;
            border-radius: 1.5rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
            border: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .welcome-section {
            flex: 1;
        }

        .welcome-section .welcome-text {
            display: block;
        }

        .welcome-section .date-dept {
            display: block;
        }

        .right-section {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .last-login {
            text-align: right;
        }

        .dashboard-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        .stat-card {
            background: white;
            border-radius: 1.5rem;
            padding: 1.75rem;
            height: 100%;
            transition: all var(--transition-speed) ease;
            border: 1px solid var(--gray-200);
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-shadow-hover);
            border-color: var(--primary-light);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 6px;
            height: 100%;
            background: var(--primary-color);
            transition: all var(--transition-speed) ease;
        }

        .stat-card:hover::before {
            width: 10px;
        }

        .stat-card.success::before {
            background: var(--success-color);
        }

        .stat-card.info::before {
            background: var(--info-color);
        }

        .stat-card.warning::before {
            background: var(--warning-color);
        }

        .stat-icon {
            position: absolute;
            right: 1.75rem;
            top: 1.75rem;
            font-size: 2.5rem;
            opacity: 0.15;
            color: var(--gray-600);
            transition: all var(--transition-speed) ease;
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.1) rotate(5deg);
            opacity: 0.25;
        }

        .stat-value {
            font-size: 2.25rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--gray-800);
            transition: all var(--transition-speed) ease;
            background: linear-gradient(135deg, var(--gray-800), var(--gray-600));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-card:hover .stat-value {
            transform: scale(1.05);
        }

        .stat-label {
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--gray-500);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .stat-change {
            font-size: 0.875rem;
            color: var(--gray-500);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
        }

        .stat-change i {
            transition: transform var(--transition-speed) ease;
        }

        .stat-card:hover .stat-change i {
            transform: translateY(-2px);
        }

        .stat-change.positive {
            color: var(--success-color);
        }

        .stat-change.negative {
            color: var(--danger-color);
        }

        .card {
            background: white;
            border-radius: 1.5rem;
            border: 1px solid var(--gray-200);
            box-shadow: var(--card-shadow);
            margin-bottom: 1.5rem;
            transition: all var(--transition-speed) ease;
            overflow: hidden;
        }

        .card:hover {
            box-shadow: var(--card-shadow-hover);
            border-color: var(--primary-light);
        }

        .card-header {
            background: none;
            border-bottom: 1px solid var(--gray-200);
            padding: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-header h5 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--gray-800);
            font-size: 1.1rem;
        }

        .card-header h5::before {
            content: '';
            display: inline-block;
            width: 4px;
            height: 1.5rem;
            background: var(--primary-color);
            border-radius: 2px;
        }

        .quick-action-btn {
            display: flex;
            align-items: center;
            padding: 1.25rem;
            border-radius: 1rem;
            transition: all var(--transition-speed) ease;
            color: white;
            text-decoration: none;
            margin-bottom: 1rem;
            position: relative;
            overflow: hidden;
            font-weight: 500;
            border: none;
        }

        .quick-action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, rgba(255,255,255,0.1), rgba(255,255,255,0));
            transform: translateX(-100%);
            transition: transform var(--transition-speed) ease;
        }

        .quick-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--card-shadow-hover);
            color: white;
        }

        .quick-action-btn:hover::before {
            transform: translateX(100%);
        }

        .quick-action-btn i {
            font-size: 1.5rem;
            margin-right: 1rem;
            transition: transform var(--transition-speed) ease;
        }

        .quick-action-btn:hover i {
            transform: scale(1.1) rotate(5deg);
        }

        .notification-item {
            padding: 1.25rem;
            border-bottom: 1px solid var(--gray-200);
            transition: all var(--transition-speed) ease;
            cursor: pointer;
            position: relative;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-item:hover {
            background-color: var(--gray-50);
            transform: translateX(5px);
        }

        .notification-item.unread {
            background-color: rgba(99, 102, 241, 0.05);
        }

        .notification-item.unread::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--primary-color);
            border-radius: 0 2px 2px 0;
        }

        .notification-item.unread:hover {
            background-color: rgba(99, 102, 241, 0.1);
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            padding: 0.25rem 0.5rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            background: var(--danger-color);
            color: white;
            animation: pulse 2s infinite;
        }

        .user-avatar {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--gray-200);
            transition: all var(--transition-speed) ease;
            background-color: var(--gray-100);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-avatar:hover {
            transform: scale(1.1);
            border-color: var(--primary-color);
        }

        .default-avatar {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            font-size: 1.5rem;
        }

        .default-avatar:hover {
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
        }

        .default-avatar i {
            transition: transform var(--transition-speed) ease;
        }

        .default-avatar:hover i {
            transform: scale(1.1);
        }

        .attendance-table {
            margin: 0;
        }

        .attendance-table th {
            font-weight: 600;
            color: var(--gray-600);
            border-bottom: 2px solid var(--gray-200);
            white-space: nowrap;
            padding: 1rem;
            background: var(--gray-50);
        }

        .attendance-table td {
            vertical-align: middle;
            transition: all var(--transition-speed) ease;
            padding: 1rem;
        }

        .attendance-table tr:hover td {
            background-color: var(--gray-50);
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

        .status-badge::before {
            content: '';
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

        .btn {
            transition: all var(--transition-speed) ease;
            border-radius: 0.75rem;
            padding: 0.5rem 1rem;
            font-weight: 500;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            border-radius: 0.5rem;
        }

        /* Loading States */
        .loading {
            position: relative;
            pointer-events: none;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: inherit;
            backdrop-filter: blur(4px);
        }

        .loading::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 1.5rem;
            height: 1.5rem;
            border: 3px solid var(--gray-200);
            border-top-color: var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            z-index: 1;
        }

        @keyframes spin {
            to {
                transform: translate(-50%, -50%) rotate(360deg);
            }
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
            }
        }

        /* Enhanced Mobile Responsiveness */
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .dashboard-header {
                padding: 1rem;
                border-radius: 1rem;
            }

            .welcome-section {
                display: flex;
                align-items: center;
            }

            .welcome-section .welcome-text {
                display: none;
            }

            .welcome-section .date-dept {
                display: none;
            }

            .welcome-section h2 {
                font-size: 1.25rem;
                margin: 0;
                white-space: nowrap;
            }

            .right-section {
                gap: 0.75rem;
            }

            .last-login .small {
                font-size: 0.7rem;
                color: var(--gray-500);
            }

            .last-login .fw-bold {
                font-size: 0.8rem;
                color: var(--gray-700);
            }

            .user-avatar {
                width: 40px;
                height: 40px;
            }
        }

        @media (max-width: 576px) {
            .dashboard-header {
                padding: 0.75rem;
            }

            .dashboard-header .d-flex {
                gap: 0.75rem;
            }

            .last-login .small {
                font-size: 0.65rem;
            }

            .last-login .fw-bold {
                font-size: 0.75rem;
            }
        }

        @media (max-width: 360px) {
            .main-content {
                padding: 0.75rem;
            }

            .dashboard-header {
                padding: 0.5rem;
            }

            .dashboard-header .d-flex {
                gap: 0.5rem;
            }

            .last-login .small {
                font-size: 0.6rem;
            }

            .last-login .fw-bold {
                font-size: 0.7rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="welcome-section">
                <span class="welcome-text">Welcome back,</span>
                <h2 class="mb-1"><?= htmlspecialchars($_SESSION['user_data']['full_name'] ?? 'User') ?></h2>
                <p class="text-muted mb-0 date-dept">
                    <?= date('l, F j, Y') ?> | 
                    <?php if (isset($_SESSION['user_data']['department'])): ?>
                    <?= $_SESSION['user_data']['department'] ?>
                    <?php endif; ?>
                </p>
            </div>
            <div class="right-section">
                <div class="last-login">
                    <div class="small text-muted">Last Login</div>
                    <div class="fw-bold"><?= date('M j, g:i A', strtotime($_SESSION['user_data']['last_login'] ?? 'now')) ?></div>
                </div>
                <?php if (!empty($_SESSION['user_data']['employee_data']['profile_picture'])): ?>
                    <img src="<?= htmlspecialchars($_SESSION['user_data']['employee_data']['profile_picture']) ?>" 
                         class="user-avatar" alt="Profile">
                <?php else: ?>
                    <div class="user-avatar default-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats Cards -->
        <?php if (hasRole('Admin') || hasRole('HR') || hasRole('Manager')): ?>
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-label"><?= hasRole('Manager') ? 'Team Members' : 'Total Employees' ?></div>
                    <div class="stat-value"><?= $totalEmployees ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> 3.2% from last month
                    </div>
                    <i class="fas fa-users stat-icon"></i>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card success">
                    <div class="stat-label">Active <?= hasRole('Manager') ? 'Team' : 'Employees' ?></div>
                    <div class="stat-value"><?= $activeEmployees ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> 1.1% from last month
                    </div>
                    <i class="fas fa-user-check stat-icon"></i>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card info">
                    <div class="stat-label">Present Today</div>
                    <div class="stat-value"><?= $presentToday ?></div>
                    <div class="stat-change negative">
                        <i class="fas fa-arrow-down"></i> 0.5% from yesterday
                    </div>
                    <i class="fas fa-clipboard-check stat-icon"></i>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card warning">
                    <div class="stat-label">Pending Leaves</div>
                    <div class="stat-value"><?= $pendingLeaves ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> 2.4% from last week
                    </div>
                    <i class="fas fa-calendar-times stat-icon"></i>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Main Content Area -->
            <div class="col-lg-8">
                <!-- Role-Specific Content -->
                <?php if (hasRole('Admin')): ?>
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">System Overview</h5>
                        <a href="admin/audit-logs.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <a href="admin/audit-logs.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-history text-primary me-2"></i>
                                    <span>Audit Logs</span>
                                </div>
                                <span class="badge bg-primary rounded-pill">14 new</span>
                            </a>
                            <a href="admin/user-management.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-user-cog text-success me-2"></i>
                                    <span>User Management</span>
                                </div>
                                <span class="badge bg-success rounded-pill">3 pending</span>
                            </a>
                            <a href="admin/database-backup.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-database text-info me-2"></i>
                                    <span>Database Backup</span>
                                </div>
                                <span class="text-muted">Last: 2 days ago</span>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Attendance Chart -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Monthly Attendance Overview</h5>
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-outline-primary active">2025</button>
                            <button type="button" class="btn btn-sm btn-outline-primary">2024</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="attendanceChart" height="300"></canvas>
                    </div>
                </div>
            </div>

            <!-- Sidebar Content -->
            <div class="col-lg-4">
                <!-- Recent Attendance -->
                <?php if (isset($_SESSION['user_data']['employee_id'])): ?>
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Your Recent Attendance</h5>
                        <a href="attendance/record.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 attendance-table">
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
                                            <span class="status-badge bg-<?= 
                                                $record['status'] === 'Present' ? 'success' : 
                                                ($record['status'] === 'Late' ? 'warning' : 
                                                ($record['status'] === 'On Leave' ? 'info' : 'danger')) 
                                            ?>">
                                                <?= $record['status'] ?>
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
                        <?php if (isset($_SESSION['user_data']['employee_id'])): ?>
                        <a href="attendance/record.php" class="quick-action-btn bg-primary">
                            <i class="fas fa-fingerprint"></i>
                            <span>Clock In/Out</span>
                        </a>
                        <a href="leave/request.php" class="quick-action-btn bg-success">
                            <i class="fas fa-calendar-plus"></i>
                            <span>Request Leave</span>
                        </a>
                        <?php endif; ?>

                        <?php if (hasRole('Admin') || hasRole('HR')): ?>
                        <a href="employees/add.php" class="quick-action-btn bg-info">
                            <i class="fas fa-user-plus"></i>
                            <span>Add Employee</span>
                        </a>
                        <a href="payroll/process.php" class="quick-action-btn bg-warning">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Process Payroll</span>
                        </a>
                        <?php endif; ?>

                        <?php if (hasRole('HR') || hasRole('Manager')): ?>
                        <a href="leave/approvals.php" class="quick-action-btn bg-info position-relative">
                            <i class="fas fa-calendar-check"></i>
                            <span>Leave Approvals</span>
                            <?php if ($pendingLeaves > 0): ?>
                            <span class="notification-badge bg-danger"><?= $pendingLeaves ?></span>
                            <?php endif; ?>
                        </a>
                        <?php endif; ?>

                        <?php if (hasRole('Admin')): ?>
                        <a href="admin/settings.php" class="quick-action-btn bg-dark">
                            <i class="fas fa-cogs"></i>
                            <span>System Settings</span>
                        </a>
                        <a href="admin/reports.php" class="quick-action-btn bg-secondary">
                            <i class="fas fa-chart-bar"></i>
                            <span>Generate Reports</span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Notifications -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Notifications</h5>
                        <button class="btn btn-sm btn-primary" id="markAllRead">Mark All as Read</button>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($notifications)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-bell-slash text-muted mb-2" style="font-size: 2rem;"></i>
                            <p class="text-muted mb-0">No new notifications</p>
                        </div>
                        <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($notifications as $notification): ?>
                            <div class="notification-item <?= $notification['is_read'] ? '' : 'unread' ?>" 
                                 data-id="<?= $notification['notification_id'] ?>">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?= htmlspecialchars($notification['title']) ?></h6>
                                    <small class="text-muted">
                                        <?= date('M j, g:i A', strtotime($notification['created_at'])) ?>
                                    </small>
                                </div>
                                <p class="mb-1"><?= htmlspecialchars($notification['message']) ?></p>
                                <?php if (!$notification['is_read']): ?>
                                <small class="text-primary">New</small>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
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

        // Handle notification clicks
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
                        item.querySelector('small.text-primary')?.remove();
                        
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
        document.getElementById('markAllRead').addEventListener('click', async () => {
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
                        item.querySelector('small.text-primary')?.remove();
                    });
                    
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