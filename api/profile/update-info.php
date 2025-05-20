<?php
header("Content-Type: application/json");
require_once '../../auth/check_login.php';
require_once '../../config/dbcon.php';

$response = ['success' => false, 'error' => ''];

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        throw new Exception('Invalid request data');
    }

    // Validate required fields
    $requiredFields = ['contact_number', 'address'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            throw new Exception("$field is required");
        }
    }

    // Validate contact number format (basic validation)
    if (!preg_match('/^[0-9+\-\s()]{10,15}$/', $data['contact_number'])) {
        throw new Exception('Invalid contact number format');
    }

    // Begin transaction
    $conn->beginTransaction();

    try {
        // Get current data for audit log
        $stmt = $conn->prepare("
            SELECT contact_number, address 
            FROM employees 
            WHERE employee_id = ?
        ");
        $stmt->execute([$_SESSION['user_data']['employee_id']]);
        $oldData = $stmt->fetch(PDO::FETCH_ASSOC);

        // Update employee information
        $stmt = $conn->prepare("
            UPDATE employees 
            SET contact_number = ?,
                address = ?,
                updated_at = NOW()
            WHERE employee_id = ?
        ");
        $stmt->execute([
            $data['contact_number'],
            $data['address'],
            $_SESSION['user_data']['employee_id']
        ]);

        // Log action
        $stmt = $conn->prepare("
            INSERT INTO audit_logs 
            (user_id, action, table_affected, record_id, old_values, new_values, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_data']['user_id'],
            'PROFILE_INFO_UPDATE',
            'employees',
            $_SESSION['user_data']['employee_id'],
            json_encode($oldData),
            json_encode([
                'contact_number' => $data['contact_number'],
                'address' => $data['address']
            ]),
            $_SERVER['REMOTE_ADDR']
        ]);

        $conn->commit();
        $response['success'] = true;
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?> 