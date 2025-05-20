<?php
require_once '../auth/check_login.php';
require_once '../config/dbcon.php';

// Get user data
$stmt = $conn->prepare("
    SELECT u.*, e.*, d.department_name, p.position_name, p.base_salary
    FROM users u
    JOIN employees e ON u.user_id = e.user_id
    JOIN departments d ON e.department_id = d.department_id
    JOIN positions p ON e.position_id = p.position_id
    WHERE u.user_id = ? AND u.deleted_at IS NULL
");
$stmt->execute([$_SESSION['user_data']['user_id']]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent attendance records
$stmt = $conn->prepare("
    SELECT * FROM attendance_records 
    WHERE employee_id = ? 
    AND deleted_at IS NULL 
    ORDER BY date DESC 
    LIMIT 5
");
$stmt->execute([$_SESSION['user_data']['employee_id']]);
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
$stmt->execute([$_SESSION['user_data']['employee_id']]);
$leaveBalances = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile | EmployeeTrack Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1a237e;
            --secondary-color: #283593;
            --success-color: #00c853;
            --info-color: #0091ea;
            --warning-color: #ffd600;
            --danger-color: #d50000;
            --light-color: #f5f7fa;
            --dark-color: #1a237e;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 0;
        }

        .profile-header {
            background: linear-gradient(135deg, #1a237e 0%, #283593 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23ffffff' fill-opacity='0.05' fill-rule='evenodd'/%3E%3C/svg%3E");
            opacity: 0.1;
        }
        
        .profile-picture {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            border: 5px solid rgba(255, 255, 255, 0.3);
            object-fit: cover;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            transition: transform 0.3s ease;
            background: #e9ecef;
            display: block;
            position: relative;
        }
        
        .default-profile-icon {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            border: 5px solid rgba(255, 255, 255, 0.3);
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            font-size: 5rem;
            color: #b0b3b8;
            transition: transform 0.3s ease;
        }
        
        .default-profile-icon:hover {
            transform: scale(1.05);
        }
        
        .profile-picture-container {
            position: relative;
            display: inline-block;
        }
        
        .profile-picture-upload {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: white;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }
        
        .profile-picture-upload:hover {
            background: var(--light-color);
            transform: scale(1.1);
        }
        
        .profile-picture-upload i {
            color: var(--primary-color);
            font-size: 1.2rem;
        }
        
        .info-card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(26, 35, 126, 0.08);
            transition: all 0.3s ease;
            background: white;
            margin-bottom: 1.5rem;
        }
        
        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 2rem 0 rgba(26, 35, 126, 0.12);
        }
        
        .info-card .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1.25rem 1.5rem;
            border-radius: 1rem 1rem 0 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .info-card .card-header h5 {
            margin: 0;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .info-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: background-color 0.3s ease;
        }
        
        .info-item:hover {
            background-color: var(--light-color);
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 0.25rem;
            font-size: 0.875rem;
        }
        
        .info-value {
            color: #2d3748;
            font-weight: 400;
        }
        
        .edit-btn {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .edit-btn:hover {
            transform: translateY(-2px);
        }
        
        .badge {
            padding: 0.5em 0.75em;
            font-weight: 500;
            border-radius: 0.5rem;
        }
        
        .badge.bg-success {
            background-color: #00c853 !important;
        }
        
        .badge.bg-info {
            background-color: #0091ea !important;
        }
        
        .badge.bg-warning {
            background-color: #ffd600 !important;
            color: #000;
        }
        
        .badge.bg-danger {
            background-color: #d50000 !important;
        }
        
        .modal-content {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.15);
        }
        
        .modal-header {
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
        }
        
        .form-control {
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.1);
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background-color: #1a237e;
            border-color: #1a237e;
        }
        
        .btn-primary:hover {
            background-color: #283593;
            border-color: #283593;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #545b62;
            transform: translateY(-2px);
        }

        .main-content {
            padding: 0;
            margin-top: 0;
        }

        @media (max-width: 768px) {
            .profile-header {
                padding: 2rem 0;
            }
            
            .profile-picture {
                width: 150px;
                height: 150px;
            }
            
            .info-card {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-3 text-center">
                        <div class="profile-picture-container">
                            <?php if (!empty($userData['profile_picture'])): ?>
                                <img src="<?= htmlspecialchars($userData['profile_picture']) ?>"
                                     alt="Profile Picture"
                                     class="profile-picture"
                                     id="profilePicture"
                                     onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.style.display='flex';">
                                <div class="default-profile-icon" style="display:none;"><i class="fas fa-user"></i></div>
                            <?php else: ?>
                                <div class="default-profile-icon"><i class="fas fa-user"></i></div>
                            <?php endif; ?>
                            <label class="profile-picture-upload" for="profilePictureInput">
                                <i class="fas fa-camera"></i>
                                <input type="file" id="profilePictureInput" accept="image/*" hidden>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-9">
                        <h2 class="mb-2"><?= htmlspecialchars($userData['first_name'] . ' ' . $userData['last_name']) ?></h2>
                        <p class="mb-1">
                            <i class="fas fa-briefcase me-2"></i>
                            <?= htmlspecialchars($userData['position_name']) ?>
                        </p>
                        <p class="mb-1">
                            <i class="fas fa-building me-2"></i>
                            <?= htmlspecialchars($userData['department_name']) ?>
                        </p>
                        <p class="mb-0">
                            <i class="fas fa-calendar-alt me-2"></i>
                            Joined <?= date('F Y', strtotime($userData['hire_date'])) ?>
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
                            <button class="btn btn-sm btn-primary edit-btn" data-section="personal">
                                <i class="fas fa-edit me-1"></i> Edit
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="info-item">
                                <div class="info-label">Full Name</div>
                                <div class="info-value"><?= htmlspecialchars($userData['first_name'] . ' ' . 
                                    ($userData['middle_name'] ? $userData['middle_name'] . ' ' : '') . 
                                    $userData['last_name']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Email</div>
                                <div class="info-value"><?= htmlspecialchars($userData['email']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Contact Number</div>
                                <div class="info-value"><?= htmlspecialchars($userData['contact_number'] ?? 'Not provided') ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Address</div>
                                <div class="info-value"><?= htmlspecialchars($userData['address'] ?? 'Not provided') ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Birth Date</div>
                                <div class="info-value"><?= $userData['birth_date'] ? date('F j, Y', strtotime($userData['birth_date'])) : 'Not provided' ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Gender</div>
                                <div class="info-value"><?= htmlspecialchars($userData['gender'] ?? 'Not provided') ?></div>
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
                                <div class="info-value"><?= htmlspecialchars($userData['employee_id']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Position</div>
                                <div class="info-value"><?= htmlspecialchars($userData['position_name']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Department</div>
                                <div class="info-value"><?= htmlspecialchars($userData['department_name']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Employment Status</div>
                                <div class="info-value">
                                    <span class="badge bg-<?= 
                                        $userData['employment_status'] === 'Regular' ? 'success' : 
                                        ($userData['employment_status'] === 'Probationary' ? 'warning' : 
                                        ($userData['employment_status'] === 'Contractual' ? 'info' : 'secondary')) 
                                    ?>">
                                        <?= htmlspecialchars($userData['employment_status']) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Hire Date</div>
                                <div class="info-value"><?= date('F j, Y', strtotime($userData['hire_date'])) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Base Salary</div>
                                <div class="info-value">₱<?= number_format($userData['base_salary'], 2) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Attendance -->
                <div class="col-md-6 mb-4">
                    <div class="card info-card">
                        <div class="card-header">
                            <h5 class="mb-0">Recent Attendance</h5>
                            <a href="attendance/record.php" class="btn btn-sm btn-primary edit-btn">
                                <i class="fas fa-list me-1"></i> View All
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
                                <div class="info-value">
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
                            <a href="leave/request.php" class="btn btn-sm btn-primary edit-btn">
                                <i class="fas fa-calendar-plus me-1"></i> Request Leave
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
                                <div class="info-value">
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
            </div>
        </div>
    </div>

    <!-- Edit Personal Information Modal -->
    <div class="modal fade" id="editPersonalModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Personal Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editPersonalForm">
                        <div class="mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="tel" class="form-control" name="contact_number" 
                                   value="<?= htmlspecialchars($userData['contact_number'] ?? '') ?>"
                                   pattern="[0-9]{11}" title="Please enter a valid 11-digit phone number">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="3"><?= htmlspecialchars($userData['address'] ?? '') ?></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="savePersonalBtn">
                        <i class="fas fa-save me-1"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script>
        // Initialize modals
        const editPersonalModal = new bootstrap.Modal(document.getElementById('editPersonalModal'));
        
        // Handle edit buttons
        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                if (btn.dataset.section === 'personal') {
                    editPersonalModal.show();
                }
            });
        });
        
        // Handle profile picture update
        document.getElementById('profilePictureInput').addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (file) {
                const formData = new FormData();
                formData.append('profile_picture', file);
                
                try {
                    const response = await fetch('../api/profile/update-picture.php', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-Token': '<?php echo $_SESSION['csrf_token']; ?>'
                        },
                        body: formData
                    });
                    
                    const result = await response.json();
                    if (result.success) {
                        await Swal.fire({
                            title: 'Success!',
                            text: 'Profile picture updated successfully',
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        });
                        window.location.reload();
                    } else {
                        throw new Error(result.error);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    Swal.fire({
                        title: 'Error',
                        text: error.message || 'Failed to update profile picture',
                        icon: 'error'
                    });
                }
            }
        });
        
        // Handle personal information update
        document.getElementById('savePersonalBtn').addEventListener('click', async () => {
            const form = document.getElementById('editPersonalForm');
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            
            try {
                const response = await fetch('../api/profile/update-personal.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': '<?php echo $_SESSION['csrf_token']; ?>'
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                if (result.success) {
                    editPersonalModal.hide();
                    await Swal.fire({
                        title: 'Success!',
                        text: 'Personal information updated successfully',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    window.location.reload();
                } else {
                    throw new Error(result.error);
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error',
                    text: error.message || 'Failed to update personal information',
                    icon: 'error'
                });
            }
        });
    </script>
</body>
</html> 