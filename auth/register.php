<?php
session_start();
require_once '../config/dbcon.php';

$success = '';
$error = '';
$errors = [];

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

    // Validation
    if (!$username) $errors['username'] = 'Username is required.';
    if (!$email) {
        $errors['email'] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format.';
    }
    if (!$password) {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters.';
    }
    if (!$role_id) $errors['role_id'] = 'Role is required.';
    if (!$first_name) $errors['first_name'] = 'First name is required.';
    if (!$last_name) $errors['last_name'] = 'Last name is required.';
    if ($birth_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
        $errors['birth_date'] = 'Invalid birth date.';
    }
    if (!$gender) $errors['gender'] = 'Gender is required.';
    if ($contact_number && !preg_match('/^\d{11}$/', $contact_number)) {
        $errors['contact_number'] = 'Contact number must be exactly 11 digits.';
    }
    if (!$hire_date) $errors['hire_date'] = 'Hire date is required.';
    if (!$position_id) $errors['position_id'] = 'Position is required.';
    if (!$department_id) $errors['department_id'] = 'Department is required.';

    if (empty($errors)) {
        try {
            $conn->beginTransaction();

            // Hash the password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // Insert into users table
            $stmtUser = $conn->prepare("INSERT INTO users (username, password_hash, email, role_id) VALUES (?, ?, ?, ?)");
            $stmtUser->execute([$username, $password_hash, $email, $role_id]);
            $user_id = $conn->lastInsertId();

            // Insert into employees table
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

            $success = "You have successfully registered.";

            // Clear POST after success
            $_POST = [];
        } catch (PDOException $e) {
            $conn->rollBack();
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch roles, positions, departments
$roles = $conn->query("SELECT role_id, role_name FROM roles")->fetchAll(PDO::FETCH_ASSOC);
$positions = $conn->query("SELECT position_id, position_name FROM positions")->fetchAll(PDO::FETCH_ASSOC);
$departments = $conn->query("SELECT department_id, department_name FROM departments")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Employee Registration</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
<style>
    :root {
        --primary-color: #2c3e50;
        --secondary-color: #1a252f;
        --accent-color: #3498db;
        --light-color: #ecf0f1;
        --dark-color: #2c3e50;
        --success-color: #27ae60;
        --border-color: #dfe6e9;
    }
    
    body {
        background-color: #f8f9fa;
        font-family: 'Segoe UI', 'Roboto', sans-serif;
        color: var(--dark-color);
        line-height: 1.5;
    }
    
    .container {
        max-width: 500px;
        background: white;
        border-radius: 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        padding: 25px;
        margin: 30px auto;
    }
    
    h2 {
        color: var(--accent-color);
        text-align: center;
        margin-bottom: 25px;
        font-weight: 600;
        font-size: 1.5rem;
    }
    
    .form-header {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 8px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .form-header i {
        font-size: 18px;
        margin-right: 10px;
        color: var(--accent-color);
    }
    
    .form-header h3 {
        margin: 0;
        color: var(--accent-color);
        font-size: 1.1rem;
        font-weight: 500;
    }
    
    fieldset {
        border-radius: 6px;
        border: 1px solid var(--border-color);
        padding: 20px;
        margin-bottom: 15px;
        background: #fff;
    }
    
    .form-label {
        font-weight: 500;
        font-size: 0.9rem;
        color: var(--dark-color);
        margin-bottom: 0.3rem;
    }
    
    .form-control, .form-select {
        border: 1px solid var(--border-color);
        border-radius: 4px;
        padding: 0.4rem 0.75rem;
        font-size: 0.9rem;
        height: calc(1.6em + 0.75rem + 2px);
    }
    
    .form-control:focus, .form-select:focus {
        border-color: var(--accent-color);
        box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.15);
    }
    
    .btn {
        padding: 0.4rem 0.9rem;
        font-size: 0.9rem;
        font-weight: 500;
        border-radius: 4px;
    }
    
    .btn-primary {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
    }
    
    .btn-primary:hover {
        background-color: var(--secondary-color);
        border-color: var(--secondary-color);
    }
    
    .btn-secondary {
        background-color: #95a5a6;
        border-color: #95a5a6;
    }
    
    .btn-success {
        background-color: var(--success-color);
        border-color: var(--success-color);
    }
    
    .invalid-feedback {
        font-size: 0.8rem;
        margin-top: 0.2rem;
    }
    
    .progress-steps {
        display: flex;
        justify-content: space-between;
        margin-bottom: 25px;
        position: relative;
    }
    
    .progress-steps::before {
        content: '';
        position: absolute;
        top: 12px;
        left: 0;
        right: 0;
        height: 2px;
        background-color: var(--border-color);
        z-index: 1;
    }
    
    .progress-bar {
        position: absolute;
        top: 12px;
        left: 0;
        height: 2px;
        background-color: var(--accent-color);
        z-index: 2;
        transition: width 0.3s ease;
    }
    
    .step {
        display: flex;
        flex-direction: column;
        align-items: center;
        position: relative;
        z-index: 3;
    }
    
    .step-number {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background-color: var(--border-color);
        color: #7f8c8d;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 500;
        font-size: 0.8rem;
        margin-bottom: 5px;
    }
    
    .step.active .step-number {
        background-color: var(--accent-color);
        color: white;
    }
    
    .step-label {
        font-size: 0.75rem;
        color: #7f8c8d;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .step.active .step-label {
        color: var(--primary-color);
    }
    
    .input-icon {
        position: relative;
    }
    
    .input-icon i {
        position: absolute;
        left: 10px;
        top: 40px;
        color: var(--accent-color);
        font-size: 0.9rem;
    }
    
    .input-icon input, .input-icon select {
        padding-left: 32px;
    }
    
    .form-check-label {
        font-size: 0.85rem;
    }
    
    .alert {
        padding: 0.6rem 1rem;
        font-size: 0.9rem;
        margin-bottom: 20px;
    }
    
    .d-flex.justify-content-between {
        margin-top: 20px;
    }
    
    /* Compact form groups */
    .mb-3 {
        margin-bottom: 1rem !important;
    }
</style>
</head>
<body>
<div class="container mt-4">
    <h2><i class="fas fa-user-plus me-2"></i>  Registration</h2>

    <div class="progress-steps">
        <div class="progress-bar" style="width: 33%;"></div>
        <div class="step active">
            <div class="step-number">1</div>
            <div class="step-label">Account</div>
        </div>
        <div class="step">
            <div class="step-number">2</div>
            <div class="step-label">Personal</div>
        </div>
        <div class="step">
            <div class="step-number">3</div>
            <div class="step-label">Employment</div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <script>
            setTimeout(() => {
                window.location.href = '../index.php?registered=1';
            }, 3000);
        </script>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form id="regForm" action="register.php" method="POST" novalidate>
        <!-- Step 1 -->
        <fieldset class="mb-3 active">
            <div class="form-header">
                <i class="fas fa-user-circle"></i>
                <h3>Account Information</h3>
            </div>

            <div class="mb-2 input-icon">
                <label for="username" class="form-label">Username *</label>
                <i class="fas fa-user"></i>
                <input type="text" id="username" name="username" class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>" 
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required />
                <div class="invalid-feedback"><?= $errors['username'] ?? '' ?></div>
            </div>

            <div class="mb-2 input-icon">
                <label for="email" class="form-label">Email *</label>
                <i class="fas fa-envelope"></i>
                <input type="email" id="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" 
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required />
                <div class="invalid-feedback"><?= $errors['email'] ?? '' ?></div>
            </div>

            <div class="mb-2 input-icon">
                <label for="password" class="form-label">Password *</label>
                <i class="fas fa-lock"></i>
                <input type="password" id="password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" required minlength="6" />
                <div class="invalid-feedback"><?= $errors['password'] ?? '' ?></div>
                <div class="form-check mt-1">
                    <input type="checkbox" class="form-check-input" id="showPasswordToggle" />
                    <label for="showPasswordToggle" class="form-check-label">Show Password</label>
                </div>
            </div>

            <div class="mb-2 input-icon">
                <label for="role_id" class="form-label">Role *</label>
                <i class="fas fa-user-tag"></i>
                <select id="role_id" name="role_id" class="form-select <?= isset($errors['role_id']) ? 'is-invalid' : '' ?>" required>
                    <option value="" disabled <?= empty($_POST['role_id']) ? 'selected' : '' ?>>Select Role</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= htmlspecialchars($role['role_id']) ?>" <?= (($_POST['role_id'] ?? '') == $role['role_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($role['role_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback"><?= $errors['role_id'] ?? '' ?></div>
            </div>

            <div class="d-flex justify-content-between mt-3">
                <a href="../index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Cancel
                </a>
                <button type="button" class="btn btn-primary btn-next">
                    Next <i class="fas fa-arrow-right ms-1"></i>
                </button>
            </div>
        </fieldset>

        <!-- Step 2 -->
        <fieldset class="mb-3" style="display:none;">
            <div class="form-header">
                <i class="fas fa-id-card"></i>
                <h3>Personal Information</h3>
            </div>

            <div class="row">
                <div class="col-md-6 mb-2 input-icon">
                    <label for="first_name" class="form-label">First Name *</label>
                    <i class="fas fa-user"></i>
                    <input type="text" id="first_name" name="first_name" class="form-control <?= isset($errors['first_name']) ? 'is-invalid' : '' ?>" 
                        value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required />
                    <div class="invalid-feedback"><?= $errors['first_name'] ?? '' ?></div>
                </div>
                
                <div class="col-md-6 mb-2 input-icon">
                    <label for="last_name" class="form-label">Last Name *</label>
                    <i class="fas fa-user"></i>
                    <input type="text" id="last_name" name="last_name" class="form-control <?= isset($errors['last_name']) ? 'is-invalid' : '' ?>" 
                        value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required />
                    <div class="invalid-feedback"><?= $errors['last_name'] ?? '' ?></div>
                </div>
            </div>

            <div class="mb-2 input-icon">
                <label for="middle_name" class="form-label">Middle Initial</label>
                <i class="fas fa-user"></i>
                <input type="text" id="middle_name" name="middle_name" class="form-control" 
                    value="<?= htmlspecialchars($_POST['middle_name'] ?? '') ?>" maxlength="1" />
            </div>

            <div class="row">
                <div class="col-md-6 mb-2 input-icon">
                    <label for="birth_date" class="form-label">Birth Date</label>
                    <i class="fas fa-calendar"></i>
                    <input type="date" id="birth_date" name="birth_date" class="form-control <?= isset($errors['birth_date']) ? 'is-invalid' : '' ?>" 
                        value="<?= htmlspecialchars($_POST['birth_date'] ?? '') ?>" />
                    <div class="invalid-feedback"><?= $errors['birth_date'] ?? '' ?></div>
                </div>
                
                <div class="col-md-6 mb-2 input-icon">
                    <label for="gender" class="form-label">Gender *</label>
                    <i class="fas fa-venus-mars"></i>
                    <select id="gender" name="gender" class="form-select <?= isset($errors['gender']) ? 'is-invalid' : '' ?>" required>
                        <option value="" disabled <?= empty($_POST['gender']) ? 'selected' : '' ?>>Select Gender</option>
                        <option value="Male" <?= (($_POST['gender'] ?? '') == 'Male') ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= (($_POST['gender'] ?? '') == 'Female') ? 'selected' : '' ?>>Female</option>
                        <option value="Other" <?= (($_POST['gender'] ?? '') == 'Other') ? 'selected' : '' ?>>Other</option>
                    </select>
                    <div class="invalid-feedback"><?= $errors['gender'] ?? '' ?></div>
                </div>
            </div>

            <div class="mb-2 input-icon">
                <label for="contact_number" class="form-label">Contact Number</label>
                <i class="fas fa-phone"></i>
                <input type="text" id="contact_number" name="contact_number" class="form-control <?= isset($errors['contact_number']) ? 'is-invalid' : '' ?>" 
                    value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>" maxlength="11" />
                <div class="invalid-feedback"><?= $errors['contact_number'] ?? '' ?></div>
            </div>

            <div class="mb-2 input-icon">
                <label for="address" class="form-label">Address</label>
                <i class="fas fa-map-marker-alt"></i>
                <textarea id="address" name="address" class="form-control" rows="2"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
            </div>

            <div class="d-flex justify-content-between mt-3">
                <button type="button" class="btn btn-secondary btn-back">
                    <i class="fas fa-arrow-left me-1"></i> Previous
                </button>
                <button type="button" class="btn btn-primary btn-next">
                    Next <i class="fas fa-arrow-right ms-1"></i>
                </button>
            </div>
        </fieldset>

        <!-- Step 3 -->
        <fieldset class="mb-3" style="display:none;">
            <div class="form-header">
                <i class="fas fa-briefcase"></i>
                <h3>Employment Information</h3>
            </div>

            <div class="mb-2 input-icon">
                <label for="hire_date" class="form-label">Hire Date *</label>
                <i class="fas fa-calendar-alt"></i>
                <input type="date" id="hire_date" name="hire_date" class="form-control <?= isset($errors['hire_date']) ? 'is-invalid' : '' ?>" 
                    value="<?= htmlspecialchars($_POST['hire_date'] ?? '') ?>" required />
                <div class="invalid-feedback"><?= $errors['hire_date'] ?? '' ?></div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-2 input-icon">
                    <label for="position_id" class="form-label">Position *</label>
                    <i class="fas fa-user-tie"></i>
                    <select id="position_id" name="position_id" class="form-select <?= isset($errors['position_id']) ? 'is-invalid' : '' ?>" required>
                        <option value="" disabled <?= empty($_POST['position_id']) ? 'selected' : '' ?>>Select Position</option>
                        <?php foreach ($positions as $pos): ?>
                            <option value="<?= htmlspecialchars($pos['position_id']) ?>" <?= (($_POST['position_id'] ?? '') == $pos['position_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pos['position_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback"><?= $errors['position_id'] ?? '' ?></div>
                </div>
                
                <div class="col-md-6 mb-2 input-icon">
                    <label for="department_id" class="form-label">Department *</label>
                    <i class="fas fa-building"></i>
                    <select id="department_id" name="department_id" class="form-select <?= isset($errors['department_id']) ? 'is-invalid' : '' ?>" required>
                        <option value="" disabled <?= empty($_POST['department_id']) ? 'selected' : '' ?>>Select Department</option>
                        <?php foreach ($departments as $dep): ?>
                            <option value="<?= htmlspecialchars($dep['department_id']) ?>" <?= (($_POST['department_id'] ?? '') == $dep['department_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dep['department_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback"><?= $errors['department_id'] ?? '' ?></div>
                </div>
            </div>

            <div class="d-flex justify-content-between mt-3">
                <button type="button" class="btn btn-secondary btn-back">
                    <i class="fas fa-arrow-left me-1"></i> Previous
                </button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-check me-1"></i> Submit
                </button>
            </div>
        </fieldset>
    </form>
</div>

<script>
// Your existing JavaScript remains the same
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('regForm');
    const fieldsets = form.querySelectorAll('fieldset');
    const steps = document.querySelectorAll('.step');
    const progressBar = document.querySelector('.progress-bar');
    let currentStep = 0;

    // Show password toggle
    const showPasswordToggle = document.getElementById('showPasswordToggle');
    const passwordInput = document.getElementById('password');
    showPasswordToggle.addEventListener('change', () => {
        passwordInput.type = showPasswordToggle.checked ? 'text' : 'password';
    });

    // Show only the current step fieldset
    function showStep(step) {
        fieldsets.forEach((fs, index) => {
            fs.style.display = (index === step) ? 'block' : 'none';
            if(index === step) {
                fs.classList.add('active');
                steps[index].classList.add('active');
            } else {
                fs.classList.remove('active');
                steps[index].classList.remove('active');
            }
        });
        
        // Update progress bar
        const progressWidth = ((step + 1) / fieldsets.length) * 100;
        progressBar.style.width = `${progressWidth}%`;
    }
    showStep(currentStep);

    // Validate input function with real-time validation
    function validateInput(input) {
        input.classList.remove('is-invalid');
        const feedback = input.nextElementSibling;
        if (feedback && feedback.classList.contains('invalid-feedback')) {
            feedback.textContent = '';
        }

        const val = input.value.trim();

        if (input.hasAttribute('required') && !val) {
            input.classList.add('is-invalid');
            if (feedback) feedback.textContent = 'This field is required.';
            return false;
        }

        if (input.type === 'email' && val) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(val)) {
                input.classList.add('is-invalid');
                if (feedback) feedback.textContent = 'Please enter a valid email.';
                return false;
            }
        }

        if (input.id === 'password' && val.length > 0) {
            const lengthValid = val.length >= 6;
            const numberValid = /\d/.test(val);
            const specialCharValid = /[!@#$%^&*(),.?":{}|<>]/.test(val);

            if (!lengthValid) {
                input.classList.add('is-invalid');
                if (feedback) feedback.textContent = 'Password must be at least 6 characters.';
                return false;
            }
            if (!numberValid) {
                input.classList.add('is-invalid');
                if (feedback) feedback.textContent = 'Password must include at least one number.';
                return false;
            }
            if (!specialCharValid) {
                input.classList.add('is-invalid');
                if (feedback) feedback.textContent = 'Password must include at least one special character.';
                return false;
            }
        }

        if (input.id === 'contact_number' && val) {
            if (!/^\d{11}$/.test(val)) {
                input.classList.add('is-invalid');
                if (feedback) feedback.textContent = 'Contact Number must be exactly 11 digits.';
                return false;
            }
        }

        // First name and last name: no digits allowed, min 2 chars
        if ((input.id === 'first_name' || input.id === 'last_name') && val) {
            if (val.length < 2) {
                input.classList.add('is-invalid');
                if (feedback) feedback.textContent = `${input.id.replace('_', ' ')} must be at least 2 characters.`;
                return false;
            }
            if (/\d/.test(val)) {
                input.classList.add('is-invalid');
                if (feedback) feedback.textContent = `${input.id.replace('_', ' ')} cannot contain numbers.`;
                return false;
            }
        }

        // Middle name (middle initial): max 1 char, no digits
        if (input.id === 'middle_name' && val) {
            if (val.length > 1) {
                input.classList.add('is-invalid');
                if (feedback) feedback.textContent = 'Middle initial can only be 1 character.';
                return false;
            }
            if (/\d/.test(val)) {
                input.classList.add('is-invalid');
                if (feedback) feedback.textContent = 'Middle initial cannot contain numbers.';
                return false;
            }
        }

        return true; // pass validation
    }

    // Add real-time validation event listeners on all inputs/selects/textareas
    form.querySelectorAll('input, select, textarea').forEach(input => {
        input.addEventListener('input', () => {
            validateInput(input);
        });
    });

    // Validate all inputs in current step before moving forward
    function validateStep(step) {
        const inputs = fieldsets[step].querySelectorAll('input, select, textarea');
        let valid = true;

        inputs.forEach(input => {
            if (!validateInput(input)) {
                valid = false;
            }
        });

        return valid;
    }

    // Next buttons
    form.querySelectorAll('.btn-next').forEach(button => {
        button.addEventListener('click', () => {
            if (validateStep(currentStep)) {
                if (currentStep < fieldsets.length - 1) {
                    currentStep++;
                    showStep(currentStep);
                    window.scrollTo(0, 0);
                }
            }
        });
    });

    // Back buttons
    form.querySelectorAll('.btn-back').forEach(button => {
        button.addEventListener('click', () => {
            if (currentStep > 0) {
                currentStep--;
                showStep(currentStep);
                window.scrollTo(0, 0);
            }
        });
    });
});
</script>
</body>
</html>
</html>