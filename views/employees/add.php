<?php
require_once '../../auth/check_login.php';
require_once '../../config/dbcon.php';

// Only Admin and HR can access this page
if (!hasRole('Admin') && !hasRole('HR')) {
    header('Location: ../dashboard.php');
    exit();
}

// Get departments and positions for dropdowns
$stmt = $conn->prepare("SELECT * FROM departments WHERE deleted_at IS NULL ORDER BY department_name");
$stmt->execute();
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT * FROM positions WHERE deleted_at IS NULL ORDER BY position_name");
$stmt->execute();
$positions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get leave types for initial leave balance
$stmt = $conn->prepare("SELECT * FROM leave_types WHERE deleted_at IS NULL");
$stmt->execute();
$leaveTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Employee | EmployeeTrack Pro</title>
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
            padding-left: 60px;
            padding-right: 60px;
            padding-top: 30px;
            transition: margin-left 0.3s;
        }
        
        .required-field::after {
            content: " *";
            color: #dc3545;
        }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container py-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Add New Employee</h2>
                <a href="list.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>

            <form id="addEmployeeForm" class="needs-validation" novalidate method="POST" enctype="multipart/form-data">
                <!-- Personal Information -->
                <div class="form-section">
                    <div class="form-section-header">
                        <h5 class="mb-0">Personal Information</h5>
                    </div>
                    <div class="form-section-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label required-field">First Name</label>
                                <input type="text" class="form-control" name="first_name" required maxlength="50">
                                <div class="invalid-feedback">
                                    First name is required.
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Middle Name</label>
                                <input type="text" class="form-control" name="middle_name" maxlength="50">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label required-field">Last Name</label>
                                <input type="text" class="form-control" name="last_name" required maxlength="50">
                                <div class="invalid-feedback">
                                    Last name is required.
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label required-field">Email</label>
                                <input type="email" class="form-control" name="email" required>
                                <div class="invalid-feedback">
                                    Please enter a valid email address.
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label required-field">Contact Number</label>
                                <input type="tel" class="form-control" name="contact_number" required>
                                <div class="invalid-feedback">
                                    Please enter contact number.
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label required-field">Birth Date</label>
                                <input type="date" class="form-control datepicker" name="birth_date" required>
                                <div class="invalid-feedback">
                                    Please select birth date.
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label required-field">Gender</label>
                                <select class="form-select" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                                <div class="invalid-feedback">
                                    Please select gender.
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label required-field">Address</label>
                            <textarea class="form-control" name="address" rows="3" required></textarea>
                            <div class="invalid-feedback">
                                Please enter address.
                            </div>
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
                                    <option value="<?= htmlspecialchars($dept['department_id']) ?>">
                                        <?= htmlspecialchars($dept['department_name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">
                                    Department is required.
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label required-field">Position</label>
                                <select class="form-select" name="position_id" required>
                                    <option value="">Select Position</option>
                                    <?php foreach ($positions as $pos): ?>
                                    <option value="<?= htmlspecialchars($pos['position_id']) ?>" data-salary="<?= htmlspecialchars($pos['base_salary']) ?>">
                                        <?= htmlspecialchars($pos['position_name']) ?> (₱<?= number_format($pos['base_salary'], 2) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">
                                    Position is required.
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label required-field">Employment Status</label>
                                <select class="form-select" name="employment_status" required>
                                    <option value="">Select Status</option>
                                    <option value="Probationary">Probationary</option>
                                    <option value="Regular">Regular</option>
                                    <option value="Contractual">Contractual</option>
                                    <option value="Resigned">Resigned</option>
                                    <option value="Terminated">Terminated</option>
                                </select>
                                <div class="invalid-feedback">
                                    Employment status is required.
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label required-field">Hire Date</label>
                                <input type="date" class="form-control datepicker" name="hire_date" required>
                                <div class="invalid-feedback">
                                    Hire date is required.
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Profile Picture</label>
                            <input type="file" class="form-control" name="profile_picture" accept="image/*">
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
                                <input type="text" class="form-control" name="username" required>
                                <div class="invalid-feedback">
                                    Please enter username.
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label required-field">Password</label>
                                <input type="password" class="form-control" name="password" required>
                                <div class="invalid-feedback">
                                    Please enter password.
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label required-field">Role</label>
                            <select class="form-select" name="role_id" required>
                                <option value="">Select Role</option>
                                <option value="2">Employee</option>
                                <?php if (hasRole('Admin')): ?>
                                <option value="1">Admin</option>
                                <option value="3">HR</option>
                                <option value="4">Manager</option>
                                <?php endif; ?>
                            </select>
                            <div class="invalid-feedback">
                                Please select role.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='list.php'">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Employee
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

        // Form validation and submission
        document.getElementById('addEmployeeForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const form = e.target;
            if (!form.checkValidity()) {
                e.stopPropagation();
                form.classList.add('was-validated');
                
                // Find the first invalid field and focus it
                const firstInvalid = form.querySelector(':invalid');
                if (firstInvalid) {
                    firstInvalid.focus();
                }
                
                return;
            }
            
            // Disable submit button to prevent double submission
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            
            try {
                const formData = new FormData(form);
                const data = {};
                
                // Convert FormData to JSON object
                for (const [key, value] of formData.entries()) {
                    data[key] = value;
                }
                
                // Get base salary from selected position
                const positionSelect = form.querySelector('select[name="position_id"]');
                const selectedOption = positionSelect.options[positionSelect.selectedIndex];
                data.base_salary = selectedOption.dataset.salary;
                
                // Validate required fields
                const requiredFields = [
                    'first_name', 'last_name', 'email', 'contact_number', 'birth_date',
                    'gender', 'address', 'department_id', 'position_id', 'employment_status',
                    'hire_date', 'username', 'password', 'role_id'
                ];
                
                for (const field of requiredFields) {
                    if (!data[field]) {
                        throw new Error(`Missing required field: ${field}`);
                    }
                }
                
                const response = await fetch('../../api/employees/add.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': '<?php echo $_SESSION['csrf_token']; ?>'
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                if (result.success) {
                    await Swal.fire({
                        title: 'Success!',
                        text: 'Employee added successfully!',
                        icon: 'success'
                    });
                    window.location.href = 'list.php';
                } else {
                    throw new Error(result.error || 'Failed to add employee');
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error',
                    text: error.message || 'Failed to add employee',
                    icon: 'error'
                });
            } finally {
                // Re-enable submit button
                submitBtn.disabled = false;
            }
        });
    </script>
</body>
</html>
