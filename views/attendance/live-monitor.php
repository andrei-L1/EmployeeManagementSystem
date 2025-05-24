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
        ar.photo_path
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
                                        <?php endif; ?>
                                        <?php if ($employee['time_out']): ?>
                                            <div>Out: <?= date('h:i A', strtotime($employee['time_out'])) ?></div>
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
            timeDisplay.innerHTML = `<div>In: ${new Date(data.time_in).toLocaleTimeString()}</div>`;
        } else {
            timeDisplay.innerHTML += `<div>Out: ${new Date(data.time_out).toLocaleTimeString()}</div>`;
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
    });

    // Listen for clock-out events
    channel.bind('clock-out-event', function(data) {
        updateEmployeeCard(data.employee_id, data, false);
        updateStats();
    });

    // Initial stats update
    updateStats();
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