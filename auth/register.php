<?php
session_start();
require_once '../config/dbcon.php';

// Initialize variables
$errors = [];
$old = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize input
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role_id = $_POST['role_id'] ?? '';
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $birth_date = $_POST['birth_date'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $contact_number = trim($_POST['contact_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $hire_date = $_POST['hire_date'] ?? '';
    $position_id = $_POST['position_id'] ?? '';
    $department_id = $_POST['department_id'] ?? '';

    // Keep old values for repopulating form
    $old = [
        'username' => $username,
        'email' => $email,
        'role_id' => $role_id,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'middle_name' => $middle_name,
        'birth_date' => $birth_date,
        'gender' => $gender,
        'contact_number' => $contact_number,
        'address' => $address,
        'hire_date' => $hire_date,
        'position_id' => $position_id,
        'department_id' => $department_id,
    ];

    // Validate inputs
    if (!$username) {
        $errors['username'] = "Username is required.";
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $errors['username'] = "Username must be between 3 and 50 characters.";
    }

    if (!$email) {
        $errors['email'] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format.";
    }

    if (!$password) {
        $errors['password'] = "Password is required.";
    } elseif (strlen($password) < 6) {
        $errors['password'] = "Password must be at least 6 characters.";
    }

    if (!$role_id) {
        $errors['role_id'] = "Role selection is required.";
    } elseif (!ctype_digit($role_id) || !existsInTable($conn, 'roles', 'role_id', $role_id)) {
        $errors['role_id'] = "Invalid role selected.";
    }

    if (!$first_name) {
        $errors['first_name'] = "First name is required.";
    }

    if (!$last_name) {
        $errors['last_name'] = "Last name is required.";
    }

    if ($birth_date && !validateDate($birth_date)) {
        $errors['birth_date'] = "Invalid birth date format.";
    }

    $valid_genders = ['Male', 'Female', 'Other'];
    if (!$gender) {
        $errors['gender'] = "Gender is required.";
    } elseif (!in_array($gender, $valid_genders)) {
        $errors['gender'] = "Invalid gender selection.";
    }

    if ($contact_number && !preg_match('/^\+?[0-9\s\-]+$/', $contact_number)) {
        $errors['contact_number'] = "Invalid contact number format.";
    }

    if (!$hire_date) {
        $errors['hire_date'] = "Hire date is required.";
    } elseif (!validateDate($hire_date)) {
        $errors['hire_date'] = "Invalid hire date format.";
    }

    if (!$position_id) {
        $errors['position_id'] = "Position selection is required.";
    } elseif (!ctype_digit($position_id) || !existsInTable($conn, 'positions', 'position_id', $position_id)) {
        $errors['position_id'] = "Invalid position selected.";
    }

    if (!$department_id) {
        $errors['department_id'] = "Department selection is required.";
    } elseif (!ctype_digit($department_id) || !existsInTable($conn, 'departments', 'department_id', $department_id)) {
        $errors['department_id'] = "Invalid department selected.";
    }

    if (empty($errors)) {
        // Insert data
        try {
            $conn->beginTransaction();

            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmtUser = $conn->prepare("INSERT INTO users (username, password_hash, email, role_id) VALUES (?, ?, ?, ?)");
            $stmtUser->execute([$username, $password_hash, $email, $role_id]);

            $user_id = $conn->lastInsertId();

            $stmtEmp = $conn->prepare("INSERT INTO employees 
                (user_id, first_name, last_name, middle_name, birth_date, gender, contact_number, address, hire_date, position_id, department_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmtEmp->execute([
                $user_id,
                $first_name,
                $last_name,
                $middle_name ?: null,
                $birth_date ?: null,
                $gender,
                $contact_number ?: null,
                $address ?: null,
                $hire_date,
                $position_id,
                $department_id
            ]);

            $conn->commit();

            $success = "Employee registered successfully.";
            $old = []; 
            header("Location: ../index.php?registered=1");
            exit;


        } catch (PDOException $e) {
            $conn->rollBack();
            $errors['general'] = "Database error: " . $e->getMessage();
        }
    }
}

$roles = $conn->query("SELECT role_id, role_name FROM roles")->fetchAll(PDO::FETCH_ASSOC);
$positions = $conn->query("SELECT position_id, position_name FROM positions")->fetchAll(PDO::FETCH_ASSOC);
$departments = $conn->query("SELECT department_id, department_name FROM departments")->fetchAll(PDO::FETCH_ASSOC);

function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function existsInTable($conn, $table, $column, $value) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM $table WHERE $column = ?");
    $stmt->execute([$value]);
    return $stmt->fetchColumn() > 0;
}

function oldValue($old, $key) {
    return htmlspecialchars($old[$key] ?? '');
}

function errorText($errors, $key) {
    return $errors[$key] ?? '';
}

function isInvalidClass($errors, $key) {
    return isset($errors[$key]) ? 'is-invalid' : '';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .registration-container {
            max-width: 800px;
            margin: 30px auto;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .card-header {
            background-color: #4e73df;
            color: white;
            padding: 15px;
            border-radius: 8px 8px 0 0;
        }
        
        .card-body {
            padding: 25px;
            background-color: white;
            border-radius: 0 0 8px 8px;
        }
        
        .step-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 30px;
        }
        
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #e9ecef;
            color: #495057;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            position: relative;
        }
        
        .step.active {
            background-color: #4e73df;
            color: white;
        }
        
        .step.completed {
            background-color: #1cc88a;
            color: white;
        }
        
        .step-line {
            height: 2px;
            width: 100px;
            background-color: #e9ecef;
        }
        
        .step-line.completed {
            background-color: #1cc88a;
        }
        
        .form-section {
            display: none;
        }
        
        .form-section.active {
            display: block;
        }
        
        .section-title {
            color: #4e73df;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .required-field::after {
            content: " *";
            color: red;
        }
        
        .navigation-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        
        .invalid-feedback {
            display: none;
            color: #dc3545;
        }
        
        .is-invalid {
            border-color: #dc3545;
        }
        
        .is-invalid ~ .invalid-feedback {
            display: block;
        }
    </style>
</head>
<body>
    <div class="registration-container">
        <div class="card-header">
            <h3><i class="fas fa-user-plus me-1"></i>EMPLOYEE REGISTRATION</h3>
        </div>
        
        <div class="card-body">
            <div class="step-indicator">
                <div class="step active" id="step1-indicator">1</div>
                <div class="step-line" id="line1-2"></div>
                <div class="step" id="step2-indicator">2</div>
                <div class="step-line" id="line2-3"></div>
                <div class="step" id="step3-indicator">3</div>
            </div>

            <form action="" method="POST" novalidate class="needs-validation" id="employeeForm">
                <!-- Step 1: Account Information -->
                <div class="form-section active" id="step1">
                    <h4 class="section-title">
                        <i class="fas fa-user-shield"></i>Account Information
                    </h4>
                    
                    <div class="mb-3">
                        <label for="username" class="form-label required-field">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user-tie"></i></span>
                            <input type="text" name="username" id="username" class="form-control" required 
                                   minlength="4" maxlength="20" pattern="[a-zA-Z0-9]+">
                        </div>
                        <div class="invalid-feedback username-error">
                            Username must be 4-20 alphanumeric characters
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label required-field">Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="email" id="email" class="form-control" required>
                        </div>
                        <div class="invalid-feedback email-error">
                            Please enter a valid email address
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label required-field">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-key"></i></span>
                            <input type="password" name="password" id="password" class="form-control" required
                                   minlength="8" pattern="^(?=.*[A-Za-z])(?=.*\d).+$">
                        </div>
                        <div class="invalid-feedback password-error">
                            Password must be at least 8 characters with at least one letter and one number
                        </div>
                    </div>
                    
                                       <div class="mb-3">
                            <label class="form-label">Role *</label>
                            <select name="role_id" class="form-select <?= isInvalidClass($errors, 'role_id') ?>">
                                <option value="">Select Role</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= $role['role_id'] ?>" <?= oldValue($old, 'role_id') == $role['role_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($role['role_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback"><?= errorText($errors, 'role_id') ?>
                        </div>
                    </div>
                    
                    <div class="navigation-buttons">
                        <button type="button" class="btn btn-outline-secondary" disabled>
                            <i class="fas fa-arrow-left me-1"></i> Previous
                        </button>
                        <button type="button" class="btn btn-primary" onclick="nextStep(1, 2)">
                            Next <i class="fas fa-arrow-right ms-1"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Step 2: Personal Information -->
                <div class="form-section" id="step2">
                    <h4 class="section-title">
                        <i class="fas fa-id-card"></i>Personal Information
                    </h4>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label required-field">First Name</label>
                            <input type="text" name="first_name" id="first_name" class="form-control" required
                                   minlength="2" maxlength="50" pattern="[A-Za-z\s]+">
                            <div class="invalid-feedback firstname-error">
                                First name must be 2-50 letters only
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label required-field">Last Name</label>
                            <input type="text" name="last_name" id="last_name" class="form-control" required
                                   minlength="2" maxlength="50" pattern="[A-Za-z\s]+">
                            <div class="invalid-feedback lastname-error">
                                Last name must be 2-50 letters only
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="gender" class="form-label required-field">Gender</label>
                        <select name="gender" id="gender" class="form-select" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                        <div class="invalid-feedback gender-error">
                            Please select gender
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="contact_number" class="form-label">Phone Number</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                            <input type="tel" name="contact_number" id="contact_number" class="form-control"
                                   pattern="[0-9]{10,15}">
                        </div>
                        <div class="invalid-feedback phone-error">
                            Please enter a valid phone number (10-15 digits)
                        </div>
                    </div>
                    
                    <div class="navigation-buttons">
                        <button type="button" class="btn btn-outline-secondary" onclick="prevStep(2, 1)">
                            <i class="fas fa-arrow-left me-1"></i> Previous
                        </button>
                        <button type="button" class="btn btn-primary" onclick="nextStep(2, 3)">
                            Next <i class="fas fa-arrow-right ms-1"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Step 3: Employment Information -->
                <div class="form-section" id="step3">
                    <h4 class="section-title">
                        <i class="fas fa-briefcase"></i>Employment Information
                    </h4>
                    
                                                 <div class="mb-3">
                            <label class="form-label">Position *</label>
                            <select name="position_id" class="form-select <?= isInvalidClass($errors, 'position_id') ?>">
                                <option value="">Select Position</option>
                                <?php foreach ($positions as $pos): ?>
                                    <option value="<?= $pos['position_id'] ?>" <?= oldValue($old, 'position_id') == $pos['position_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($pos['position_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback"><?= errorText($errors, 'position_id') ?></div>
                    </div>
                    
                    <div class="mb-3">
                            <label class="form-label">Department *</label>
                            <select name="department_id" class="form-select <?= isInvalidClass($errors, 'department_id') ?>">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['department_id'] ?>" <?= oldValue($old, 'department_id') == $dept['department_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept['department_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback"><?= errorText($errors, 'department_id') ?>
                        </div>
                    
                    </div>
                    
                    <div class="mb-3">
                        <label for="hire_date" class="form-label required-field">Hire Date</label>
                        <input type="date" name="hire_date" id="hire_date" class="form-control" required
                               max="<?= date('Y-m-d') ?>">
                        <div class="invalid-feedback hiredate-error">
                            Please select a valid hire date (cannot be in the future)
                        </div>
                    </div>
                    
                    <div class="navigation-buttons">
                        <button type="button" class="btn btn-outline-secondary" onclick="prevStep(3, 2)">
                            <i class="fas fa-arrow-left me-1"></i> Previous
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-user-plus me-1"></i> Register
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to move to next step with validation
        function nextStep(currentStep, nextStep) {
            if (validateStep(currentStep)) {
                // Hide current step
                document.getElementById(`step${currentStep}`).classList.remove('active');
                document.getElementById(`step${currentStep}-indicator`).classList.remove('active');
                
                // Show next step
                document.getElementById(`step${nextStep}`).classList.add('active');
                document.getElementById(`step${nextStep}-indicator`).classList.add('active');
                
                // Mark current step as completed
                document.getElementById(`step${currentStep}-indicator`).classList.add('completed');
                if (currentStep === 1) document.getElementById('line1-2').classList.add('completed');
                if (currentStep === 2) document.getElementById('line2-3').classList.add('completed');
            }
        }
        
        // Function to move to previous step
        function prevStep(currentStep, prevStep) {
            // Hide current step
            document.getElementById(`step${currentStep}`).classList.remove('active');
            document.getElementById(`step${currentStep}-indicator`).classList.remove('active');
            
            // Show previous step
            document.getElementById(`step${prevStep}`).classList.add('active');
            document.getElementById(`step${prevStep}-indicator`).classList.add('active');
        }
        
        // Function to validate current step before proceeding
        function validateStep(step) {
            let isValid = true;
            const currentStep = document.getElementById(`step${step}`);
            const fields = currentStep.querySelectorAll('input, select, textarea');
            
            fields.forEach(field => {
                // Reset previous error states
                field.classList.remove('is-invalid');
                const errorElement = field.closest('.mb-3').querySelector('.invalid-feedback');
                
                // Skip validation for non-required empty fields
                if (!field.required && field.value.trim() === '') {
                    errorElement.style.display = 'none';
                    return;
                }
                
                // Validate required fields
                if (field.required && !field.value.trim()) {
                    field.classList.add('is-invalid');
                    errorElement.style.display = 'block';
                    isValid = false;
                    return;
                }
                
                // Field-specific validation
                let fieldValid = true;
                let errorMessage = '';
                
                if (field.id === 'username') {
                    fieldValid = /^[a-zA-Z0-9]{4,20}$/.test(field.value);
                    errorMessage = 'Username must be 4-20 alphanumeric characters';
                }
                
                if (field.id === 'email') {
                    fieldValid = /^\S+@\S+\.\S+$/.test(field.value);
                    errorMessage = 'Please enter a valid email address';
                }
                
                if (field.id === 'password') {
                    fieldValid = field.value.length >= 8 && /[A-Za-z]/.test(field.value) && /\d/.test(field.value);
                    errorMessage = 'Password must be at least 8 characters with at least one letter and one number';
                }
                
                if (field.id === 'first_name' || field.id === 'last_name') {
                    fieldValid = /^[A-Za-z\s]{2,50}$/.test(field.value);
                    errorMessage = `${field.id.replace('_', ' ')} must be 2-50 letters only`;
                }
                
                if (field.id === 'contact_number' && field.value.trim() !== '') {
                    fieldValid = /^[0-9]{10,15}$/.test(field.value);
                    errorMessage = 'Phone number must be 10-15 digits';
                }
                
                if (field.id === 'hire_date') {
                    const today = new Date();
                    const hireDate = new Date(field.value);
                    fieldValid = hireDate <= today;
                    errorMessage = 'Hire date cannot be in the future';
                }
                
                if (!fieldValid) {
                    field.classList.add('is-invalid');
                    errorElement.textContent = errorMessage;
                    errorElement.style.display = 'block';
                    isValid = false;
                } else {
                    errorElement.style.display = 'none';
                }
            });
            
            if (!isValid) {
                // Scroll to the first invalid field
                const firstInvalid = currentStep.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
            
            return isValid;
        }

        // Real-time validation for all fields
        document.getElementById('username').addEventListener('input', function() {
            const isValid = /^[a-zA-Z0-9]{4,20}$/.test(this.value);
            const errorElement = this.closest('.mb-3').querySelector('.invalid-feedback');
            
            this.classList.toggle('is-invalid', !isValid);
            errorElement.style.display = isValid ? 'none' : 'block';
        });

        document.getElementById('email').addEventListener('input', function() {
            const isValid = /^\S+@\S+\.\S+$/.test(this.value);
            const errorElement = this.closest('.mb-3').querySelector('.invalid-feedback');
            
            this.classList.toggle('is-invalid', !isValid);
            errorElement.style.display = isValid ? 'none' : 'block';
        });

        document.getElementById('password').addEventListener('input', function() {
            const isValid = this.value.length >= 8 && /[A-Za-z]/.test(this.value) && /\d/.test(this.value);
            const errorElement = this.closest('.mb-3').querySelector('.invalid-feedback');
            
            this.classList.toggle('is-invalid', !isValid);
            errorElement.style.display = isValid ? 'none' : 'block';
        });

        document.getElementById('first_name').addEventListener('input', function() {
            const isValid = /^[A-Za-z\s]{2,50}$/.test(this.value);
            const errorElement = this.closest('.mb-3').querySelector('.invalid-feedback');
            
            this.classList.toggle('is-invalid', !isValid);
            errorElement.style.display = isValid ? 'none' : 'block';
        });

        document.getElementById('last_name').addEventListener('input', function() {
            const isValid = /^[A-Za-z\s]{2,50}$/.test(this.value);
            const errorElement = this.closest('.mb-3').querySelector('.invalid-feedback');
            
            this.classList.toggle('is-invalid', !isValid);
            errorElement.style.display = isValid ? 'none' : 'block';
        });

        document.getElementById('contact_number').addEventListener('input', function() {
            if (this.value.trim() === '') {
                this.classList.remove('is-invalid');
                this.closest('.mb-3').querySelector('.invalid-feedback').style.display = 'none';
            } else {
                const isValid = /^[0-9]{10,15}$/.test(this.value);
                const errorElement = this.closest('.mb-3').querySelector('.invalid-feedback');
                
                this.classList.toggle('is-invalid', !isValid);
                errorElement.style.display = isValid ? 'none' : 'block';
            }
        });

        document.getElementById('hire_date').addEventListener('change', function() {
            const today = new Date();
            const hireDate = new Date(this.value);
            const isValid = hireDate <= today;
            const errorElement = this.closest('.mb-3').querySelector('.invalid-feedback');
            
            this.classList.toggle('is-invalid', !isValid);
            errorElement.style.display = isValid ? 'none' : 'block';
        });

        // Validate select fields when changed
        document.querySelectorAll('select').forEach(select => {
            select.addEventListener('change', function() {
                if (this.required) {
                    const isValid = this.value !== '';
                    const errorElement = this.closest('.mb-3').querySelector('.invalid-feedback');
                    
                    this.classList.toggle('is-invalid', !isValid);
                    errorElement.style.display = isValid ? 'none' : 'block';
                }
            });
        });
    </script>
</body>
</html>