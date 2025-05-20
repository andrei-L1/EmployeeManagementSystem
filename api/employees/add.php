<?php
header("Content-Type: application/json");
require_once '../../auth/check_login.php';
require_once '../../config/dbcon.php';

$response = ['success' => false, 'error' => ''];

try {
    // Only Admin and HR can add employees
    if (!hasRole('Admin') && !hasRole('HR')) {
        throw new Exception('Unauthorized access');
    }

    // Get request data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $requiredFields = [
        'first_name', 'last_name', 'email', 'contact_number', 'birth_date',
        'gender', 'address', 'department_id', 'position_id', 'employment_status',
        'hire_date', 'base_salary', 'username', 'password', 'role_id'
    ];
    
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email format");
    }

    // Check if email already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND deleted_at IS NULL");
    $stmt->execute([$data['email']]);
    if ($stmt->rowCount() > 0) {
        throw new Exception("Email already exists");
    }

    // Check if username already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND deleted_at IS NULL");
    $stmt->execute([$data['username']]);
    if ($stmt->rowCount() > 0) {
        throw new Exception("Username already exists");
    }

    // Begin transaction
    $conn->beginTransaction();

    try {
        // Create user account
        $stmt = $conn->prepare("
            INSERT INTO users (username, email, password, role_id, is_active, created_at)
            VALUES (?, ?, ?, ?, TRUE, NOW())
        ");
        $stmt->execute([
            $data['username'],
            $data['email'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['role_id']
        ]);
        
        $userId = $conn->lastInsertId();

        // Create employee record
        $stmt = $conn->prepare("
            INSERT INTO employees (
                user_id, first_name, middle_name, last_name, email,
                contact_number, birth_date, gender, address,
                department_id, position_id, employment_status,
                hire_date, base_salary, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $userId,
            $data['first_name'],
            $data['middle_name'] ?? null,
            $data['last_name'],
            $data['email'],
            $data['contact_number'],
            $data['birth_date'],
            $data['gender'],
            $data['address'],
            $data['department_id'],
            $data['position_id'],
            $data['employment_status'],
            $data['hire_date'],
            $data['base_salary']
        ]);
        
        $employeeId = $conn->lastInsertId();

        // Log action
        $stmt = $conn->prepare("
            INSERT INTO audit_logs 
            (user_id, action, table_affected, record_id, new_values, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_data']['user_id'],
            'CREATE_EMPLOYEE',
            'employees',
            $employeeId,
            json_encode($data),
            $_SERVER['REMOTE_ADDR']
        ]);

        $conn->commit();
        $response['success'] = true;
        $response['employee_id'] = $employeeId;
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?> 