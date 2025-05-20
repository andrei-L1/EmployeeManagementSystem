<?php
require_once '../../auth/check_login.php';
require_once '../../config/dbcon.php';

// Only managers/HR can access this page
if (!hasRole('Manager') && !hasRole('HR')) {
    header('Location: ../dashboard.php');
    exit();
}

// Get departments for filter
$stmt = $conn->prepare("SELECT * FROM departments WHERE deleted_at IS NULL ORDER BY department_name");
$stmt->execute();
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get positions for filter
$stmt = $conn->prepare("SELECT * FROM positions WHERE deleted_at IS NULL ORDER BY position_name");
$stmt->execute();
$positions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build query based on filters
$where = ["e.deleted_at IS NULL"];
$params = [];

if (hasRole('Manager')) {
    $where[] = "e.department_id = ?";
    $params[] = $_SESSION['user_data']['employee_data']['department_id'];
}

if (isset($_GET['department']) && !empty($_GET['department'])) {
    $where[] = "e.department_id = ?";
    $params[] = $_GET['department'];
}

if (isset($_GET['position']) && !empty($_GET['position'])) {
    $where[] = "e.position_id = ?";
    $params[] = $_GET['position'];
}

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $where[] = "e.employment_status = ?";
    $params[] = $_GET['status'];
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $where[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_id LIKE ?)";
    $search = "%{$_GET['search']}%";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Get employees
