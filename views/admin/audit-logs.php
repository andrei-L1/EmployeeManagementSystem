<?php
require_once '../../auth/check_login.php';
require_once '../../config/dbcon.php';

// Ensure only admin can access this page
if (!hasRole('Admin')) {
    header('Location: ../dashboard.php');
    exit();
}

// Get filter parameters
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$user_id = $_GET['user_id'] ?? '';
$action_type = $_GET['action_type'] ?? '';
$table_affected = $_GET['table_affected'] ?? '';

// Build the query
$query = "
    SELECT al.*, u.username, u.email,
           CASE 
               WHEN al.action = 'Error' THEN 'danger'
               WHEN al.action = 'Update' THEN 'warning'
               WHEN al.action = 'Delete' THEN 'danger'
               WHEN al.action = 'Create' THEN 'success'
               ELSE 'info'
           END as action_color
    FROM audit_logs al 
    LEFT JOIN users u ON al.user_id = u.user_id 
    WHERE al.action_timestamp BETWEEN ? AND ? 
";

$params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];

if ($user_id) {
    $query .= " AND al.user_id = ?";
    $params[] = $user_id;
}

if ($action_type) {
    $query .= " AND al.action = ?";
    $params[] = $action_type;
}

if ($table_affected) {
    $query .= " AND al.table_affected = ?";
    $params[] = $table_affected;
}

$query .= " ORDER BY al.action_timestamp DESC";

// Get total records for pagination
$stmt = $conn->prepare(str_replace('al.*, u.username, u.email,', 'COUNT(*) as total,', $query));
$stmt->execute($params);
$total_records = $stmt->fetchColumn();

// Pagination
$records_per_page = 20;
$total_pages = ceil($total_records / $records_per_page);
$current_page = $_GET['page'] ?? 1;
$offset = ($current_page - 1) * $records_per_page;

// Modify the query to include LIMIT and OFFSET directly
$query .= " LIMIT " . (int)$records_per_page . " OFFSET " . (int)$offset;

