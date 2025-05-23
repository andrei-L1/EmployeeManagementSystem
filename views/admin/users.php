<?php
require_once('../../auth/check_login.php');
require_once('../../config/dbcon.php');

// Ensure only admin can access this page
if (!hasRole('Admin')) {
    header('Location: ../dashboard.php');
    exit();
}

// Handle user deletion
if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    $stmt = $conn->prepare("UPDATE users SET deleted_at = NOW() WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // Log the action
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, table_affected, record_id) VALUES (?, 'DELETE', 'users', ?)");
    $stmt->execute([$_SESSION['user_data']['user_id'], $user_id]);
    
    $_SESSION['success'] = "User deleted successfully";
    header('Location: users.php');
    exit();
}

// Handle user status toggle
if (isset($_POST['toggle_status']) && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    $stmt = $conn->prepare("UPDATE users SET is_active = NOT is_active WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // Log the action
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, table_affected, record_id) VALUES (?, 'UPDATE', 'users', ?)");
    $stmt->execute([$_SESSION['user_data']['user_id'], $user_id]);
    
    $_SESSION['success'] = "User status updated successfully";
    header('Location: users.php');
    exit();
}

// Fetch all users with their roles
$stmt = $conn->prepare("
    SELECT u.*, r.role_name, 
           e.employee_id, e.first_name, e.last_name,
           d.department_name, p.position_name
    FROM users u
    JOIN roles r ON u.role_id = r.role_id
    LEFT JOIN employees e ON u.user_id = e.user_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN positions p ON e.position_id = p.position_id
    WHERE u.deleted_at IS NULL
    ORDER BY u.created_at DESC
");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all roles for the add/edit form
$stmt = $conn->prepare("SELECT * FROM roles WHERE role_name != 'System'");
$stmt->execute();
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | EmployeeTrack Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #818cf8;
            --primary-dark: #3730a3;
            --secondary: #f472b6;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #0ea5e9;
            --light: #f8fafc;
            --dark: #1e293b;
        }

        body {
            background-color: #f5f7fa;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        .main-content {
            padding-left: 60px;
            padding-right: 60px;
            padding-top: 30px;
            transition: margin-left 0.3s;
        }

        .dashboard-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 2rem;
        }

        .welcome-section {
            padding: 1.5rem 0;
        }

        .welcome-section h1 {
            color: var(--dark);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .welcome-section p {
            font-size: 0.95rem;
        }

        .welcome-section i {
            color: var(--primary);
        }

        .card {
            background: white;
            border-radius: 1rem;
            padding: 1.75rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        }

        .btn {
            padding: 0.5rem 1rem;
            font-weight: 500;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-1px);
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            color: #64748b;
            border-bottom-width: 1px;
        }

        .table td {
            vertical-align: middle;
            padding: 1rem;
        }

        .badge {
            padding: 0.5em 0.75em;
            font-weight: 500;
            border-radius: 0.5rem;
        }

        .modal-content {
            border-radius: 1rem;
            border: none;
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

        .form-label {
            font-weight: 500;
            color: var(--dark);
        }

        .form-control, .form-select {
            border-radius: 0.5rem;
            padding: 0.625rem 1rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(79, 70, 229, 0.25);
        }

        .alert {
            border-radius: 0.75rem;
            border: none;
            padding: 1rem 1.5rem;
        }

        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .alert-danger {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .dataTables_wrapper .dataTables_filter input {
            border-radius: 0.5rem;
            padding: 0.5rem 1rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .dataTables_wrapper .dataTables_length select {
            border-radius: 0.5rem;
            padding: 0.5rem 2rem 0.5rem 1rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius: 0.5rem;
            padding: 0.5rem 1rem;
            margin: 0 0.25rem;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--primary);
            border-color: var(--primary);
            color: white !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: var(--primary-light);
            border-color: var(--primary-light);
            color: white !important;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .bg-primary-subtle {
            background-color: rgba(79, 70, 229, 0.1) !important;
        }

        .text-primary {
            color: var(--primary) !important;
        }

        .bg-success-subtle {
            background-color: rgba(16, 185, 129, 0.1) !important;
        }

        .text-success {
            color: var(--success) !important;
        }

        .bg-warning-subtle {
            background-color: rgba(245, 158, 11, 0.1) !important;
        }

        .text-warning {
            color: var(--warning) !important;
        }

        .bg-danger-subtle {
            background-color: rgba(239, 68, 68, 0.1) !important;
        }

        .text-danger {
            color: var(--danger) !important;
        }

        .bg-info-subtle {
            background-color: rgba(14, 165, 233, 0.1) !important;
        }

        .text-info {
            color: var(--info) !important;
        }

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 1rem;
            }

            .card {
                padding: 1.25rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="dashboard-container">
            <!-- Dashboard Header -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="welcome-section">
                        <h1 class="h3 mb-2">User Management</h1>
                        <p class="text-muted mb-0">
                            <i class="fas fa-users me-2"></i>
                            Manage system users and their roles
                        </p>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-plus me-2"></i> Add New User
                    </button>
                </div>
            </div>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= $_SESSION['success'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= $_SESSION['error'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover" id="usersTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Employee Info</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= $user['user_id'] ?></td>
                                    <td><?= htmlspecialchars($user['username']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= strtolower($user['role_name']) === 'admin' ? 'danger' : 
                                            (strtolower($user['role_name']) === 'hr' ? 'warning' : 
                                            (strtolower($user['role_name']) === 'manager' ? 'info' : 'success')) ?>">
                                            <?= htmlspecialchars($user['role_name']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($user['employee_id']): ?>
                                            <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                                            <br>
                                            <small class="text-muted">
                                                <?= htmlspecialchars($user['position_name']) ?> in 
                                                <?= htmlspecialchars($user['department_name']) ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                            <button type="submit" name="toggle_status" class="btn btn-sm btn-<?= $user['is_active'] ? 'success' : 'secondary' ?>">
                                                <i class="fas fa-<?= $user['is_active'] ? 'check-circle' : 'times-circle' ?> me-1"></i>
                                                <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td><?= $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never' ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary edit-user" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editUserModal"
                                                data-user='<?= htmlspecialchars(json_encode($user)) ?>'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger delete-user"
                                                data-bs-toggle="modal"
                                                data-bs-target="#deleteUserModal"
                                                data-user-id="<?= $user['user_id'] ?>"
                                                data-username="<?= htmlspecialchars($user['username']) ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="add_user.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Add New User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="role_id" class="form-label">Role</label>
                            <select class="form-select" id="role_id" name="role_id" required>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= $role['role_id'] ?>"><?= htmlspecialchars($role['role_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Add User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="edit_user.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-user-edit me-2"></i>Edit User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="edit_username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_password" class="form-label">New Password (leave blank to keep current)</label>
                            <input type="password" class="form-control" id="edit_password" name="password">
                        </div>
                        <div class="mb-3">
                            <label for="edit_role_id" class="form-label">Role</label>
                            <select class="form-select" id="edit_role_id" name="role_id" required>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= $role['role_id'] ?>"><?= htmlspecialchars($role['role_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="user_id" id="delete_user_id">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-trash-alt me-2"></i>Confirm Delete</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete user: <span id="delete_username" class="fw-bold"></span>?</p>
                        <p class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_user" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Delete User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize DataTable
        $('#usersTable').DataTable({
            order: [[0, 'desc']],
            pageLength: 10,
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search users..."
            }
        });
        
        // Handle edit user modal
        document.querySelectorAll('.edit-user').forEach(button => {
            button.addEventListener('click', function() {
                const userData = JSON.parse(this.dataset.user);
                document.getElementById('edit_user_id').value = userData.user_id;
                document.getElementById('edit_username').value = userData.username;
                document.getElementById('edit_email').value = userData.email;
                document.getElementById('edit_role_id').value = userData.role_id;
            });
        });
        
        // Handle delete user modal
        document.querySelectorAll('.delete-user').forEach(button => {
            button.addEventListener('click', function() {
                document.getElementById('delete_user_id').value = this.dataset.userId;
                document.getElementById('delete_username').textContent = this.dataset.username;
            });
        });
    });
    </script>
</body>
</html>
