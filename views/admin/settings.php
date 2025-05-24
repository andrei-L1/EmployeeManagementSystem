<?php
require_once '../../auth/check_login.php';
require_once '../../config/dbcon.php';

// Ensure only admin can access this page
if (!hasRole('Admin')) {
    header('Location: ../dashboard.php');
    exit();
}

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        // Update system settings
        $settings = [
            'system_name' => $_POST['system_name'] ?? 'EmployeeTrack Pro',
            'company_name' => $_POST['company_name'] ?? '',
            'company_email' => $_POST['company_email'] ?? '',
            'timezone' => $_POST['timezone'] ?? 'Asia/Manila',
            'work_hours_start' => $_POST['work_hours_start'] ?? '08:00',
            'work_hours_end' => $_POST['work_hours_end'] ?? '17:00',
            'attendance_grace_period' => $_POST['attendance_grace_period'] ?? 15,
            'session_timeout' => $_POST['session_timeout'] ?? 30,
            'enable_email_notifications' => isset($_POST['enable_email_notifications']) ? 1 : 0,
            'enable_sms_notifications' => isset($_POST['enable_sms_notifications']) ? 1 : 0,
            'maintenance_mode' => isset($_POST['maintenance_mode']) ? 1 : 0
        ];
        
        foreach ($settings as $key => $value) {
            $stmt = $conn->prepare("
                INSERT INTO system_settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $stmt->execute([$key, $value, $value]);
        }
        
        $conn->commit();
        $success = "System settings updated successfully.";
        
        // Log the action
        $stmt = $conn->prepare("
            INSERT INTO audit_logs (user_id, action, table_affected, new_values) 
            VALUES (?, 'Update', 'system_settings', ?)
        ");
        $stmt->execute([$_SESSION['user_data']['user_id'], json_encode($settings)]);
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "Error updating settings: " . $e->getMessage();
    }
}

// Get current settings
$stmt = $conn->query("SELECT setting_key, setting_value FROM system_settings");
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - EmployeeTrack Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .settings-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .settings-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        
        .settings-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .settings-body {
            padding: 1.5rem;
        }
        
        .form-label {
            font-weight: 500;
            color: #374151;
        }
        
        .form-text {
            color: #6b7280;
        }
        
        .settings-section {
            margin-bottom: 2rem;
        }
        
        .settings-section:last-child {
            margin-bottom: 0;
        }
        
        .settings-section h5 {
            color: #4f46e5;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="settings-container">
            <div class="settings-card">
                <div class="settings-header">
                    <h4 class="mb-0"><i class="fas fa-cogs me-2"></i>System Settings</h4>
                </div>
                
                <div class="settings-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        
                        <div class="settings-section">
                            <h5>General Settings</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">System Name</label>
                                    <input type="text" class="form-control" name="system_name" 
                                           value="<?= htmlspecialchars($settings['system_name'] ?? 'EmployeeTrack Pro') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Company Name</label>
                                    <input type="text" class="form-control" name="company_name" 
                                           value="<?= htmlspecialchars($settings['company_name'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Company Email</label>
                                    <input type="email" class="form-control" name="company_email" 
                                           value="<?= htmlspecialchars($settings['company_email'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Timezone</label>
                                    <select class="form-select" name="timezone">
                                        <option value="Asia/Manila" <?= ($settings['timezone'] ?? '') === 'Asia/Manila' ? 'selected' : '' ?>>Philippines (Asia/Manila)</option>
                                        <option value="UTC" <?= ($settings['timezone'] ?? '') === 'UTC' ? 'selected' : '' ?>>UTC</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h5>Work Hours & Attendance</h5>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Work Hours Start</label>
                                    <input type="time" class="form-control" name="work_hours_start" 
                                           value="<?= htmlspecialchars($settings['work_hours_start'] ?? '08:00') ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Work Hours End</label>
                                    <input type="time" class="form-control" name="work_hours_end" 
                                           value="<?= htmlspecialchars($settings['work_hours_end'] ?? '17:00') ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Attendance Grace Period (minutes)</label>
                                    <input type="number" class="form-control" name="attendance_grace_period" 
                                           value="<?= htmlspecialchars($settings['attendance_grace_period'] ?? '15') ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h5>Security & Session</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Session Timeout (minutes)</label>
                                    <input type="number" class="form-control" name="session_timeout" 
                                           value="<?= htmlspecialchars($settings['session_timeout'] ?? '30') ?>">
                                    <div class="form-text">Time of inactivity before automatic logout</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h5>Notifications</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="enable_email_notifications" 
                                               <?= ($settings['enable_email_notifications'] ?? '') == '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label">Enable Email Notifications</label>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="enable_sms_notifications" 
                                               <?= ($settings['enable_sms_notifications'] ?? '') == '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label">Enable SMS Notifications</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h5>System Status</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="maintenance_mode" 
                                               <?= ($settings['maintenance_mode'] ?? '') == '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label">Maintenance Mode</label>
                                    </div>
                                    <div class="form-text">Enable to restrict access to admin users only</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 