$stmt = $conn->prepare("
    SELECT e.*, d.department_name, p.position_name,
           CONCAT(e.first_name, ' ', e.last_name) as full_name
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN positions p ON e.position_id = p.position_id
    $whereClause
    ORDER BY e.last_name, e.first_name
");
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees | EmployeeTrack Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
        }

        body {
            background-color: #f8f9fc;
        }

        .main-content {
            padding: 2rem;
            transition: margin-left 0.3s;
        }

        .page-header {
            background: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
        }

        .employee-card {
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            border-radius: 0.5rem;
            overflow: hidden;
        }
        
        .employee-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 2rem 0 rgba(58, 59, 69, 0.2);
        }

        .employee-avatar {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid #fff;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            margin: 1rem auto;
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.5em 1em;
            font-weight: 600;
            border-radius: 2rem;
        }

        .filter-card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
        }

        .filter-card .card-body {
            padding: 1.5rem;
        }

        .btn-action {
            padding: 0.5rem 1rem;
            border-radius: 0.35rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-action:hover {
            transform: translateY(-2px);
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--secondary-color);
            margin-bottom: 1rem;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            display: none;
        }

        .spinner {
            width: 3rem;
            height: 3rem;
        }

        .department-badge {
            background: #e8f0fe;
            color: var(--primary-color);
            padding: 0.35em 0.65em;
            border-radius: 0.25rem;
            font-size: 0.875rem;
        }

        .employee-info {
            margin: 1rem 0;
        }

        .employee-info p {
            margin-bottom: 0.5rem;
            color: var(--secondary-color);
        }

        .employee-info i {
            width: 1.5rem;
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="page-header d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-0">Employee Management</h2>
                    <p class="text-muted mb-0">Manage and view all employees in the system</p>
                </div>
                <?php if (hasRole('HR')): ?>
                <a href="add.php" class="btn btn-primary btn-action">
                    <i class="fas fa-user-plus me-2"></i> Add New Employee
                </a>
                <?php endif; ?>
            </div>

            <!-- Filters -->
            <div class="filter-card">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Search</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" name="search" 
                                       value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" 
                                       placeholder="Search by name or ID">
                            </div>
                        </div>
                        
                        <?php if (hasRole('HR')): ?>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Department</label>
                            <select class="form-select" name="department">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['department_id'] ?>" 
                                        <?= (isset($_GET['department']) && $_GET['department'] == $dept['department_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['department_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Position</label>
                            <select class="form-select" name="position">
                                <option value="">All Positions</option>
                                <?php foreach ($positions as $pos): ?>
                                <option value="<?= $pos['position_id'] ?>"
                                        <?= (isset($_GET['position']) && $_GET['position'] == $pos['position_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pos['position_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="Regular" <?= (isset($_GET['status']) && $_GET['status'] === 'Regular') ? 'selected' : '' ?>>Regular</option>
                                <option value="Probationary" <?= (isset($_GET['status']) && $_GET['status'] === 'Probationary') ? 'selected' : '' ?>>Probationary</option>
                                <option value="Contractual" <?= (isset($_GET['status']) && $_GET['status'] === 'Contractual') ? 'selected' : '' ?>>Contractual</option>
                            </select>
                        </div>
                        
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100 btn-action">
                                <i class="fas fa-filter"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Loading Overlay -->
            <div class="loading-overlay">
                <div class="spinner-border text-primary spinner" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>

            <!-- Employee List -->
            <div class="row">
                <?php if (empty($employees)): ?>
                <div class="col-12">
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h3>No Employees Found</h3>
                        <p class="text-muted">Try adjusting your search or filter criteria</p>
                        <?php if (hasRole('HR')): ?>
                        <a href="add.php" class="btn btn-primary mt-3">
                            <i class="fas fa-user-plus me-2"></i> Add New Employee
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <?php foreach ($employees as $employee): ?>
                <div class="col-md-4 mb-4">
                    <div class="card employee-card h-100">
                        <div class="card-body text-center">
                            <img src="<?= $employee['profile_picture'] ?? '../../assets/img/default-profile.jpg' ?>" 
                                 alt="Profile Picture" 
                                 class="employee-avatar">
                            
                            <h5 class="card-title mb-1"><?= htmlspecialchars($employee['full_name']) ?></h5>
                            <p class="text-muted mb-2"><?= htmlspecialchars($employee['position_name']) ?></p>
                            
                            <div class="mb-3">
                                <span class="badge bg-<?= 
                                    $employee['employment_status'] === 'Regular' ? 'success' : 
                                    ($employee['employment_status'] === 'Probationary' ? 'warning' : 
                                    ($employee['employment_status'] === 'Contractual' ? 'info' : 'secondary')) 
                                ?> status-badge">
                                    <?= htmlspecialchars($employee['employment_status']) ?>
                                </span>
                            </div>

                            <div class="employee-info">
                                <p><i class="fas fa-building me-2"></i> <?= htmlspecialchars($employee['department_name']) ?></p>
                                <p><i class="fas fa-id-card me-2"></i> <?= htmlspecialchars($employee['employee_id']) ?></p>
                            </div>
                            
                            <div class="d-flex justify-content-center gap-2">
                                <a href="view.php?id=<?= $employee['employee_id'] ?>" 
                                   class="btn btn-info text-white btn-action">
                                    <i class="fas fa-eye me-1"></i> View
                                </a>
                                
                                <?php if (hasRole('HR')): ?>
                                <a href="edit.php?id=<?= $employee['employee_id'] ?>" 
                                   class="btn btn-warning text-white btn-action">
                                    <i class="fas fa-edit me-1"></i> Edit
                                </a>
                                
                                <button class="btn btn-danger btn-action delete-employee" 
                                        data-id="<?= $employee['employee_id'] ?>"
                                        data-name="<?= htmlspecialchars($employee['full_name']) ?>">
                                    <i class="fas fa-trash me-1"></i> Delete
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script>
        // Show loading overlay
        function showLoading() {
            document.querySelector('.loading-overlay').style.display = 'flex';
        }

        // Hide loading overlay
        function hideLoading() {
            document.querySelector('.loading-overlay').style.display = 'none';
        }

        // Handle form submission
        document.querySelector('form').addEventListener('submit', () => {
            showLoading();
        });

        // Handle employee deletion
        document.querySelectorAll('.delete-employee').forEach(button => {
            button.addEventListener('click', async () => {
                const employeeId = button.dataset.id;
                const employeeName = button.dataset.name;
                
                const result = await Swal.fire({
                    title: 'Delete Employee',
                    html: `
                        <div class="text-center">
                            <i class="fas fa-exclamation-triangle text-warning mb-3" style="font-size: 3rem;"></i>
                            <p>Are you sure you want to delete <strong>${employeeName}</strong>?</p>
                            <p class="text-danger">This action cannot be undone.</p>
                        </div>
                    `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#e74a3b',
                    cancelButtonColor: '#858796',
                    confirmButtonText: '<i class="fas fa-trash me-2"></i>Yes, delete',
                    cancelButtonText: '<i class="fas fa-times me-2"></i>Cancel',
                    reverseButtons: true
                });

                if (result.isConfirmed) {
                    showLoading();
                    try {
                        const response = await fetch('../../api/employees/delete.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': '<?php echo $_SESSION['csrf_token']; ?>'
                            },
                            body: JSON.stringify({ employee_id: employeeId })
                        });
                        
                        const result = await response.json();
                        if (result.success) {
                            await Swal.fire({
                                title: 'Success!',
                                text: 'Employee has been deleted successfully.',
                                icon: 'success',
                                confirmButtonColor: '#1cc88a'
                            });
                            window.location.reload();
                        } else {
                            throw new Error(result.error);
                        }
                    } catch (error) {
                        hideLoading();
                        console.error('Error:', error);
                        Swal.fire({
                            title: 'Error',
                            text: error.message || 'Failed to delete employee',
                            icon: 'error',
                            confirmButtonColor: '#e74a3b'
                        });
                    }
                }
            });
        });

        // Hide loading overlay when page is fully loaded
        window.addEventListener('load', hideLoading);
    </script>
</body>
</html>
