-- 1. Roles table
CREATE TABLE roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT
);

-- 2. Users table
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role_id INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (role_id) REFERENCES roles(role_id)
);

-- 3. Positions table
CREATE TABLE positions (
    position_id INT AUTO_INCREMENT PRIMARY KEY,
    position_name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    base_salary DECIMAL(10,2) NOT NULL,
    deleted_at TIMESTAMP NULL
);

-- 4. Departments table (without FK to employees yet)
CREATE TABLE departments (
    department_id INT AUTO_INCREMENT PRIMARY KEY,
    department_name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    manager_id INT,  -- FK will be added later
    deleted_at TIMESTAMP NULL
);

-- 5. Employees table
CREATE TABLE employees (
    employee_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    birth_date DATE,
    gender ENUM('Male', 'Female', 'Other'),
    contact_number VARCHAR(20),
    address TEXT,
    hire_date DATE NOT NULL,
    position_id INT NOT NULL,
    department_id INT NOT NULL,
    employment_status ENUM('Probationary', 'Regular', 'Contractual', 'Resigned', 'Terminated') DEFAULT 'Probationary',
    profile_picture VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (position_id) REFERENCES positions(position_id),
    FOREIGN KEY (department_id) REFERENCES departments(department_id)
);

-- 🔁 6. Add manager_id FK to departments
ALTER TABLE departments
ADD CONSTRAINT fk_manager_id FOREIGN KEY (manager_id) REFERENCES employees(employee_id);

-- 7. Attendance Records
CREATE TABLE attendance_records (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    date DATE NOT NULL,
    time_in TIMESTAMP NULL,
    time_out TIMESTAMP NULL,
    status ENUM('Present', 'Late', 'Half-day', 'Absent', 'On Leave') DEFAULT 'Present',
    total_hours DECIMAL(5,2) DEFAULT 0,
    notes TEXT,
    ip_address VARCHAR(45),
    device_info VARCHAR(255),
    photo_path VARCHAR(255),
    latitude DECIMAL(10,8) NULL,
    longitude DECIMAL(11,8) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
    UNIQUE KEY unique_employee_date (employee_id, date)
);

-- 8. Leave Types
CREATE TABLE leave_types (
    leave_type_id INT AUTO_INCREMENT PRIMARY KEY,
    type_name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    days_allowed INT NOT NULL,
    is_paid BOOLEAN DEFAULT TRUE,
    deleted_at TIMESTAMP NULL
);

-- 9. Leave Requests
CREATE TABLE leave_requests (
    leave_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    leave_type_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    approved_by INT,
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(leave_type_id),
    FOREIGN KEY (approved_by) REFERENCES employees(employee_id)
);

-- 10. Overtime Requests
CREATE TABLE overtime_requests (
    overtime_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    date DATE NOT NULL,
    hours DECIMAL(4,2) NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    approved_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
    FOREIGN KEY (approved_by) REFERENCES employees(employee_id)
);

-- 11. Salary Adjustments
CREATE TABLE salary_adjustments (
    adjustment_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    adjustment_type ENUM('Bonus', 'Deduction', 'Allowance', 'Other') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    effective_date DATE NOT NULL,
    approved_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
    FOREIGN KEY (approved_by) REFERENCES employees(employee_id)
);

-- 12. Payroll
CREATE TABLE payroll (
    payroll_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    pay_period_start DATE NOT NULL,
    pay_period_end DATE NOT NULL,
    basic_salary DECIMAL(10,2) NOT NULL,
    total_allowances DECIMAL(10,2) DEFAULT 0,
    total_deductions DECIMAL(10,2) DEFAULT 0,
    overtime_pay DECIMAL(10,2) DEFAULT 0,
    tax DECIMAL(10,2) DEFAULT 0,
    net_pay DECIMAL(10,2) NOT NULL,
    status ENUM('Pending', 'Processed', 'Paid') DEFAULT 'Pending',
    payment_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
);

-- 13. Audit Logs
CREATE TABLE audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(50) NOT NULL,
    table_affected VARCHAR(50) NOT NULL,
    record_id INT,
    old_values TEXT,
    new_values TEXT,
    ip_address VARCHAR(45),
    action_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert roles
INSERT INTO roles (role_name, description) VALUES 
('Admin', 'Full system access'),
('HR', 'Human Resources access'),
('Manager', 'Department manager access'),
('Employee', 'Regular employee access');

-- Insert departments
INSERT INTO departments (department_name, description) VALUES 
('Human Resources', 'HR Department'),
('Information Technology', 'IT Department'),
('Finance', 'Finance and Accounting'),
('Operations', 'Business Operations');

-- Insert positions
INSERT INTO positions (position_name, description, base_salary) VALUES 
('HR Manager', 'Human Resources Manager', 50000.00),
('IT Manager', 'IT Department Manager', 60000.00),
('Software Developer', 'Develops software applications', 45000.00),
('Accountant', 'Handles company finances', 40000.00),
('Operations Manager', 'Manages business operations', 55000.00);

INSERT INTO leave_types (type_name, description, days_allowed, is_paid) VALUES
('Annual Leave', 'Paid time off for vacation or personal reasons', 15, TRUE),
('Sick Leave', 'Paid leave for illness or medical appointments', 10, TRUE),
('Maternity Leave', 'Paid leave for childbirth and recovery', 90, TRUE),
('Paternity Leave', 'Paid leave for fathers after childbirth', 7, TRUE),
('Unpaid Leave', 'Leave without pay for personal reasons', 0, FALSE),
('Bereavement Leave', 'Leave for mourning a close relative', 5, TRUE),
('Study Leave', 'Leave granted for educational purposes', 20, TRUE);
