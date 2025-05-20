<?php
require_once '../../auth/check_login.php';
require_once '../../config/dbcon.php';

// Only managers/HR can access this page
if (!hasRole('Manager') && !hasRole('HR')) {
    header('Location: ../dashboard.php');
    exit();
}

// Get employee ID from URL
$employeeId = $_GET['id'] ?? null;
if (!$employeeId) {
    header('Location: list.php');
    exit();
}

// Get employee data
$stmt = $conn->prepare("
    SELECT e.*, d.department_name, p.position_name, p.base_salary,
           CONCAT(e.first_name, ' ', e.last_name) as full_name,
           u.email
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN positions p ON e.position_id = p.position_id
    LEFT JOIN users u ON e.user_id = u.user_id
    WHERE e.employee_id = ? AND e.deleted_at IS NULL
");
$stmt->execute([$employeeId]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    header('Location: list.php');
    exit();
}

// Check if manager is viewing employee from their department
if (hasRole('Manager') && $employee['department_id'] !== $_SESSION['user_data']['employee_data']['department_id']) {
    header('Location: list.php');
    exit();
}

// Get recent attendance records
$stmt = $conn->prepare("
    SELECT * FROM attendance_records 
    WHERE employee_id = ? 
    AND deleted_at IS NULL 
    ORDER BY date DESC 
    LIMIT 5
");
$stmt->execute([$employeeId]);
$recentAttendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get leave balance
$stmt = $conn->prepare("
    SELECT lt.type_name, lt.days_allowed, 
           (lt.days_allowed - IFNULL(SUM(DATEDIFF(lr.end_date, lr.start_date) + 1), 0)) as remaining
    FROM leave_types lt
    LEFT JOIN leave_requests lr ON lt.leave_type_id = lr.leave_type_id 
        AND lr.employee_id = ? 
        AND lr.status = 'Approved'
        AND lr.deleted_at IS NULL
    WHERE lt.deleted_at IS NULL
    GROUP BY lt.leave_type_id
");
$stmt->execute([$employeeId]);
$leaveBalances = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent leave requests
$stmt = $conn->prepare("
    SELECT lr.*, lt.type_name,
           CONCAT(ae.first_name, ' ', ae.last_name) as approved_by_name
    FROM leave_requests lr
    JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
    LEFT JOIN employees ae ON lr.approved_by = ae.employee_id
    WHERE lr.employee_id = ? 
    AND lr.deleted_at IS NULL
    ORDER BY lr.created_at DESC
    LIMIT 5
");
$stmt->execute([$employeeId]);
$recentLeaves = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Details | EmployeeTrack Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-header {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .main-content {
            padding-left: 60px;
            padding-right: 60px;
            padding-top: 30px;
            transition: margin-left 0.3s;
        }
        .profile-picture {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            border: 5px solid white;
            object-fit: cover;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .info-card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            transition: transform 0.3s ease;
        }
        
        .info-card:hover {
            transform: translateY(-5px);
        }
        
        .info-card .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            padding: 1rem 1.35rem;
            font-weight: 700;
        }
        
        .info-item {
            padding: 0.75rem 1.35rem;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #5a5c69;
        }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-3 text-center">
                        <img src="<?= $employee['profile_picture'] ?? '../../assets/img/default-profile.jpg' ?>" 
                             alt="Profile Picture" 
                             class="profile-picture">
                    </div>
                    <div class="col-md-9">
                        <h2 class="mb-2"><?= htmlspecialchars($employee['full_name']) ?></h2>
                        <p class="mb-1">
                            <i class="fas fa-briefcase me-2"></i>
                            <?= htmlspecialchars($employee['position_name']) ?>
                        </p>
                        <p class="mb-1">
                            <i class="fas fa-building me-2"></i>
                            <?= htmlspecialchars($employee['department_name']) ?>
                        </p>
                        <p class="mb-0">
                            <i class="fas fa-calendar-alt me-2"></i>
                            Joined <?= date('F Y', strtotime($employee['hire_date'])) ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <div class="row">
                <!-- Personal Information -->
                <div class="col-md-6 mb-4">
                    <div class="card info-card">
                        <div class="card-header">
                            <h5 class="mb-0">Personal Information</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="info-item">
                                <div class="info-label">Full Name</div>
                                <div><?= htmlspecialchars($employee['first_name'] . ' ' . 
                                    ($employee['middle_name'] ? $employee['middle_name'] . ' ' : '') . 
                                    $employee['last_name']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Email</div>
                                <div><?= htmlspecialchars($employee['email'] ?? 'Not provided') ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Contact Number</div>
                                <div><?= htmlspecialchars($employee['contact_number'] ?? 'Not provided') ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Address</div>
                                <div><?= htmlspecialchars($employee['address'] ?? 'Not provided') ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Birth Date</div>
                                <div><?= $employee['birth_date'] ? date('F j, Y', strtotime($employee['birth_date'])) : 'Not provided' ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Gender</div>
                                <div><?= htmlspecialchars($employee['gender'] ?? 'Not provided') ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Employment Information -->
                <div class="col-md-6 mb-4">
                    <div class="card info-card">
                        <div class="card-header">
                            <h5 class="mb-0">Employment Information</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="info-item">
                                <div class="info-label">Employee ID</div>
                                <div><?= htmlspecialchars($employee['employee_id']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Position</div>
                                <div><?= htmlspecialchars($employee['position_name']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Department</div>
                                <div><?= htmlspecialchars($employee['department_name']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Employment Status</div>
                                <div>
                                    <span class="badge bg-<?= 
                                        $employee['employment_status'] === 'Regular' ? 'success' : 
                                        ($employee['employment_status'] === 'Probationary' ? 'warning' : 
                                        ($employee['employment_status'] === 'Contractual' ? 'info' : 'secondary')) 
                                    ?>">
                                        <?= htmlspecialchars($employee['employment_status']) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Hire Date</div>
                                <div><?= date('F j, Y', strtotime($employee['hire_date'])) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Base Salary</div>
                                <div>₱<?= number_format($employee['base_salary'], 2) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Attendance -->
                <div class="col-md-6 mb-4">
                    <div class="card info-card">
                        <div class="card-header">
                            <h5 class="mb-0">Recent Attendance</h5>
                            <a href="../attendance/report.php?employee=<?= $employee['employee_id'] ?>" 
                               class="btn btn-sm btn-primary">
                                View All
                            </a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($recentAttendance)): ?>
                            <div class="info-item">
                                <div class="text-muted">No recent attendance records</div>
                            </div>
                            <?php else: ?>
                            <?php foreach ($recentAttendance as $record): ?>
                            <div class="info-item">
                                <div class="info-label"><?= date('F j, Y', strtotime($record['date'])) ?></div>
                                <div>
                                    <span class="badge bg-<?= 
                                        $record['status'] === 'Present' ? 'success' : 
                                        ($record['status'] === 'Late' ? 'warning' : 
                                        ($record['status'] === 'On Leave' ? 'info' : 'danger')) 
                                    ?>">
                                        <?= $record['status'] ?>
                                    </span>
                                    <?php if ($record['time_in']): ?>
                                    <small class="text-muted ms-2">
                                        In: <?= date('h:i A', strtotime($record['time_in'])) ?>
                                    </small>
                                    <?php endif; ?>
                                    <?php if ($record['time_out']): ?>
                                    <small class="text-muted ms-2">
                                        Out: <?= date('h:i A', strtotime($record['time_out'])) ?>
                                    </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Leave Balance -->
                <div class="col-md-6 mb-4">
                    <div class="card info-card">
                        <div class="card-header">
                            <h5 class="mb-0">Leave Balance</h5>
                            <a href="../leave/request.php?employee=<?= $employee['employee_id'] ?>" 
                               class="btn btn-sm btn-primary">
                                View All
                            </a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($leaveBalances)): ?>
                            <div class="info-item">
                                <div class="text-muted">No leave balances available</div>
                            </div>
                            <?php else: ?>
                            <?php foreach ($leaveBalances as $balance): ?>
                            <div class="info-item">
                                <div class="info-label"><?= htmlspecialchars($balance['type_name']) ?></div>
                                <div>
                                    <span class="badge bg-info">
                                        <?= $balance['remaining'] ?> days remaining
                                    </span>
                                    <small class="text-muted ms-2">
                                        (<?= $balance['days_allowed'] ?> days total)
                                    </small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Leave Requests -->
                <div class="col-md-12 mb-4">
                    <div class="card info-card">
                        <div class="card-header">
                            <h5 class="mb-0">Recent Leave Requests</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($recentLeaves)): ?>
                            <div class="info-item">
                                <div class="text-muted">No recent leave requests</div>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Type</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                            <th>Status</th>
                                            <th>Approved By</th>
                                            <th>Comments</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentLeaves as $leave): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($leave['type_name']) ?></td>
                                            <td><?= date('M j, Y', strtotime($leave['start_date'])) ?></td>
                                            <td><?= date('M j, Y', strtotime($leave['end_date'])) ?></td>
                                            <td>
                                                <span class="badge bg-<?= 
                                                    $leave['status'] === 'Approved' ? 'success' : 
                                                    ($leave['status'] === 'Pending' ? 'warning' : 'danger') 
                                                ?>">
                                                    <?= $leave['status'] ?>
                                                </span>
                                            </td>
                                            <td><?= $leave['approved_by_name'] ?? '-' ?></td>
                                            <td><?= $leave['comments'] ?? '-' ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
