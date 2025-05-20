<?php
require_once '../../auth/check_login.php';
require_once '../../config/dbcon.php';

// Only Admin and HR can access this page
if (!hasRole('Admin') && !hasRole('HR')) {
    header('Location: ../dashboard.php');
    exit();
}

// Check if employee ID is provided
if (!isset($_GET['id'])) {
    header('Location: list.php');
    exit();
}

$employee_id = $_GET['id'];

// Get employee data
$stmt = $conn->prepare("
    SELECT 
        e.*,
        u.username,
        u.role_id,
        u.email as user_email
    FROM employees e
    LEFT JOIN users u ON e.user_id = u.user_id
    WHERE e.employee_id = ? AND e.deleted_at IS NULL
");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    header('Location: list.php');
    exit();
}

// Get departments and positions for dropdowns
$stmt = $conn->prepare("SELECT * FROM departments WHERE deleted_at IS NULL ORDER BY department_name");
$stmt->execute();
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT * FROM positions WHERE deleted_at IS NULL ORDER BY position_name");
$stmt->execute();
$positions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Employee | EmployeeTrack Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
    <style>
        .form-section {
            background: #fff;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
        }
        
        .form-section-header {
            background: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            padding: 1rem 1.35rem;
            border-radius: 0.5rem 0.5rem 0 0;
        }
        
        .form-section-body {
            padding: 1.35rem;
        }
        
        .main-content {
            padding: 2rem;
            transition: margin-left 0.3s;
        }
        
        .required-field::after {
            content: " *";
            color: #dc3545;
        }

        .profile-picture-container {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto 1.5rem;
        }

        .profile-picture {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid #fff;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        .profile-picture-upload {
            position: absolute;
            bottom: 0;
            right: 0;
            background: var(--primary-color);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .profile-picture-upload:hover {
            transform: scale(1.1);
        }

        .profile-picture-upload input {
            display: none;
        }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-0">Edit Employee</h2>
                    <p class="text-muted mb-0">Update employee information</p>
                </div>
                <a href="list.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>

            <form id="editEmployeeForm" class="needs-validation" novalidate>
                <input type="hidden" name="employee_id" value="<?= htmlspecialchars($employee['employee_id']) ?>">
                
                <!-- Profile Picture -->
                <div class="form-section text-center">
                    <div class="form-section-body">
                        <div class="profile-picture-container">
                            <img src="<?= $employee['profile_picture'] ?? '../../assets/img/default-profile.jpg' ?>" 
                                 alt="Profile Picture" 
                                 class="profile-picture"
                                 id="profilePreview">
                            <label class="profile-picture-upload" title="Change Profile Picture">
                                <i class="fas fa-camera"></i>
                                <input type="file" name="profile_picture" accept="image/*" id="profilePictureInput">
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Personal Information -->
                <div class="form-section">
                    <div class="form-section-header">
                        <h5 class="mb-0">Personal Information</h5>
                    </div>
                    <div class="form-section-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label required-field">First Name</label>
                                <input type="text" class="form-control" name="first_name" 
                                       value="<?= htmlspecialchars($employee['first_name'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Middle Name</label>
                                <input type="text" class="form-control" name="middle_name"
                                       value="<?= htmlspecialchars($employee['middle_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label required-field">Last Name</label>
                                <input type="text" class="form-control" name="last_name"
                                       value="<?= htmlspecialchars($employee['last_name'] ?? '') ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label required-field">Email</label>
                                <input type="email" class="form-control" name="email"
                                       value="<?= htmlspecialchars($employee['email'] ?? $employee['user_email'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label required-field">Contact Number</label>
                                <input type="tel" class="form-control" name="contact_number"
                                       value="<?= htmlspecialchars($employee['contact_number'] ?? '') ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label required-field">Birth Date</label>
                                <input type="date" class="form-control datepicker" name="birth_date"
                                       value="<?= htmlspecialchars($employee['birth_date'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label required-field">Gender</label>
                                <select class="form-select" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male" <?= ($employee['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                                    <option value="Female" <?= ($employee['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                    <option value="Other" <?= ($employee['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label required-field">Address</label>
                            <textarea class="form-control" name="address" rows="3" required><?= htmlspecialchars($employee['address'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Employment Information -->
                <div class="form-section">
                    <div class="form-section-header">
                        <h5 class="mb-0">Employment Information</h5>
                    </div>
                    <div class="form-section-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label required-field">Department</label>
                                <select class="form-select" name="department_id" required>
                                    <option value="">Select Department</option>
                                    <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['department_id'] ?>"
                                            <?= ($employee['department_id'] ?? '') == $dept['department_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept['department_name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label required-field">Position</label>
                                <select class="form-select" name="position_id" required>
                                    <option value="">Select Position</option>
                                    <?php foreach ($positions as $pos): ?>
                                    <option value="<?= $pos['position_id'] ?>"
                                            <?= ($employee['position_id'] ?? '') == $pos['position_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($pos['position_name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label required-field">Employment Status</label>
                                <select class="form-select" name="employment_status" required>
                                    <option value="">Select Status</option>
                                    <option value="Probationary" <?= ($employee['employment_status'] ?? '') === 'Probationary' ? 'selected' : '' ?>>Probationary</option>
                                    <option value="Regular" <?= ($employee['employment_status'] ?? '') === 'Regular' ? 'selected' : '' ?>>Regular</option>
                                    <option value="Contractual" <?= ($employee['employment_status'] ?? '') === 'Contractual' ? 'selected' : '' ?>>Contractual</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label required-field">Hire Date</label>
                                <input type="date" class="form-control datepicker" name="hire_date"
                                       value="<?= htmlspecialchars($employee['hire_date'] ?? '') ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label required-field">Base Salary</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" class="form-control" name="base_salary" step="0.01"
                                       value="<?= htmlspecialchars($employee['base_salary'] ?? '') ?>" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- User Account Information -->
                <div class="form-section">
                    <div class="form-section-header">
                        <h5 class="mb-0">User Account Information</h5>
                    </div>
                    <div class="form-section-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label required-field">Username</label>
                                <input type="text" class="form-control" name="username"
                                       value="<?= htmlspecialchars($employee['username'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" name="password" 
                                       placeholder="Leave blank to keep current password">
                                <small class="text-muted">Only fill this if you want to change the password</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label required-field">Role</label>
                            <select class="form-select" name="role_id" required>
                                <option value="">Select Role</option>
                                <option value="2" <?= ($employee['role_id'] ?? '') == 2 ? 'selected' : '' ?>>Employee</option>
                                <?php if (hasRole('HR')): ?>
                                <option value="3" <?= ($employee['role_id'] ?? '') == 3 ? 'selected' : '' ?>>HR</option>
                                <option value="4" <?= ($employee['role_id'] ?? '') == 4 ? 'selected' : '' ?>>Manager</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='list.php'">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Employee
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script>
        // Initialize date pickers
        flatpickr(".datepicker", {
            dateFormat: "Y-m-d"
        });

        // Profile picture preview
        document.getElementById('profilePictureInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profilePreview').src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });

        // Form validation and submission
        document.getElementById('editEmployeeForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            if (!e.target.checkValidity()) {
                e.target.classList.add('was-validated');
                return;
            }

            const formData = new FormData(e.target);
            
            try {
                const response = await fetch('../../api/employees/update.php', {
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
                        text: 'Employee information has been updated successfully.',
                        icon: 'success',
                        confirmButtonColor: '#1cc88a'
                    });
                    window.location.href = 'list.php';
                } else {
                    throw new Error(result.error || 'Failed to update employee');
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error',
                    text: error.message || 'Failed to update employee',
                    icon: 'error',
                    confirmButtonColor: '#e74a3b'
                });
            }
        });
    </script>
</body>
</html> 