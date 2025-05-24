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

// Get month and year from URL, default to current month
$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// Get employee data
$stmt = $conn->prepare("
    SELECT e.*, d.department_name, p.position_name,
           CONCAT(e.first_name, ' ', e.last_name) as full_name
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN positions p ON e.position_id = p.position_id
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

// Get attendance records for the selected month
$stmt = $conn->prepare("
    SELECT ar.*, 
           e.first_name, e.last_name, e.employee_id,
           d.department_name, p.position_name
    FROM attendance_records ar
    LEFT JOIN employees e ON ar.employee_id = e.employee_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN positions p ON e.position_id = p.position_id
    WHERE ar.employee_id = ? 
    AND MONTH(ar.date) = ? 
    AND YEAR(ar.date) = ?
    AND ar.deleted_at IS NULL 
    ORDER BY ar.date ASC
");
$stmt->execute([$employeeId, $month, $year]);
$attendanceRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate attendance statistics
$totalDays = 0;
$presentDays = 0;
$lateDays = 0;
$absentDays = 0;
$onLeaveDays = 0;

foreach ($attendanceRecords as $record) {
    $totalDays++;
    switch ($record['status']) {
        case 'Present':
            $presentDays++;
            break;
        case 'Late':
            $lateDays++;
            break;
        case 'Absent':
            $absentDays++;
            break;
        case 'On Leave':
            $onLeaveDays++;
            break;
    }
}

// Get all months for the dropdown
$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

// Get years for the dropdown (current year and 2 years back)
$currentYear = intval(date('Y'));
$years = range($currentYear - 2, $currentYear);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Report | EmployeeTrack Pro</title>
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
        <!-- Report Header -->
        <div class="bg-gradient-to-r from-primary to-blue-800 rounded-2xl shadow-lg p-8 mb-8">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-6">
                    <div class="relative">
                        <?php
                        $profilePic = $employee['profile_picture'] ?? '';
                        if (!$profilePic || !file_exists('../../' . $profilePic)) {
                        ?>
                            <div class="w-24 h-24 rounded-full bg-white flex items-center justify-center shadow-lg">
                                <i class="fas fa-user text-4xl text-primary"></i>
                            </div>
                        <?php
                        } else {
                        ?>
                            <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profile Picture" 
                                 class="w-24 h-24 rounded-full object-cover border-4 border-white shadow-lg">
                        <?php } ?>
                    </div>
                    <div class="text-white">
                        <h1 class="text-2xl font-bold mb-2"><?= htmlspecialchars($employee['full_name']) ?></h1>
                        <div class="space-y-1">
                            <p class="flex items-center">
                                <i class="fas fa-briefcase w-6"></i>
                                <?= htmlspecialchars($employee['position_name']) ?>
                            </p>
                            <p class="flex items-center">
                                <i class="fas fa-building w-6"></i>
                                <?= htmlspecialchars($employee['department_name']) ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="flex space-x-4">
                    <a href="view.php?id=<?= $employee['employee_id'] ?>" 
                       class="bg-white text-primary px-6 py-2 rounded-lg font-semibold hover:bg-gray-50 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Profile
                    </a>
                </div>
            </div>
        </div>

        <div class="container mx-auto">
            <!-- Month Selection -->
            <div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
                <form method="GET" class="flex items-center space-x-4">
                    <input type="hidden" name="id" value="<?= $employee['employee_id'] ?>">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Select Month</label>
                        <select name="month" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50">
                            <?php foreach ($months as $num => $name): ?>
                                <option value="<?= $num ?>" <?= $num == $month ? 'selected' : '' ?>>
                                    <?= $name ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Select Year</label>
                        <select name="year" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50">
                            <?php foreach ($years as $y): ?>
                                <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>>
                                    <?= $y ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="bg-primary text-white px-6 py-2 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                            <i class="fas fa-search mr-2"></i>View Report
                        </button>
                    </div>
                </form>
            </div>

            <!-- Attendance Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-2xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Total Days</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?= $totalDays ?></h3>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-calendar-check text-xl text-primary"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-2xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Present Days</p>
                            <h3 class="text-2xl font-bold text-green-600"><?= $presentDays ?></h3>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-user-check text-xl text-green-600"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-2xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Late Days</p>
                            <h3 class="text-2xl font-bold text-yellow-600"><?= $lateDays ?></h3>
                        </div>
                        <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-clock text-xl text-yellow-600"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-2xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Absent Days</p>
                            <h3 class="text-2xl font-bold text-red-600"><?= $absentDays ?></h3>
                        </div>
                        <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-user-times text-xl text-red-600"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Attendance Records -->
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b">
                    <h2 class="text-xl font-semibold text-gray-800">Attendance Records</h2>
                </div>
                <div class="p-6">
                    <?php if (empty($attendanceRecords)): ?>
                        <p class="text-gray-500 text-center py-4">No attendance records found for the selected month</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Day</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time In</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time Out</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Photo</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Clock-in Location</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Clock-out Location</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remarks</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($attendanceRecords as $record): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?= date('F j, Y', strtotime($record['date'])) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= date('l', strtotime($record['date'])) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= $record['time_in'] ? date('h:i A', strtotime($record['time_in'])) : '-' ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= $record['time_out'] ? date('h:i A', strtotime($record['time_out'])) : '-' ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-3 py-1 rounded-full text-sm font-medium
                                                    <?= $record['status'] === 'Present' ? 'bg-green-100 text-green-800' : 
                                                        ($record['status'] === 'Late' ? 'bg-yellow-100 text-yellow-800' : 
                                                        ($record['status'] === 'On Leave' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800')) ?>">
                                                    <?= $record['status'] ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if ($record['photo_path']): ?>
                                                    <?php 
                                                    $photoPath = '../../' . $record['photo_path'];
                                                    $webPhotoPath = '/employeeYA/' . ltrim($record['photo_path'], '/');
                                                    if (file_exists($photoPath)): 
                                                    ?>
                                                        <img src="<?= htmlspecialchars($webPhotoPath) ?>" 
                                                             alt="Clock-in Photo" 
                                                             class="w-16 h-16 object-cover rounded-lg cursor-pointer"
                                                             onclick="showPhotoModal('<?= htmlspecialchars($webPhotoPath) ?>')">
                                                    <?php else: ?>
                                                        <span class="text-gray-400">Photo not found</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-gray-400">No photo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500">
                                                <?php if ($record['clock_in_latitude'] && $record['clock_in_longitude']): ?>
                                                    <a href="https://www.google.com/maps?q=<?= $record['clock_in_latitude'] ?>,<?= $record['clock_in_longitude'] ?>" 
                                                       target="_blank" 
                                                       class="text-primary hover:text-blue-700">
                                                        <i class="fas fa-map-marker-alt"></i> View Location
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-gray-400">No location data</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500">
                                                <?php if ($record['clock_out_latitude'] && $record['clock_out_longitude']): ?>
                                                    <a href="https://www.google.com/maps?q=<?= $record['clock_out_latitude'] ?>,<?= $record['clock_out_longitude'] ?>" 
                                                       target="_blank" 
                                                       class="text-primary hover:text-blue-700">
                                                        <i class="fas fa-map-marker-alt"></i> View Location
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-gray-400">No location data</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500">
                                                <?= $record['remarks'] ?? '-' ?>
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

    <!-- Add this before the closing body tag -->
    <div id="photoModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white p-4 rounded-lg max-w-2xl w-full mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Clock-in Photo</h3>
                <button onclick="closePhotoModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <img id="modalPhoto" src="" alt="Clock-in Photo" class="w-full h-auto rounded-lg">
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function showPhotoModal(photoPath) {
        console.log('Modal photo path:', photoPath); // Debug output
        const modal = document.getElementById('photoModal');
        const modalPhoto = document.getElementById('modalPhoto');
        modalPhoto.src = photoPath;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closePhotoModal() {
        const modal = document.getElementById('photoModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    // Close modal when clicking outside
    document.getElementById('photoModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closePhotoModal();
        }
    });

    // Add error handling for images
    document.querySelectorAll('img').forEach(img => {
        img.onerror = function() {
            this.onerror = null;
            this.src = '../../assets/img/no-image.png'; // Fallback image
        };
    });
    </script>
</body>
</html> 