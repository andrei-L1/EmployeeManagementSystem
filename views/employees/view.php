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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4e73df',
                        secondary: '#858796',
                        success: '#1cc88a',
                        info: '#36b9cc',
                        warning: '#f6c23e',
                        danger: '#e74a3b',
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="ml-64 p-8">
        <!-- Profile Header -->
        <div class="bg-gradient-to-r from-primary to-blue-800 rounded-2xl shadow-lg p-8 mb-8">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-6">
                    <div class="relative">
                        <?php
                        $profilePic = $employee['profile_picture'] ?? '';
                        if (!$profilePic || !file_exists('../../' . $profilePic)) {
                        ?>
                            <div class="w-32 h-32 rounded-full bg-white flex items-center justify-center shadow-lg">
                                <i class="fas fa-user text-5xl text-primary"></i>
                            </div>
                        <?php
                        } else {
                        ?>
                            <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile Picture" 
                                 class="w-32 h-32 rounded-full object-cover border-4 border-white shadow-lg">
                        <?php } ?>
                    </div>
                    <div class="text-white">
                        <h1 class="text-3xl font-bold mb-2"><?= htmlspecialchars($employee['full_name']) ?></h1>
                        <div class="space-y-1">
                            <p class="flex items-center">
                                <i class="fas fa-briefcase w-6"></i>
                                <?= htmlspecialchars($employee['position_name']) ?>
                            </p>
                            <p class="flex items-center">
                                <i class="fas fa-building w-6"></i>
                                <?= htmlspecialchars($employee['department_name']) ?>
                            </p>
                            <p class="flex items-center">
                                <i class="fas fa-calendar-alt w-6"></i>
                                Joined <?= date('F Y', strtotime($employee['hire_date'])) ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="flex space-x-4">
                    <a href="edit.php?id=<?= $employee['employee_id'] ?>" 
                       class="bg-white text-primary px-6 py-2 rounded-lg font-semibold hover:bg-gray-50 transition-colors">
                        <i class="fas fa-edit mr-2"></i>Edit Profile
                    </a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Personal Information -->
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b">
                    <h2 class="text-xl font-semibold text-gray-800">Personal Information</h2>
                </div>
                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-500">Full Name</p>
                            <p class="font-medium"><?= htmlspecialchars($employee['first_name'] . ' ' . 
                                ($employee['middle_name'] ? $employee['middle_name'] . ' ' : '') . 
                                $employee['last_name']) ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Email</p>
                            <p class="font-medium"><?= htmlspecialchars($employee['email'] ?? 'Not provided') ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Contact Number</p>
                            <p class="font-medium"><?= htmlspecialchars($employee['contact_number'] ?? 'Not provided') ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Birth Date</p>
                            <p class="font-medium"><?= $employee['birth_date'] ? date('F j, Y', strtotime($employee['birth_date'])) : 'Not provided' ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Gender</p>
                            <p class="font-medium"><?= htmlspecialchars($employee['gender'] ?? 'Not provided') ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Address</p>
                            <p class="font-medium"><?= htmlspecialchars($employee['address'] ?? 'Not provided') ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Employment Information -->
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b">
                    <h2 class="text-xl font-semibold text-gray-800">Employment Information</h2>
                </div>
                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-500">Employee ID</p>
                            <p class="font-medium"><?= htmlspecialchars($employee['employee_id']) ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Position</p>
                            <p class="font-medium"><?= htmlspecialchars($employee['position_name']) ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Department</p>
                            <p class="font-medium"><?= htmlspecialchars($employee['department_name']) ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Employment Status</p>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                <?= $employee['employment_status'] === 'Regular' ? 'bg-green-100 text-green-800' : 
                                    ($employee['employment_status'] === 'Probationary' ? 'bg-yellow-100 text-yellow-800' : 
                                    ($employee['employment_status'] === 'Contractual' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800')) ?>">
                                <?= htmlspecialchars($employee['employment_status']) ?>
                            </span>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Hire Date</p>
                            <p class="font-medium"><?= date('F j, Y', strtotime($employee['hire_date'])) ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Base Salary</p>
                            <p class="font-medium">₱<?= number_format($employee['base_salary'], 2) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Attendance -->
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b flex justify-between items-center">
                    <h2 class="text-xl font-semibold text-gray-800">Recent Attendance</h2>
                    <a href="attendance_report.php?id=<?= $employee['employee_id'] ?>" 
                       class="text-primary hover:text-blue-700 font-medium">
                        View Full Report
                    </a>
                </div>
                <div class="p-6">
                    <?php if (empty($recentAttendance)): ?>
                        <p class="text-gray-500 text-center py-4">No recent attendance records</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($recentAttendance as $record): ?>
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                    <div>
                                        <p class="font-medium"><?= date('F j, Y', strtotime($record['date'])) ?></p>
                                        <div class="flex items-center space-x-4 mt-1">
                                            <?php if ($record['time_in']): ?>
                                                <span class="text-sm text-gray-500">
                                                    <i class="fas fa-sign-in-alt mr-1"></i>
                                                    <?= date('h:i A', strtotime($record['time_in'])) ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($record['time_out']): ?>
                                                <span class="text-sm text-gray-500">
                                                    <i class="fas fa-sign-out-alt mr-1"></i>
                                                    <?= date('h:i A', strtotime($record['time_out'])) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <span class="px-3 py-1 rounded-full text-sm font-medium
                                        <?= $record['status'] === 'Present' ? 'bg-green-100 text-green-800' : 
                                            ($record['status'] === 'Late' ? 'bg-yellow-100 text-yellow-800' : 
                                            ($record['status'] === 'On Leave' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800')) ?>">
                                        <?= $record['status'] ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Leave Balance -->
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b flex justify-between items-center">
                    <h2 class="text-xl font-semibold text-gray-800">Leave Balance</h2>
                    <a href="../leave/request.php?employee=<?= $employee['employee_id'] ?>" 
                       class="text-primary hover:text-blue-700 font-medium">
                        View All
                    </a>
                </div>
                <div class="p-6">
                    <?php if (empty($leaveBalances)): ?>
                        <p class="text-gray-500 text-center py-4">No leave balances available</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($leaveBalances as $balance): ?>
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                    <div>
                                        <p class="font-medium"><?= htmlspecialchars($balance['type_name']) ?></p>
                                        <p class="text-sm text-gray-500 mt-1">
                                            <?= $balance['days_allowed'] ?> days total
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                            <?= $balance['remaining'] ?> days remaining
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Leave Requests -->
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden md:col-span-2">
                <div class="bg-gray-50 px-6 py-4 border-b">
                    <h2 class="text-xl font-semibold text-gray-800">Recent Leave Requests</h2>
                </div>
                <div class="p-6">
                    <?php if (empty($recentLeaves)): ?>
                        <p class="text-gray-500 text-center py-4">No recent leave requests</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Approved By</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Comments</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($recentLeaves as $leave): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($leave['type_name']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= date('M j, Y', strtotime($leave['start_date'])) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= date('M j, Y', strtotime($leave['end_date'])) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-3 py-1 rounded-full text-sm font-medium
                                                    <?= $leave['status'] === 'Approved' ? 'bg-green-100 text-green-800' : 
                                                        ($leave['status'] === 'Pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                                    <?= $leave['status'] ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= $leave['approved_by_name'] ?? '-' ?>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500">
                                                <?= $leave['comments'] ?? '-' ?>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
