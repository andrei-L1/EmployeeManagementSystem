<?php
require_once '../../auth/check_login.php';
require_once '../../config/dbcon.php';

// Check if user has HR or Manager role
if (!hasRole('HR') && !hasRole('Manager')) {
    header('Location: ../dashboard.php');
    exit();
}

// Get department filter if user is a manager
$departmentFilter = '';
if (hasRole('Manager')) {
    $departmentFilter = "AND e.department_id = " . $_SESSION['user_data']['employee_data']['department_id'];
}

// Get current attendance status for all employees
$stmt = $conn->prepare("
    SELECT 
        e.employee_id,
        e.first_name,
        e.last_name,
        d.department_name,
        p.position_name,
        ar.time_in,
        ar.time_out,
        ar.status,
        ar.date,
        ar.photo_path,
        ar.clock_in_latitude,
        ar.clock_in_longitude,
        ar.clock_out_latitude,
        ar.clock_out_longitude
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN positions p ON e.position_id = p.position_id
    LEFT JOIN attendance_records ar ON e.employee_id = ar.employee_id 
        AND ar.date = CURDATE()
    WHERE e.deleted_at IS NULL
    $departmentFilter
    ORDER BY d.department_name, e.last_name, e.first_name
");
$stmt->execute();
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Attendance Monitor | EmployeeTrack Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <style>
        .main-content {
            padding-left: 60px;
            padding-right: 60px;
            padding-top: 30px;
            transition: margin-left 0.3s;
        }
        .attendance-card {
            transition: all 0.3s ease;
        }
        .attendance-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
        }
        .time-display {
            font-size: 0.9rem;
            color: #666;
        }
        .department-header {
            background-color: #f8f9fa;
            padding: 0.5rem 1rem;
            margin: 1rem 0;
            border-radius: 4px;
        }
        #attendanceStats {
            position: sticky;
            top: 1rem;
            z-index: 100;
        }

        /* Dark mode styles */
        [data-bs-theme="dark"] {
            --bs-body-bg: #1a1d20;
            --bs-body-color: #e9ecef;
        }

        [data-bs-theme="dark"] .card {
            background-color: #2c3034;
            border-color: #373b3e;
        }

        [data-bs-theme="dark"] .department-header {
            background-color: #2c3034;
            color: #e9ecef;
        }

        [data-bs-theme="dark"] .time-display {
            color: #adb5bd;
        }

        [data-bs-theme="dark"] .form-control,
        [data-bs-theme="dark"] .form-select {
            background-color: #2c3034;
            border-color: #373b3e;
            color: #e9ecef;
        }

        [data-bs-theme="dark"] .form-control:focus,
        [data-bs-theme="dark"] .form-select:focus {
            background-color: #2c3034;
            border-color: #0d6efd;
            color: #e9ecef;
        }

        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
            }
            
            .attendance-card {
                margin-bottom: 1rem;
            }

            .stats-card {
                margin-bottom: 1rem;
            }

            .chart-container {
                height: 300px;
                margin-bottom: 1rem;
            }

            #attendanceStats {
                position: static;
            }

            .search-filters {
                flex-direction: column;
            }

            .search-filters > div {
                margin-bottom: 0.5rem;
            }
        }
    </style>
