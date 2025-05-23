DELIMITER //

CREATE PROCEDURE ProcessPayroll(
    IN p_employee_id INT,
    IN p_start_date DATE,
    IN p_end_date DATE,
    OUT p_base_salary DECIMAL(10,2),
    OUT p_overtime_pay DECIMAL(10,2),
    OUT p_deductions DECIMAL(10,2),
    OUT p_bonuses DECIMAL(10,2),
    OUT p_net_pay DECIMAL(10,2)
)
BEGIN
    DECLARE v_daily_rate DECIMAL(10,2);
    DECLARE v_working_days INT DEFAULT 22;
    
    -- Get employee base salary and calculate daily rate
    SELECT p.base_salary INTO p_base_salary
    FROM employees e
    JOIN positions p ON e.position_id = p.position_id
    WHERE e.employee_id = p_employee_id
    AND e.deleted_at IS NULL;
    
    SET v_daily_rate = p_base_salary / v_working_days;
    
    -- Calculate overtime pay
    SELECT COALESCE(SUM(hours * (v_daily_rate / 8) * 1.25), 0) INTO p_overtime_pay
    FROM overtime_requests
    WHERE employee_id = p_employee_id
    AND date BETWEEN p_start_date AND p_end_date
    AND status = 'Approved'
    AND deleted_at IS NULL;
    
    -- Calculate deductions
    SELECT COALESCE(SUM(amount), 0) INTO p_deductions
    FROM salary_adjustments
    WHERE employee_id = p_employee_id
    AND adjustment_type = 'Deduction'
    AND effective_date BETWEEN p_start_date AND p_end_date
    AND deleted_at IS NULL;
    
    -- Calculate bonuses
    SELECT COALESCE(SUM(amount), 0) INTO p_bonuses
    FROM salary_adjustments
    WHERE employee_id = p_employee_id
    AND adjustment_type = 'Bonus'
    AND effective_date BETWEEN p_start_date AND p_end_date
    AND deleted_at IS NULL;
    
    -- Calculate net pay
    SET p_net_pay = p_base_salary + p_overtime_pay + p_bonuses - p_deductions;
    
END //

DELIMITER ; 