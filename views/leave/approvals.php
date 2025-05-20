<?php
require_once '../../auth/check_login.php';
require_once '../../config/dbcon.php';

// Only managers/HR can access this page
if (!hasRole('Manager') && !hasRole('HR')) {
    header('Location: ../dashboard.php');
    exit();
}

// Get pending leave requests
$where = ["lr.status = 'Pending'"];
$params = [];

if (hasRole('Manager')) {
    $where[] = "e.department_id = ?";
    $params[] = $_SESSION['user_data']['employee_data']['department_id'];
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$stmt = $conn->prepare("
    SELECT lr.*, e.first_name, e.last_name, lt.type_name,
           DATEDIFF(lr.end_date, lr.start_date) + 1 as days,
           d.department_name
    FROM leave_requests lr
    JOIN employees e ON lr.employee_id = e.employee_id
    JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
    JOIN departments d ON e.department_id = d.department_id
    $whereClause
    ORDER BY lr.created_at DESC
");
$stmt->execute($params);
$pendingRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent approvals/rejections
$where = ["lr.status != 'Pending'"];
$params = [];

if (hasRole('Manager')) {
    $where[] = "e.department_id = ?";
    $params[] = $_SESSION['user_data']['employee_data']['department_id'];
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$stmt = $conn->prepare("
    SELECT lr.*, e.first_name, e.last_name, lt.type_name,
           DATEDIFF(lr.end_date, lr.start_date) + 1 as days,
           CONCAT(ae.first_name, ' ', ae.last_name) as approved_by_name,
           d.department_name
    FROM leave_requests lr
    JOIN employees e ON lr.employee_id = e.employee_id
    JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
    LEFT JOIN employees ae ON lr.approved_by = ae.employee_id
    JOIN departments d ON e.department_id = d.department_id
    $whereClause
    ORDER BY lr.updated_at DESC
    LIMIT 10
");
$stmt->execute($params);
$recentDecisions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Approvals | EmployeeTrack Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .main-content {
            padding-left: 60px;
            padding-right: 60px;
            padding-top: 30px;
            transition: margin-left 0.3s;
        }
    </style>
</head>
<body>
<?php include '../../includes/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Leave Approvals</h2>
            <?php if (hasRole('HR')): ?>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-primary" id="exportBtn">
                    <i class="fas fa-download"></i> Export Report
                </button>
                <button type="button" class="btn btn-outline-primary" id="printBtn">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
            <?php endif; ?>
        </div>

        <!-- Pending Requests -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Pending Requests</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <?php if (hasRole('HR')): ?>
                                <th>Department</th>
                                <?php endif; ?>
                                <th>Leave Type</th>
                                <th>Duration</th>
                                <th>Days</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingRequests as $request): ?>
                            <tr>
                                <td><?= htmlspecialchars($request['first_name'] . ' ' . $request['last_name']) ?></td>
                                <?php if (hasRole('HR')): ?>
                                <td><?= htmlspecialchars($request['department_name']) ?></td>
                                <?php endif; ?>
                                <td><?= htmlspecialchars($request['type_name']) ?></td>
                                <td>
                                    <?= date('M d, Y', strtotime($request['start_date'])) ?> to
                                    <?= date('M d, Y', strtotime($request['end_date'])) ?>
                                </td>
                                <td><?= $request['days'] ?></td>
                                <td>
                                    <span class="badge bg-warning">Pending</span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-success approve-btn" 
                                                data-id="<?= $request['leave_id'] ?>">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button class="btn btn-sm btn-danger reject-btn" 
                                                data-id="<?= $request['leave_id'] ?>">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Decisions -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Recent Decisions</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <?php if (hasRole('HR')): ?>
                                <th>Department</th>
                                <?php endif; ?>
                                <th>Leave Type</th>
                                <th>Duration</th>
                                <th>Days</th>
                                <th>Status</th>
                                <th>Approved By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentDecisions as $decision): ?>
                            <tr>
                                <td><?= htmlspecialchars($decision['first_name'] . ' ' . $decision['last_name']) ?></td>
                                <?php if (hasRole('HR')): ?>
                                <td><?= htmlspecialchars($decision['department_name']) ?></td>
                                <?php endif; ?>
                                <td><?= htmlspecialchars($decision['type_name']) ?></td>
                                <td>
                                    <?= date('M d, Y', strtotime($decision['start_date'])) ?> to
                                    <?= date('M d, Y', strtotime($decision['end_date'])) ?>
                                </td>
                                <td><?= $decision['days'] ?></td>
                                <td>
                                    <span class="badge bg-<?= $decision['status'] === 'Approved' ? 'success' : 'danger' ?>">
                                        <?= $decision['status'] ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($decision['approved_by_name']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Approval Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Leave Request Approval</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="approvalForm">
                    <input type="hidden" id="leaveId" name="leave_id">
                    <input type="hidden" id="status" name="status">
                    <div class="mb-3">
                        <label class="form-label">Comments</label>
                        <textarea class="form-control" name="comments" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="submitDecision">Submit</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Handle approve/reject buttons
    document.querySelectorAll('.approve-btn, .reject-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const leaveId = btn.dataset.id;
            const status = btn.classList.contains('approve-btn') ? 'Approved' : 'Rejected';
            
            document.getElementById('leaveId').value = leaveId;
            document.getElementById('status').value = status;
            document.getElementById('approvalModal').querySelector('.modal-title').textContent = 
                `Leave Request ${status}`;
            
            const modal = new bootstrap.Modal(document.getElementById('approvalModal'));
            modal.show();
        });
    });
    
    // Handle decision submission
    document.getElementById('submitDecision').addEventListener('click', async () => {
        const form = document.getElementById('approvalForm');
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        try {
            const response = await fetch('../../api/leave/approve.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': '<?php echo $_SESSION['csrf_token']; ?>'
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            if (result.success) {
                window.location.reload();
            } else {
                alert('Error: ' + result.error);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to process leave request');
        }
    });
</script>
</body>
</html>
