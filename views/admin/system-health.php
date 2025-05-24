<?php
require_once '../../auth/check_login.php';
require_once '../../config/dbcon.php';

// Ensure only admin can access this page
if (!hasRole('Admin')) {
    header('Location: ../dashboard.php');
    exit();
}

// Get system statistics
$stats = [
    'total_users' => $conn->query("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL")->fetchColumn(),
    'active_users' => $conn->query("SELECT COUNT(*) FROM users WHERE is_active = 1 AND deleted_at IS NULL")->fetchColumn(),
    'total_employees' => $conn->query("SELECT COUNT(*) FROM employees WHERE deleted_at IS NULL")->fetchColumn(),
    'total_departments' => $conn->query("SELECT COUNT(*) FROM departments WHERE deleted_at IS NULL")->fetchColumn(),
    'total_positions' => $conn->query("SELECT COUNT(*) FROM positions WHERE deleted_at IS NULL")->fetchColumn(),
    'total_attendance_records' => $conn->query("SELECT COUNT(*) FROM attendance_records WHERE deleted_at IS NULL")->fetchColumn(),
    'total_leave_requests' => $conn->query("SELECT COUNT(*) FROM leave_requests WHERE deleted_at IS NULL")->fetchColumn(),
    'total_payroll_records' => $conn->query("SELECT COUNT(*) FROM payroll WHERE deleted_at IS NULL")->fetchColumn()
];

// Get recent system errors from audit logs
$stmt = $conn->prepare("
    SELECT al.*, u.username 
    FROM audit_logs al 
    LEFT JOIN users u ON al.user_id = u.user_id 
    WHERE al.action = 'Error' 
    ORDER BY al.action_timestamp DESC 
    LIMIT 10
");
$stmt->execute();
$recentErrors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get system performance metrics
$performance = [
    'php_version' => PHP_VERSION,
    'mysql_version' => $conn->query('SELECT VERSION()')->fetchColumn(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'],
    'max_upload_size' => ini_get('upload_max_filesize'),
    'max_execution_time' => ini_get('max_execution_time'),
    'memory_limit' => ini_get('memory_limit')
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Health - EmployeeTrack Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .health-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .health-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        
        .health-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .health-body {
            padding: 1.5rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
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
            margin-bottom: 1rem;
        }
        
        .error-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .error-item {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            transition: background-color 0.2s;
        }
        
        .error-item:hover {
            background-color: #f8fafc;
        }
        
        .error-item:last-child {
            border-bottom: none;
        }
        
        .performance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .performance-item {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 0.5rem;
        }
        
        .performance-label {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 0.25rem;
        }
        
        .performance-value {
            font-weight: 600;
            color: #1f2937;
        }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="health-container">
            <div class="health-card">
                <div class="health-header">
                    <h4 class="mb-0"><i class="fas fa-heartbeat me-2"></i>System Health Overview</h4>
                </div>
                
                <div class="health-body">
                    <div class="row g-4 mb-4">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon bg-primary">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h3 class="mb-2"><?= number_format($stats['total_users']) ?></h3>
                                <p class="text-muted mb-0">Total Users</p>
                                <small class="text-success">
                                    <?= number_format($stats['active_users']) ?> active
                                </small>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon bg-success">
                                    <i class="fas fa-user-tie"></i>
                                </div>
                                <h3 class="mb-2"><?= number_format($stats['total_employees']) ?></h3>
                                <p class="text-muted mb-0">Total Employees</p>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon bg-info">
                                    <i class="fas fa-building"></i>
                                </div>
                                <h3 class="mb-2"><?= number_format($stats['total_departments']) ?></h3>
                                <p class="text-muted mb-0">Departments</p>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon bg-warning">
                                    <i class="fas fa-briefcase"></i>
                                </div>
                                <h3 class="mb-2"><?= number_format($stats['total_positions']) ?></h3>
                                <p class="text-muted mb-0">Positions</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row g-4">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon bg-primary">
                                    <i class="fas fa-clipboard-check"></i>
                                </div>
                                <h3 class="mb-2"><?= number_format($stats['total_attendance_records']) ?></h3>
                                <p class="text-muted mb-0">Attendance Records</p>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon bg-success">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <h3 class="mb-2"><?= number_format($stats['total_leave_requests']) ?></h3>
                                <p class="text-muted mb-0">Leave Requests</p>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon bg-info">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <h3 class="mb-2"><?= number_format($stats['total_payroll_records']) ?></h3>
                                <p class="text-muted mb-0">Payroll Records</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-8">
                    <div class="health-card">
                        <div class="health-header">
                            <h4 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Recent System Errors</h4>
                        </div>
                        
                        <div class="health-body">
                            <div class="error-list">
                                <?php if (empty($recentErrors)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-check-circle text-success mb-2" style="font-size: 2rem;"></i>
                                        <p class="text-muted mb-0">No recent errors found</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recentErrors as $error): ?>
                                        <div class="error-item">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1"><?= htmlspecialchars($error['username'] ?? 'System') ?></h6>
                                                    <p class="mb-1 text-danger"><?= htmlspecialchars($error['new_values']) ?></p>
                                                    <small class="text-muted">
                                                        Table: <?= htmlspecialchars($error['table_affected']) ?>
                                                    </small>
                                                </div>
                                                <small class="text-muted">
                                                    <?= date('M j, g:i A', strtotime($error['action_timestamp'])) ?>
                                                </small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="health-card">
                        <div class="health-header">
                            <h4 class="mb-0"><i class="fas fa-server me-2"></i>System Performance</h4>
                        </div>
                        
                        <div class="health-body">
                            <div class="performance-grid">
                                <div class="performance-item">
                                    <div class="performance-label">PHP Version</div>
                                    <div class="performance-value"><?= $performance['php_version'] ?></div>
                                </div>
                                
                                <div class="performance-item">
                                    <div class="performance-label">MySQL Version</div>
                                    <div class="performance-value"><?= $performance['mysql_version'] ?></div>
                                </div>
                                
                                <div class="performance-item">
                                    <div class="performance-label">Server Software</div>
                                    <div class="performance-value"><?= $performance['server_software'] ?></div>
                                </div>
                                
                                <div class="performance-item">
                                    <div class="performance-label">Max Upload Size</div>
                                    <div class="performance-value"><?= $performance['max_upload_size'] ?></div>
                                </div>
                                
                                <div class="performance-item">
                                    <div class="performance-label">Max Execution Time</div>
                                    <div class="performance-value"><?= $performance['max_execution_time'] ?> seconds</div>
                                </div>
                                
                                <div class="performance-item">
                                    <div class="performance-label">Memory Limit</div>
                                    <div class="performance-value"><?= $performance['memory_limit'] ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 