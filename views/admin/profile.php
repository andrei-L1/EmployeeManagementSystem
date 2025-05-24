<?php
require_once '../../auth/check_login.php';
require_once '../../config/dbcon.php';

// Ensure only admin can access this page
if (!hasRole('Admin')) {
    header('Location: ../dashboard.php');
    exit();
}

// Get admin's last login activity
$stmt = $conn->prepare("
    SELECT 
        u.last_login,
        u.created_at,
        COUNT(DISTINCT al.log_id) as total_actions,
        COUNT(DISTINCT CASE WHEN al.action_timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN al.log_id END) as recent_actions
    FROM users u
    LEFT JOIN audit_logs al ON u.user_id = al.user_id
    WHERE u.user_id = ?
    GROUP BY u.user_id
");
$stmt->execute([$_SESSION['user_data']['user_id']]);
$adminStats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent system activities
$stmt = $conn->prepare("
    SELECT al.*, u.username
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.user_id
    ORDER BY al.action_timestamp DESC
    LIMIT 10
");
$stmt->execute();
$recentActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - EmployeeTrack Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .admin-profile {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .profile-header {
            background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
            color: white;
            padding: 2rem;
            border-radius: 1rem;
            margin-bottom: 2rem;
        }
        
        .admin-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .activity-list {
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .activity-item {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .admin-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .action-card {
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            text-align: center;
            transition: transform 0.2s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .action-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #4f46e5;
        }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="admin-profile">
            <div class="profile-header">
                <div class="d-flex align-items-center">
                    <div class="avatar me-4">
                        <i class="fas fa-user-shield fa-3x"></i>
                    </div>
                    <div>
                        <h2 class="mb-1"><?= htmlspecialchars($_SESSION['user_data']['full_name']) ?></h2>
                        <p class="mb-0">System Administrator</p>
                    </div>
                </div>
            </div>
            
            <div class="admin-stats">
                <div class="stat-card">
                    <h6 class="text-muted mb-2">Last Login</h6>
                    <p class="mb-0"><?= date('F j, Y g:i A', strtotime($adminStats['last_login'])) ?></p>
                </div>
                <div class="stat-card">
                    <h6 class="text-muted mb-2">Account Created</h6>
                    <p class="mb-0"><?= date('F j, Y', strtotime($adminStats['created_at'])) ?></p>
                </div>
                <div class="stat-card">
                    <h6 class="text-muted mb-2">Total Actions</h6>
                    <p class="mb-0"><?= number_format($adminStats['total_actions']) ?></p>
                </div>
                <div class="stat-card">
                    <h6 class="text-muted mb-2">Recent Actions (24h)</h6>
                    <p class="mb-0"><?= number_format($adminStats['recent_actions']) ?></p>
                </div>
            </div>
            
            <div class="activity-list">
                <h4 class="mb-4">Recent System Activities</h4>
                <?php foreach ($recentActivities as $activity): ?>
                <div class="activity-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?= htmlspecialchars($activity['username'] ?? 'System') ?></strong>
                            <span class="text-muted"><?= htmlspecialchars($activity['action']) ?></span>
                            <span class="text-muted">in <?= htmlspecialchars($activity['table_affected']) ?></span>
                        </div>
                        <small class="text-muted">
                            <?= date('M j, g:i A', strtotime($activity['action_timestamp'])) ?>
                        </small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="admin-actions">
                <a href="users.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <h5>User Management</h5>
                    <p class="text-muted mb-0">Manage system users and permissions</p>
                </a>
                
                <a href="reports.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <h5>System Reports</h5>
                    <p class="text-muted mb-0">View detailed system analytics</p>
                </a>
                
                <a href="settings.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <h5>System Settings</h5>
                    <p class="text-muted mb-0">Configure system parameters</p>
                </a>
                
                <a href="backup.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-database"></i>
                    </div>
                    <h5>Database Backup</h5>
                    <p class="text-muted mb-0">Manage system backups</p>
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 