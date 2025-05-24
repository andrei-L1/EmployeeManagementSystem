<?php
require_once(__DIR__ . '/../config/dbcon.php');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if no active session
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header('Location: login.php');
    exit();
}



// Check if user still exists and is active
$stmt = $conn->prepare("
    SELECT u.*, r.role_name 
    FROM users u
    JOIN roles r ON u.role_id = r.role_id
    WHERE u.user_id = ? AND u.is_active = TRUE AND u.deleted_at IS NULL
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// If user not found or inactive
if (!$user) {
    session_unset();
    session_destroy();
    header('Location: login.php?error=session_expired');
    exit();
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Session timeout (30 minutes)
$inactive = 1800;
if (isset($_SESSION['last_activity'])) {
    $session_life = time() - $_SESSION['last_activity'];
    if ($session_life > $inactive) {
        session_unset();
        session_destroy();
        header('Location: login.php?error=session_timeout');
        exit();
    }
}

// Check for CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $headers = getallheaders();
    $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : 
                 (isset($headers['X-CSRF-Token']) ? $headers['X-CSRF-Token'] : null);
                 
    if (!$csrf_token || $csrf_token !== $_SESSION['csrf_token']) {
        if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
            header('Content-Type: application/json');
            die(json_encode(['success' => false, 'error' => 'CSRF token validation failed']));
        } else {
            die('CSRF token validation failed');
        }
    }
}

// Generate new CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Store user data in session
$_SESSION['user_data'] = [
    'user_id' => $user['user_id'],
    'username' => $user['username'],
    'email' => $user['email'],
    'role_id' => $user['role_id'],
    'role_name' => $user['role_name'],
    'last_login' => $user['last_login']
];

// Function to check user role
function hasRole($requiredRole) {
    if (!isset($_SESSION['user_data']['role_name'])) {
        return false;
    }
    return $_SESSION['user_data']['role_name'] === $requiredRole;
}

// Function to check permission (for more granular control)
function hasPermission($requiredPermission) {
    // You would implement this based on a permissions table
    // For now, we'll just check roles
    return true;
}

// Logout after inactivity
function checkInactivity() {
    $inactive = 1800; // 30 minutes in seconds
    if (isset($_SESSION['last_activity'])) {
        $session_life = time() - $_SESSION['last_activity'];
        if ($session_life > $inactive) {
            session_unset();
            session_destroy();
            header('Location: login.php?error=inactivity');
            exit();
        }
    }
    $_SESSION['last_activity'] = time();
}

// Check for employee record
function getEmployeeData($conn) {
    if (!isset($_SESSION['user_data']['employee_data'])) {
        $stmt = $conn->prepare("
            SELECT e.*, d.department_name, p.position_name 
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.department_id
            LEFT JOIN positions p ON e.position_id = p.position_id
            WHERE e.user_id = ? AND e.deleted_at IS NULL
        ");
        $stmt->execute([$_SESSION['user_data']['user_id']]);
        $_SESSION['user_data']['employee_data'] = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    return $_SESSION['user_data']['employee_data'];
}

// Get employee data if exists
$employeeData = getEmployeeData($conn);

// Add employee data to session if available
if ($employeeData) {
    $_SESSION['user_data']['employee_id'] = $employeeData['employee_id'];
    $_SESSION['user_data']['full_name'] = trim(
        $employeeData['first_name'] . ' ' . 
        ($employeeData['middle_name'] ? $employeeData['middle_name'] . ' ' : '') . 
        $employeeData['last_name']
    );
    $_SESSION['user_data']['position'] = $employeeData['position_name'];
    $_SESSION['user_data']['department'] = $employeeData['department_name'];
} else {
    // For admin users, set a default full name from username
    $_SESSION['user_data']['full_name'] = $_SESSION['user_data']['username'];
}

// Update last login time (once per session)
if (!isset($_SESSION['last_login_updated'])) {
    $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_data']['user_id']]);
    $_SESSION['last_login_updated'] = true;
}

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Prevent access to time tracking outside work hours (optional)
if (basename($_SERVER['PHP_SELF']) === 'record.php') {
    $currentHour = date('H');
    if ($currentHour < 6 || $currentHour > 20) { // 6AM to 8PM only
        $_SESSION['error'] = "Time tracking only available during work hours (6:00 AM - 8:00 PM Philippine Time)";
        header("Location: ../dashboard.php");
        exit();
    }
}

// Prevent multiple clock-ins - only for non-admin users
if (!hasRole('Admin') && isset($_SESSION['user_data']['employee_id'])) {
    $stmt = $conn->prepare("SELECT * FROM attendance_records 
                          WHERE employee_id = ? 
                          AND date = CURDATE() 
                          AND time_out IS NULL");
    $stmt->execute([$_SESSION['user_data']['employee_id']]);
    if ($stmt->rowCount() > 0 && basename($_SERVER['PHP_SELF']) === 'clock-in.php') {
        header("Location: ../attendance/record.php");
        exit();
    }
}
?>