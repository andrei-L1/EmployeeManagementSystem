<?php
require_once '../../auth/check_login.php';
require_once '../../config/dbcon.php';

// Get available leave types
$stmt = $conn->prepare("SELECT * FROM leave_types WHERE deleted_at IS NULL");
$stmt->execute();
$leaveTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get employee's leave balance
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

// Get employee's leave history
$stmt = $conn->prepare("
    SELECT lr.*, lt.type_name,
           CONCAT(ae.first_name, ' ', ae.last_name) as approved_by_name
    FROM leave_requests lr
    JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
    LEFT JOIN employees ae ON lr.approved_by = ae.employee_id
    WHERE lr.employee_id = ? 
    AND lr.deleted_at IS NULL
    ORDER BY lr.created_at DESC
");
$stmt->execute([$_SESSION['user_data']['employee_id']]);
$leaveHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Request | EmployeeTrack Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
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

        .card-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 1.5rem;
            border-radius: 1rem 1rem 0 0;
            position: relative;
            overflow: hidden;
        }

        .card-header::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 150px;
            height: 150px;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1));
            border-radius: 50%;
            transform: translate(30%, -30%);
        }

        .leave-balance-card {
            background: var(--light);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
        }

        .leave-balance-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            border-color: var(--primary-light);
        }

        .balance-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0.5rem 0;
        }

        .balance-label {
            color: var(--dark);
            font-size: 0.875rem;
            opacity: 0.8;
        }

        .form-label {
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            border-radius: 0.75rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            padding: 0.75rem 1rem;
            transition: all 0.2s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(79, 70, 229, 0.25);
        }

        .form-control:hover, .form-select:hover {
            border-color: var(--primary-light);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            border-radius: 0.75rem;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger), #dc2626);
            border: none;
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(239, 68, 68, 0.15);
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
            background: var(--light);
        }

        .table td {
            vertical-align: middle;
            padding: 1rem;
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

        .status-badge::before {
            content: '';
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

        .loading-spinner {
            display: none;
            width: 1.5rem;
            height: 1.5rem;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top: 3px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .input-group-text {
            background: var(--light);
            border: 1px solid rgba(0, 0, 0, 0.1);
            color: var(--dark);
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 1rem;
            }

            .card {
                padding: 1.25rem;
            }

            .balance-value {
                font-size: 1.75rem;
            }

            .btn {
                padding: 0.625rem 1.25rem;
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
                        <h1 class="h3 mb-2">Leave Request</h1>
                        <p class="text-muted mb-0">
                            <i class="fas fa-calendar-alt me-2"></i>
                            Submit and manage your leave requests
                        </p>
                    </div>
                </div>
            </div>

            <!-- Leave Balance Cards -->
            <div class="row mb-4">
                <?php foreach ($leaveBalances as $balance): ?>
                <div class="col-md-4 mb-3">
                    <div class="leave-balance-card">
                        <div class="balance-label"><?= htmlspecialchars($balance['type_name']) ?></div>
                        <div class="balance-value"><?= $balance['remaining'] ?></div>
                        <div class="balance-label">days remaining</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Leave Request Form -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-paper-plane me-2"></i>Submit Leave Request</h5>
                </div>
                <div class="card-body">
                    <form id="leaveRequestForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Leave Type</label>
                                <select class="form-select" name="leave_type_id" required>
                                    <option value="">Select Leave Type</option>
                                    <?php foreach ($leaveTypes as $type): ?>
                                    <option value="<?= $type['leave_type_id'] ?>">
                                        <?= htmlspecialchars($type['type_name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Duration</label>
                                <div class="input-group">
                                    <input type="date" class="form-control" name="start_date" required>
                                    <span class="input-group-text">to</span>
                                    <input type="date" class="form-control" name="end_date" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Reason</label>
                            <textarea class="form-control" name="reason" rows="4" required 
                                    placeholder="Please provide a detailed reason for your leave request..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i> Submit Request
                        </button>
                    </form>
                </div>
            </div>

            <!-- Leave History -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Leave History</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Days</th>
                                    <th>Status</th>
                                    <th>Approved By</th>
                                    <th>Comments</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leaveHistory as $leave): ?>
                                <tr>
                                    <td><?= htmlspecialchars($leave['type_name']) ?></td>
                                    <td><?= date('M j, Y', strtotime($leave['start_date'])) ?></td>
                                    <td><?= date('M j, Y', strtotime($leave['end_date'])) ?></td>
                                    <td><?= date_diff(date_create($leave['start_date']), date_create($leave['end_date']))->days + 1 ?></td>
                                    <td>
                                        <span class="status-badge bg-<?= 
                                            $leave['status'] === 'Approved' ? 'success' : 
                                            ($leave['status'] === 'Pending' ? 'warning' : 'danger') 
                                        ?>">
                                            <?= $leave['status'] ?>
                                        </span>
                                    </td>
                                    <td><?= $leave['approved_by_name'] ?? '-' ?></td>
                                    <td><?= $leave['comments'] ?? '-' ?></td>
                                    <td>
                                        <?php if ($leave['status'] === 'Pending'): ?>
                                        <button class="btn btn-danger btn-sm cancel-leave" data-id="<?= $leave['leave_id'] ?>">
                                            <i class="fas fa-times me-1"></i> Cancel
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script>
    document.getElementById('leaveRequestForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        
        try {
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="loading-spinner"></span> Processing...';
            
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            
            const response = await fetch('../../api/leave/request.php', {
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
                    text: 'Leave request submitted successfully',
                    icon: 'success'
                });
                window.location.reload();
            } else {
                throw new Error(result.error || 'Failed to submit leave request');
            }
        } catch (error) {
            Swal.fire({
                title: 'Error',
                text: error.message,
                icon: 'error'
            });
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    });

    // Handle leave cancellation
    document.querySelectorAll('.cancel-leave').forEach(button => {
        button.addEventListener('click', async () => {
            const result = await Swal.fire({
                title: 'Cancel Leave Request',
                text: 'Are you sure you want to cancel this leave request?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, Cancel',
                cancelButtonText: 'No, Keep It',
                confirmButtonColor: '#ef4444'
            });

            if (result.isConfirmed) {
                const leaveId = button.dataset.id;
                try {
                    const response = await fetch('../../api/leave/cancel.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': '<?php echo $_SESSION['csrf_token']; ?>'
                        },
                        body: JSON.stringify({ leave_id: leaveId })
                    });
                    
                    const result = await response.json();
                    if (result.success) {
                        await Swal.fire({
                            title: 'Success!',
                            text: 'Leave request cancelled successfully',
                            icon: 'success'
                        });
                        window.location.reload();
                    } else {
                        throw new Error(result.error || 'Failed to cancel leave request');
                    }
                } catch (error) {
                    Swal.fire({
                        title: 'Error',
                        text: error.message,
                        icon: 'error'
                    });
                }
            }
        });
    });

    // Set minimum date for date inputs
    const today = new Date().toISOString().split('T')[0];
    document.querySelector('input[name="start_date"]').min = today;
    document.querySelector('input[name="end_date"]').min = today;

    // Update end date minimum when start date changes
    document.querySelector('input[name="start_date"]').addEventListener('change', function() {
        document.querySelector('input[name="end_date"]').min = this.value;
    });
    </script>
</body>
</html>