// Get the logs
$stmt = $conn->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique users for filter
$users = $conn->query("SELECT user_id, username FROM users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);

// Get unique actions for filter
$actions = $conn->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll(PDO::FETCH_ASSOC);

// Get unique tables for filter
$tables = $conn->query("SELECT DISTINCT table_affected FROM audit_logs ORDER BY table_affected")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - EmployeeTrack Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <style>
        .audit-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .audit-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        
        .audit-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .audit-body {
            padding: 1.5rem;
        }
        
        .filter-section {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .log-item {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            transition: background-color 0.2s;
        }
        
        .log-item:hover {
            background-color: #f8fafc;
        }
        
        .log-item:last-child {
            border-bottom: none;
        }
        
        .action-badge {
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 600;
            border-radius: 0.25rem;
        }
        
        .changes-diff {
            font-family: monospace;
            font-size: 0.875rem;
            background: #f8fafc;
            padding: 0.5rem;
            border-radius: 0.25rem;
            margin-top: 0.5rem;
        }
        
        .changes-diff .added {
            color: #059669;
            background: #ecfdf5;
        }
        
        .changes-diff .removed {
            color: #dc2626;
            background: #fef2f2;
        }
        
        .pagination {
            margin-top: 1.5rem;
        }
        
        .export-buttons {
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="audit-container">
            <div class="audit-card">
                <div class="audit-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="fas fa-history me-2"></i>Audit Logs</h4>
                    <div class="export-buttons">
                        <button class="btn btn-outline-primary btn-sm" onclick="exportToCSV()">
                            <i class="fas fa-file-csv me-1"></i> Export CSV
                        </button>
                        <button class="btn btn-outline-danger btn-sm" onclick="exportToPDF()">
                            <i class="fas fa-file-pdf me-1"></i> Export PDF
                        </button>
                    </div>
                </div>
                
                <div class="audit-body">
                    <!-- Filters -->
                    <div class="filter-section">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Date Range</label>
                                <div class="input-group">
                                    <input type="date" class="form-control" name="start_date" value="<?= $start_date ?>">
                                    <span class="input-group-text">to</span>
                                    <input type="date" class="form-control" name="end_date" value="<?= $end_date ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">User</label>
                                <select class="form-select" name="user_id">
                                    <option value="">All Users</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?= $user['user_id'] ?>" <?= $user_id == $user['user_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($user['username']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">Action Type</label>
                                <select class="form-select" name="action_type">
                                    <option value="">All Actions</option>
                                    <?php foreach ($actions as $action): ?>
                                        <option value="<?= $action['action'] ?>" <?= $action_type == $action['action'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($action['action']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">Table</label>
                                <select class="form-select" name="table_affected">
                                    <option value="">All Tables</option>
                                    <?php foreach ($tables as $table): ?>
                                        <option value="<?= $table['table_affected'] ?>" <?= $table_affected == $table['table_affected'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($table['table_affected']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter me-1"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Logs Table -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Table</th>
                                    <th>Record ID</th>
                                    <th>Changes</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?= date('M j, Y g:i A', strtotime($log['action_timestamp'])) ?></td>
                                        <td>
                                            <?php if ($log['username']): ?>
                                                <span class="fw-bold"><?= htmlspecialchars($log['username']) ?></span>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars($log['email']) ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">System</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="action-badge bg-<?= $log['action_color'] ?>-subtle text-<?= $log['action_color'] ?>">
                                                <?= htmlspecialchars($log['action']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($log['table_affected']) ?></td>
                                        <td><?= $log['record_id'] ?: '-' ?></td>
                                        <td>
                                            <?php if ($log['old_values'] || $log['new_values']): ?>
                                                <button class="btn btn-link btn-sm p-0" 
                                                        onclick="showChanges(<?= htmlspecialchars(json_encode([
                                                            'old' => json_decode($log['old_values'], true),
                                                            'new' => json_decode($log['new_values'], true)
                                                        ])) ?>)">
                                                    View Changes
                                                </button>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $log['ip_address'] ?: '-' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?= $current_page == 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $current_page - 1 ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&user_id=<?= $user_id ?>&action_type=<?= $action_type ?>&table_affected=<?= $table_affected ?>">
                                        Previous
                                    </a>
                                </li>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?= $current_page == $i ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&user_id=<?= $user_id ?>&action_type=<?= $action_type ?>&table_affected=<?= $table_affected ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?= $current_page == $total_pages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $current_page + 1 ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&user_id=<?= $user_id ?>&action_type=<?= $action_type ?>&table_affected=<?= $table_affected ?>">
                                        Next
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Changes Modal -->
    <div class="modal fade" id="changesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Changes Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="changesContent"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.5.28/dist/jspdf.plugin.autotable.min.js"></script>
    <script>
        // Show changes in modal
        function showChanges(changes) {
            const modal = new bootstrap.Modal(document.getElementById('changesModal'));
            const content = document.getElementById('changesContent');
            
            let html = '<div class="changes-diff">';
            
            if (changes.old && changes.new) {
                // Compare old and new values
                for (const key in changes.new) {
                    if (changes.old[key] !== changes.new[key]) {
                        html += `<div><strong>${key}:</strong></div>`;
                        if (changes.old[key]) {
                            html += `<div class="removed">- ${changes.old[key]}</div>`;
                        }
                        if (changes.new[key]) {
                            html += `<div class="added">+ ${changes.new[key]}</div>`;
                        }
                    }
                }
            } else if (changes.new) {
                // New record
                for (const key in changes.new) {
                    html += `<div class="added">+ ${key}: ${changes.new[key]}</div>`;
                }
            } else if (changes.old) {
                // Deleted record
                for (const key in changes.old) {
                    html += `<div class="removed">- ${key}: ${changes.old[key]}</div>`;
                }
            }
            
            html += '</div>';
            content.innerHTML = html;
            modal.show();
        }
        
        // Export to CSV
        function exportToCSV() {
            const table = document.querySelector('table');
            const rows = Array.from(table.querySelectorAll('tr'));
            
            let csv = [];
            rows.forEach(row => {
                const cells = Array.from(row.querySelectorAll('th, td'));
                const rowData = cells.map(cell => {
                    // Remove HTML tags and escape quotes
                    const text = cell.textContent.replace(/"/g, '""');
                    return `"${text}"`;
                });
                csv.push(rowData.join(','));
            });
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `audit_logs_${new Date().toISOString().split('T')[0]}.csv`;
            link.click();
        }
        
        // Export to PDF
        function exportToPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            doc.autoTable({
                html: 'table',
                theme: 'grid',
                headStyles: { fillColor: [79, 70, 229] },
                styles: { fontSize: 8 },
                margin: { top: 20 }
            });
            
            doc.save(`audit_logs_${new Date().toISOString().split('T')[0]}.pdf`);
        }
    </script>
</body>
</html> 