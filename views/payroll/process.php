<?php
require_once '../../auth/check_login.php';
require_once '../../config/dbcon.php';

// Only HR/Admin can access this page
if (!hasRole('HR') && !hasRole('Admin')) {
    header('Location: ../dashboard.php');
    exit();
}

// Get all active employees
$stmt = $conn->prepare("
    SELECT e.*, d.department_name, p.position_name, p.base_salary
    FROM employees e
    JOIN departments d ON e.department_id = d.department_id
    JOIN positions p ON e.position_id = p.position_id
    WHERE e.employment_status IN ('Regular', 'Probationary')
    AND e.deleted_at IS NULL
    ORDER BY d.department_name, e.last_name, e.first_name
");
$stmt->execute();
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent payroll periods
$stmt = $conn->prepare("
    SELECT DISTINCT pay_period_start, pay_period_end
    FROM payroll
    WHERE deleted_at IS NULL
    ORDER BY pay_period_start DESC
    LIMIT 5
");
$stmt->execute();
$recentPeriods = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Payroll | EmployeeTrack Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
    <style>
        .main-content {
            padding-left: 60px;
            padding-right: 60px;
            padding-top: 30px;
            transition: margin-left 0.3s;
        }
        .employee-card {
            transition: all 0.3s ease;
        }
        .employee-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .employee-card.selected {
            border-color: #0d6efd;
            background-color: #f8f9ff;
        }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Process Payroll</h2>
            <div>
                <a href="reports.php" class="btn btn-outline-primary">
                    <i class="fas fa-file-alt"></i> View Reports
                </a>
            </div>
        </div>

        <!-- Payroll Period Selection -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Payroll Period</h5>
            </div>
            <div class="card-body">
                <form id="payrollForm" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" name="end_date" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Pay Date</label>
                        <input type="date" class="form-control" name="pay_date" required>
                    </div>
                </form>
            </div>
        </div>

        <!-- Employee Selection -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Select Employees</h5>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="selectAll">
                    <label class="form-check-label" for="selectAll">
                        Select All
                    </label>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach ($employees as $employee): ?>
                    <div class="col-md-4">
                        <div class="card employee-card">
                            <div class="card-body">
                                <div class="form-check">
                                    <input class="form-check-input employee-checkbox" 
                                           type="checkbox" 
                                           name="employee_ids[]" 
                                           value="<?= $employee['employee_id'] ?>">
                                    <label class="form-check-label">
                                        <h6 class="mb-1"><?= $employee['first_name'] . ' ' . $employee['last_name'] ?></h6>
                                        <small class="text-muted">
                                            <?= $employee['department_name'] ?> - <?= $employee['position_name'] ?><br>
                                            Base Salary: ₱<?= number_format($employee['base_salary'], 2) ?>
                                        </small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Recent Payroll Periods -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Recent Payroll Periods</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPeriods as $period): ?>
                            <tr>
                                <td>
                                    <?= date('M j, Y', strtotime($period['pay_period_start'])) ?> - 
                                    <?= date('M j, Y', strtotime($period['pay_period_end'])) ?>
                                </td>
                                <td>
                                    <a href="reports.php?start=<?= $period['pay_period_start'] ?>&end=<?= $period['pay_period_end'] ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        View Details
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Generate Payroll Button -->
        <div class="text-center mt-4">
            <button id="generatePayroll" class="btn btn-primary btn-lg px-5">
                <i class="fas fa-money-bill-wave"></i> Generate Payroll
            </button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script>
        // Initialize date pickers
        flatpickr("input[type=date]", {
            dateFormat: "Y-m-d"
        });

        // Handle select all checkbox
        document.getElementById('selectAll').addEventListener('change', function() {
            document.querySelectorAll('.employee-checkbox').forEach(checkbox => {
                checkbox.checked = this.checked;
                checkbox.closest('.employee-card').classList.toggle('selected', this.checked);
            });
        });

        // Handle individual checkboxes
        document.querySelectorAll('.employee-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                this.closest('.employee-card').classList.toggle('selected', this.checked);
            });
        });

        // Handle payroll generation
        document.getElementById('generatePayroll').addEventListener('click', async () => {
            try {
                const form = document.getElementById('payrollForm');
                const formData = new FormData(form);
                
                // Get selected employee IDs
                const selectedEmployees = Array.from(document.querySelectorAll('.employee-checkbox:checked'))
                    .map(checkbox => checkbox.value);
                
                if (selectedEmployees.length === 0) {
                    await Swal.fire({
                        title: 'Warning',
                        text: 'Please select at least one employee',
                        icon: 'warning'
                    });
                    return;
                }

                // Validate dates
                const startDate = new Date(formData.get('start_date'));
                const endDate = new Date(formData.get('end_date'));
                const payDate = new Date(formData.get('pay_date'));

                if (startDate > endDate) {
                    await Swal.fire({
                        title: 'Invalid Dates',
                        text: 'Start date cannot be after end date',
                        icon: 'error'
                    });
                    return;
                }

                if (payDate < endDate) {
                    await Swal.fire({
                        title: 'Invalid Pay Date',
                        text: 'Pay date cannot be before end date',
                        icon: 'error'
                    });
                    return;
                }

                const result = await Swal.fire({
                    title: 'Generate Payroll',
                    text: `Generate payroll for ${selectedEmployees.length} employee(s)?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, generate',
                    cancelButtonText: 'Cancel'
                });

                if (result.isConfirmed) {
                    // Show loading state
                    const generateBtn = document.getElementById('generatePayroll');
                    const originalText = generateBtn.innerHTML;
                    generateBtn.disabled = true;
                    generateBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Generating...';

                    try {
                        const response = await fetch('../../api/payroll/generate.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': '<?php echo $_SESSION['csrf_token']; ?>'
                            },
                            body: JSON.stringify({
                                employee_ids: selectedEmployees,
                                start_date: formData.get('start_date'),
                                end_date: formData.get('end_date'),
                                pay_date: formData.get('pay_date')
                            })
                        });

                        const result = await response.json();
                        if (result.success) {
                            await Swal.fire({
                                title: 'Success!',
                                text: 'Payroll generated successfully',
                                icon: 'success'
                            });
                            window.location.href = 'reports.php';
                        } else {
                            throw new Error(result.error);
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        Swal.fire({
                            title: 'Error',
                            text: error.message || 'Failed to generate payroll',
                            icon: 'error'
                        });
                    } finally {
                        generateBtn.disabled = false;
                        generateBtn.innerHTML = originalText;
                    }
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error',
                    text: error.message || 'An unexpected error occurred',
                    icon: 'error'
                });
            }
        });
    </script>
</body>
</html>