<?php
session_start();
require_once '../config/dbcon.php';

// Only allow existing admins to create new admin accounts
if (!hasRole('Admin')) {
    header('Location: ../index.php');
    exit();
}

$success = '';
$error = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize input
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (!$username) $errors['username'] = 'Username is required.';
    if (!$email) {
        $errors['email'] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format.';
    }
    if (!$password) {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    // Check if username or email already exists
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetchColumn() > 0) {
            $errors['username'] = 'Username or email already exists.';
        }
    }

    if (empty($errors)) {
        try {
            // Get the Admin role ID
            $stmt = $conn->prepare("SELECT role_id FROM roles WHERE role_name = 'Admin'");
            $stmt->execute();
            $role_id = $stmt->fetchColumn();

            // Hash the password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // Insert into users table
            $stmt = $conn->prepare("INSERT INTO users (username, password_hash, email, role_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $password_hash, $email, $role_id]);

            $success = "Admin account created successfully.";
            $_POST = []; // Clear form data
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin Account</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        
        .form-label {
            font-weight: 500;
            font-size: 0.9rem;
            color: var(--dark-color);
            margin-bottom: 0.3rem;
        }
        
        .form-control {
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 0.4rem 0.75rem;
            font-size: 0.9rem;
            height: calc(1.6em + 0.75rem + 2px);
        }
        
        .form-control:focus {
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
        
        .invalid-feedback {
            font-size: 0.8rem;
            margin-top: 0.2rem;
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
        
        .input-icon input {
            padding-left: 32px;
        }
        
        .alert {
            padding: 0.6rem 1rem;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h2><i class="fas fa-user-shield me-2"></i>Create Admin Account</h2>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <script>
                setTimeout(() => {
                    window.location.href = '../views/admin/users.php';
                }, 2000);
            </script>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="admin_register.php" method="POST" novalidate>
            <div class="form-header">
                <i class="fas fa-user-circle"></i>
                <h3>Admin Account Information</h3>
            </div>

            <div class="mb-3 input-icon">
                <label for="username" class="form-label">Username *</label>
                <i class="fas fa-user"></i>
                <input type="text" id="username" name="username" 
                    class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>" 
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required />
                <div class="invalid-feedback"><?= $errors['username'] ?? '' ?></div>
            </div>

            <div class="mb-3 input-icon">
                <label for="email" class="form-label">Email *</label>
                <i class="fas fa-envelope"></i>
                <input type="email" id="email" name="email" 
                    class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" 
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required />
                <div class="invalid-feedback"><?= $errors['email'] ?? '' ?></div>
            </div>

            <div class="mb-3 input-icon">
                <label for="password" class="form-label">Password *</label>
                <i class="fas fa-lock"></i>
                <input type="password" id="password" name="password" 
                    class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" 
                    required minlength="8" />
                <div class="invalid-feedback"><?= $errors['password'] ?? '' ?></div>
            </div>

            <div class="mb-3 input-icon">
                <label for="confirm_password" class="form-label">Confirm Password *</label>
                <i class="fas fa-lock"></i>
                <input type="password" id="confirm_password" name="confirm_password" 
                    class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>" 
                    required minlength="8" />
                <div class="invalid-feedback"><?= $errors['confirm_password'] ?? '' ?></div>
            </div>

            <div class="d-flex justify-content-between mt-4">
                <a href="../views/admin/users.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Users
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-user-plus me-1"></i> Create Admin
                </button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('form');
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');

            // Real-time password match validation
            confirmPassword.addEventListener('input', () => {
                if (password.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            });

            password.addEventListener('input', () => {
                if (confirmPassword.value) {
                    if (password.value !== confirmPassword.value) {
                        confirmPassword.setCustomValidity('Passwords do not match');
                    } else {
                        confirmPassword.setCustomValidity('');
                    }
                }
            });

            // Form validation
            form.addEventListener('submit', (e) => {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                form.classList.add('was-validated');
            });
        });
    </script>
</body>
</html> 