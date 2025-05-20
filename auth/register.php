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

// Fetch roles, positions, departments for selects (for both GET and POST)
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #3498db;
            --secondary-blue: #34495e;
            --accent-blue: #3498db;
            --light-gray: #ecf0f1;
            --dark-gray: #7f8c8d;
        }
        
        body {
            background-color: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .registration-container {
            max-width: 600px;
            width: 100%;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-blue), var(--secondary-blue));
            color: white;
            padding: 1rem;
            text-align: center;
            border-bottom: none;
        }
        
        .card-header h3 {
            font-size: 1.25rem;
            margin: 0;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .form-section {
            display: none;
            padding: 1rem 0;
        }
        
        .form-section.active {
            display: block;
        }
        
        .section-title {
            color: var(--primary-blue);
            font-weight: 600;
            margin-bottom: 1rem;
            font-size: 1rem;
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 8px;
            font-size: 0.9rem;
        }
        
        .required-field::after {
            content: " *";
            color: #e74c3c;
            font-size: 0.8rem;
        }
        
        .form-control, .form-select {
            padding: 0.5rem 0.75rem;
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
        }
        
        .input-group-text {
            padding: 0.5rem 0.75rem;
            font-size: 0.9rem;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 1.5rem;
            gap: 10px;
        }
        
        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
            position: relative;
        }
        
        .step.active {
            background-color: var(--primary-blue);
            color: white;
        }
        
        .step.completed {
            background-color: #2ecc71;
            color: white;
        }
        
        .step-line {
            flex: 1;
            height: 2px;
            background-color: #ddd;
            margin: 0 5px;
            align-self: center;
        }
        
        .step-line.completed {
            background-color: #2ecc71;
        }
        
        .navigation-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 1.5rem;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background-color: var(--primary-blue);
            border-color: var(--primary-blue);
        }
        
        .invalid-feedback {
            font-size: 0.75rem;
            margin-top: -0.5rem;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="registration-container">
        <div class="card-header">
            <h3><i class="fas fa-user-plus me-1"></i>EMPLOYEE REGISTRATION</h3>
        </div>
        
        <div class="card-body">
            <!-- Step Indicator -->
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
                            <input type="text" name="username" id="username" class="form-control" required>
                        </div>
                        <div class="invalid-feedback">Please enter a username</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label required-field">Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="email" id="email" class="form-control" required>
                        </div>
                        <div class="invalid-feedback">Please enter a valid email</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label required-field">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-key"></i></span>
                            <input type="password" name="password" id="password" class="form-control" required>
                        </div>
                        <div class="invalid-feedback">Password must be at least 8 characters</div>
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
                            <div class="invalid-feedback"><?= errorText($errors, 'role_id') ?></div>
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
                            <input type="text" name="first_name" id="first_name" class="form-control" required>
                            <div class="invalid-feedback">Please enter first name</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label required-field">Last Name</label>
                            <input type="text" name="last_name" id="last_name" class="form-control" required>
                            <div class="invalid-feedback">Please enter last name</div>
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
                        <div class="invalid-feedback">Please select gender</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="contact_number" class="form-label">Phone Number</label>
                        <input type="text" name="contact_number" id="contact_number" class="form-control">
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
                            <div class="invalid-feedback"><?= errorText($errors, 'department_id') ?></div>
                        </div>
                    
                    <div class="mb-3">
                        <label for="hire_date" class="form-label required-field">Hire Date</label>
                        <input type="date" name="hire_date" id="hire_date" class="form-control" required>
                        <div class="invalid-feedback">Please select hire date</div>
                    </div>
                    
                    <div class="navigation-buttons">
                        <button type="button" class="btn btn-outline-secondary" onclick="prevStep(3, 2)">
                            <i class="fas fa-arrow-left me-1"></i> Previous
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-user-plus me-1"></i> Register
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
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
        
        function prevStep(currentStep, prevStep) {
            // Hide current step
            document.getElementById(`step${currentStep}`).classList.remove('active');
            document.getElementById(`step${currentStep}-indicator`).classList.remove('active');
            
            // Show previous step
            document.getElementById(`step${prevStep}`).classList.add('active');
            document.getElementById(`step${prevStep}-indicator`).classList.add('active');
            
            // Unmark completion status
            document.getElementById(`step${currentStep}-indicator`).classList.remove('completed');
            if (currentStep === 2) document.getElementById('line1-2').classList.remove('completed');
            if (currentStep === 3) document.getElementById('line2-3').classList.remove('completed');
        }
        
        function validateStep(step) {
            let isValid = true;
            const currentStep = document.getElementById(`step${step}`);
            const requiredFields = currentStep.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                    
                    // Additional validation for specific fields
                    if (field.id === 'email' && !/^\S+@\S+\.\S+$/.test(field.value)) {
                        field.classList.add('is-invalid');
                        field.nextElementSibling.textContent = 'Please enter a valid email';
                        isValid = false;
                    }
                    
                    if (field.id === 'password' && field.value.length < 8) {
                        field.classList.add('is-invalid');
                        field.nextElementSibling.textContent = 'Password must be at least 8 characters';
                        isValid = false;
                    }
                }
            });
            
            return isValid;
        }
        
        // Real-time validation
        document.getElementById('email').addEventListener('input', function() {
            this.classList.toggle('is-invalid', !/^\S+@\S+\.\S+$/.test(this.value));
        });
        
        document.getElementById('password').addEventListener('input', function() {
            this.classList.toggle('is-invalid', this.value.length < 8);
        });
    </script>
</body>
</html>