</head>
<body>
<?php include '../../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Live Attendance Monitor</h2>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary" id="themeToggle">
                            <i class="fas fa-moon"></i>
                        </button>
                        <?php if (hasRole('HR')): ?>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-primary" id="exportBtn">
                                <i class="fas fa-download"></i> Export Report
                            </button>
                            <button type="button" class="btn btn-outline-primary" id="printBtn">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Search and Filters -->
                <div class="row mb-4 search-filters">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="searchInput" placeholder="Search employees...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="departmentFilter">
                            <option value="">All Departments</option>
                            <?php
                            $deptStmt = $conn->query("SELECT DISTINCT d.department_name FROM departments d 
                                                    JOIN employees e ON d.department_id = e.department_id 
                                                    WHERE e.deleted_at IS NULL ORDER BY d.department_name");
                            while ($dept = $deptStmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='" . htmlspecialchars($dept['department_name']) . "'>" . 
                                     htmlspecialchars($dept['department_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="statusFilter">
                            <option value="">All Status</option>
                            <option value="Present">Present</option>
                            <option value="Late">Late</option>
                            <option value="Absent">Absent</option>
                            <option value="On Leave">On Leave</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="autoRefresh" checked>
                            <label class="form-check-label" for="autoRefresh">Auto-refresh</label>
                        </div>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="row mb-4" id="attendanceStats">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Present Today</h5>
                                <h2 class="mb-0" id="presentCount">0</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <h5 class="card-title">Late Today</h5>
                                <h2 class="mb-0" id="lateCount">0</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <h5 class="card-title">Absent Today</h5>
                                <h2 class="mb-0" id="absentCount">0</h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">On Leave</h5>
                                <h2 class="mb-0" id="leaveCount">0</h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Department-wise Attendance</h5>
                                <canvas id="departmentChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Today's Attendance Trend</h5>
                                <canvas id="trendChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Employee Cards -->
                <div class="row" id="employeeCards">
                    <?php
                    $currentDepartment = '';
                    foreach ($employees as $employee):
                        if ($currentDepartment !== $employee['department_name']):
                            $currentDepartment = $employee['department_name'];
                    ?>
                            <div class="col-12">
                                <h4 class="department-header"><?= htmlspecialchars($currentDepartment) ?></h4>
                            </div>
                    <?php endif; ?>
                        <div class="col-md-4 mb-3">
                            <div class="card attendance-card" id="employee-<?= $employee['employee_id'] ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="d-flex align-items-center mb-1">
                                            <div>
                                                <h5 class="card-title mb-1">
                                                    <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?>
                                                </h5>
                                                <p class="text-muted mb-2"><?= htmlspecialchars($employee['position_name']) ?></p>
                                            </div>
                                        </div>
                                        <span class="badge status-badge bg-<?= 
                                            $employee['status'] === 'Present' ? 'success' : 
                                            ($employee['status'] === 'Late' ? 'warning' : 
                                            ($employee['status'] === 'On Leave' ? 'info' : 'secondary')) 
                                        ?>">
                                            <?= $employee['status'] ?: 'Not Clocked In' ?>
                                        </span>
                                    </div>
                                    <div class="time-display">
                                        <?php if ($employee['time_in']): ?>
                                            <div>In: <?= date('h:i A', strtotime($employee['time_in'])) ?></div>
                                            <?php if ($employee['clock_in_latitude'] && $employee['clock_in_longitude']): ?>
                                                <div class="mt-1">
                                                    <a href="https://www.google.com/maps?q=<?= $employee['clock_in_latitude'] ?>,<?= $employee['clock_in_longitude'] ?>" 
                                                       target="_blank" 
                                                       class="text-primary text-decoration-none">
                                                        <i class="fas fa-map-marker-alt"></i> Clock-in Location
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if ($employee['time_out']): ?>
                                            <div>Out: <?= date('h:i A', strtotime($employee['time_out'])) ?></div>
                                            <?php if ($employee['clock_out_latitude'] && $employee['clock_out_longitude']): ?>
                                                <div class="mt-1">
                                                    <a href="https://www.google.com/maps?q=<?= $employee['clock_out_latitude'] ?>,<?= $employee['clock_out_longitude'] ?>" 
                                                       target="_blank" 
                                                       class="text-primary text-decoration-none">
                                                        <i class="fas fa-map-marker-alt"></i> Clock-out Location
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php
                                    $hasPhoto = $employee['photo_path'] && $employee['time_in'];
                                    $photoPath = $hasPhoto
                                        ? '/employeeYA/' . ltrim($employee['photo_path'], '/')
                                        : '';
                                    ?>
                                    <?php if ($hasPhoto): ?>
                                        <button class="btn btn-sm btn-primary mt-2" onclick="showPhotoModal('<?= htmlspecialchars($photoPath) ?>')">
                                            View Photo
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Initialize Pusher
    const pusher = new Pusher('10ac21fee8c24f99545b', {
        cluster: 'ap1'
    });

    // Subscribe to attendance channel
    const channel = pusher.subscribe('attendance-channel');

    // Update stats counters
    function updateStats() {
        const cards = document.querySelectorAll('.attendance-card');
        let present = 0, late = 0, absent = 0, leave = 0;
        
        cards.forEach(card => {
            const status = card.querySelector('.badge').textContent.trim();
            switch(status) {
                case 'Present': present++; break;
                case 'Late': late++; break;
                case 'On Leave': leave++; break;
                default: absent++;
            }
        });

        document.getElementById('presentCount').textContent = present;
        document.getElementById('lateCount').textContent = late;
        document.getElementById('absentCount').textContent = absent;
        document.getElementById('leaveCount').textContent = leave;
    }

    // Update employee card
    function updateEmployeeCard(employeeId, data, isClockIn) {
        const card = document.getElementById(`employee-${employeeId}`);
        if (!card) return;

        const badge = card.querySelector('.badge');
        const timeDisplay = card.querySelector('.time-display');

        if (isClockIn) {
            badge.className = `badge status-badge bg-${data.status === 'Late' ? 'warning' : 'success'}`;
            badge.textContent = data.status;
            
            let timeHtml = `<div>In: ${new Date(data.time_in).toLocaleTimeString()}</div>`;
            
            // Add location if available
            if (data.clock_in_latitude && data.clock_in_longitude) {
                timeHtml += `
                    <div class="mt-1">
                        <a href="https://www.google.com/maps?q=${data.clock_in_latitude},${data.clock_in_longitude}" 
                           target="_blank" 
                           class="text-primary text-decoration-none">
                            <i class="fas fa-map-marker-alt"></i> Clock-in Location
                        </a>
                    </div>`;
            }
            
            timeDisplay.innerHTML = timeHtml;

            // Add photo button if available
            if (data.photo_path) {
                const photoPath = '/employeeYA/' + data.photo_path.replace(/^\//, '');
                const photoButton = document.createElement('button');
                photoButton.className = 'btn btn-sm btn-primary mt-2';
                photoButton.textContent = 'View Photo';
                photoButton.onclick = () => showPhotoModal(photoPath);
                timeDisplay.appendChild(photoButton);
            }
        } else {
            let timeHtml = timeDisplay.innerHTML;
            timeHtml += `<div>Out: ${new Date(data.time_out).toLocaleTimeString()}</div>`;
            
            // Add location if available
            if (data.clock_out_latitude && data.clock_out_longitude) {
                timeHtml += `
                    <div class="mt-1">
                        <a href="https://www.google.com/maps?q=${data.clock_out_latitude},${data.clock_out_longitude}" 
                           target="_blank" 
                           class="text-primary text-decoration-none">
                            <i class="fas fa-map-marker-alt"></i> Clock-out Location
                        </a>
                    </div>`;
            }
            
            timeDisplay.innerHTML = timeHtml;
        }
    }

    // Handle export button
    document.getElementById('exportBtn').addEventListener('click', async () => {
        try {
            const response = await fetch('../../api/attendance/export.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': '<?php echo $_SESSION['csrf_token']; ?>'
                }
            });
            
            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `attendance-report-${new Date().toISOString().split('T')[0]}.xlsx`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                a.remove();
                
                await Swal.fire({
                    title: 'Success!',
                    text: 'Attendance report exported successfully',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                throw new Error('Failed to export report');
            }
        } catch (error) {
            console.error('Error:', error);
            Swal.fire({
                title: 'Error',
                text: error.message || 'Failed to export attendance report',
                icon: 'error'
            });
        }
    });

    // Handle print button
    document.getElementById('printBtn').addEventListener('click', () => {
        window.print();
    });

    // Listen for clock-in events
    channel.bind('clock-in-event', function(data) {
        updateEmployeeCard(data.employee_id, data, true);
        updateStats();
        updateTrendData(data.time_in);
    });

    // Listen for clock-out events
    channel.bind('clock-out-event', function(data) {
        updateEmployeeCard(data.employee_id, data, false);
        updateStats();
    });

    // Update trend data for a specific time
    function updateTrendData(timeIn) {
        const hour = new Date(timeIn).getHours();
        const trendChart = window.charts.trendChart;
        trendChart.data.datasets[0].data[hour]++;
        trendChart.update();
    }

    // Initial stats update
    updateStats();

    // Search and filter functionality
    function filterEmployees() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const departmentFilter = document.getElementById('departmentFilter').value;
        const statusFilter = document.getElementById('statusFilter').value;
        
        const cards = document.querySelectorAll('.attendance-card');
        cards.forEach(card => {
            const employeeName = card.querySelector('.card-title').textContent.toLowerCase();
            const department = card.closest('.col-12').querySelector('.department-header')?.textContent || '';
            const status = card.querySelector('.badge').textContent.trim();
            
            const matchesSearch = employeeName.includes(searchTerm);
            const matchesDepartment = !departmentFilter || department === departmentFilter;
            const matchesStatus = !statusFilter || status === statusFilter;
            
            card.closest('.col-md-4').style.display = 
                matchesSearch && matchesDepartment && matchesStatus ? 'block' : 'none';
        });
    }

    // Auto-refresh functionality
    let autoRefreshInterval;
    function toggleAutoRefresh() {
        const autoRefresh = document.getElementById('autoRefresh');
        if (autoRefresh.checked) {
            autoRefreshInterval = setInterval(() => {
                location.reload();
            }, 30000); // Refresh every 30 seconds
        } else {
            clearInterval(autoRefreshInterval);
        }
        // Save preference to localStorage
        localStorage.setItem('autoRefresh', autoRefresh.checked);
    }

    // Event listeners
    document.getElementById('searchInput').addEventListener('input', filterEmployees);
    document.getElementById('departmentFilter').addEventListener('change', filterEmployees);
    document.getElementById('statusFilter').addEventListener('change', filterEmployees);
    document.getElementById('autoRefresh').addEventListener('change', toggleAutoRefresh);

    // Initialize auto-refresh from localStorage
    const savedAutoRefresh = localStorage.getItem('autoRefresh');
    if (savedAutoRefresh !== null) {
        document.getElementById('autoRefresh').checked = savedAutoRefresh === 'true';
    }
    toggleAutoRefresh();

    // Keyboard shortcuts
    document.addEventListener('keydown', (e) => {
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            document.getElementById('searchInput').focus();
        }
        if (e.ctrlKey && e.key === 'r') {
            e.preventDefault();
            location.reload();
        }
    });

    // Initialize charts
    function initializeCharts() {
        // Department-wise attendance chart
        const deptCtx = document.getElementById('departmentChart').getContext('2d');
        const deptData = {
            labels: [],
            datasets: [{
                label: 'Present',
                data: [],
                backgroundColor: 'rgba(40, 167, 69, 0.5)',
                borderColor: 'rgb(40, 167, 69)',
                borderWidth: 1
            }, {
                label: 'Late',
                data: [],
                backgroundColor: 'rgba(255, 193, 7, 0.5)',
                borderColor: 'rgb(255, 193, 7)',
                borderWidth: 1
            }, {
                label: 'Absent',
                data: [],
                backgroundColor: 'rgba(220, 53, 69, 0.5)',
                borderColor: 'rgb(220, 53, 69)',
                borderWidth: 1
            }]
        };

        const deptChart = new Chart(deptCtx, {
            type: 'bar',
            data: deptData,
            options: {
                responsive: true,
                scales: {
                    x: {
                        stacked: true
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true
                    }
                }
            }
        });

        // Attendance trend chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        const trendData = {
            labels: [],
            datasets: [{
                label: 'Clock-ins',
                data: [],
                borderColor: 'rgb(75, 192, 192)',
                tension: 0.1
            }]
        };

        const trendChart = new Chart(trendCtx, {
            type: 'line',
            data: trendData,
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        return { deptChart, trendChart };
    }

    // Update charts with data
    function updateCharts() {
        const cards = document.querySelectorAll('.attendance-card');
        const deptData = {};
        const trendData = new Array(24).fill(0);

        cards.forEach(card => {
            const department = card.closest('.col-12').querySelector('.department-header')?.textContent || 'Unknown';
            const status = card.querySelector('.badge').textContent.trim();
            const timeIn = card.querySelector('.time-display')?.textContent.match(/In: (\d{1,2}:\d{2} [AP]M)/)?.[1];

            // Update department data
            if (!deptData[department]) {
                deptData[department] = { Present: 0, Late: 0, Absent: 0 };
            }
            deptData[department][status]++;

            // Update trend data
            if (timeIn) {
                const hour = new Date(`2000-01-01 ${timeIn}`).getHours();
                trendData[hour]++;
            }
        });

        // Update department chart
        const deptChart = window.charts.deptChart;
        deptChart.data.labels = Object.keys(deptData);
        deptChart.data.datasets[0].data = Object.values(deptData).map(d => d.Present);
        deptChart.data.datasets[1].data = Object.values(deptData).map(d => d.Late);
        deptChart.data.datasets[2].data = Object.values(deptData).map(d => d.Absent);
        deptChart.update();

        // Update trend chart
        const trendChart = window.charts.trendChart;
        trendChart.data.labels = Array.from({length: 24}, (_, i) => `${i}:00`);
        trendChart.data.datasets[0].data = trendData;
        trendChart.update();
    }

    // Initialize charts on page load
    window.charts = initializeCharts();

    // Update charts when stats are updated
    const originalUpdateStats = updateStats;
    updateStats = function() {
        originalUpdateStats();
        updateCharts();
    };

    // Initial update
    updateStats();

    // Dark mode toggle
    function toggleDarkMode() {
        const html = document.documentElement;
        const currentTheme = html.getAttribute('data-bs-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-bs-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        
        const themeIcon = document.querySelector('#themeToggle i');
        themeIcon.className = newTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }

    // Initialize theme
    function initializeTheme() {
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
        const themeIcon = document.querySelector('#themeToggle i');
        themeIcon.className = savedTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }

    // Theme toggle event listener
    document.getElementById('themeToggle').addEventListener('click', toggleDarkMode);

    // Initialize theme on page load
    initializeTheme();
</script>

<!-- Add modal at the end of the file before </body> -->
<div id="photoModal" class="modal fade" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Clock-in Photo</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <img id="modalPhoto" src="" alt="Clock-in Photo" class="img-fluid rounded">
      </div>
    </div>
  </div>
</div>

<script>
function showPhotoModal(photoPath) {
    const modalPhoto = document.getElementById('modalPhoto');
    modalPhoto.src = photoPath;
    const modal = new bootstrap.Modal(document.getElementById('photoModal'));
    modal.show();
}
</script>
</body>
</html> 