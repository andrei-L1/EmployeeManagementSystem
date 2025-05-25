# EmployeeTrack Pro

A comprehensive employee management system designed to streamline HR processes and enhance workplace efficiency.

## Overview

EmployeeTrack Pro is a modern web application that combines employee records, attendance tracking, and payroll management in one platform. It offers features such as biometric integration, real-time attendance monitoring, leave management, analytics dashboards, and a mobile app for employee self-service.

## Features

- **Biometric Integration**: Support for fingerprint and facial recognition for accurate time tracking and attendance management.
- **Real-time Attendance**: Live tracking of employee clock-ins and outs with instant notifications for managers.
- **Leave Management**: Automated leave requests, approvals, and tracking with calendar integration.
- **Analytics Dashboard**: Comprehensive reports and visualizations for workforce analytics and decision making.
- **Mobile App**: Employee self-service portal with mobile app for clocking in/out and leave requests.
- **Advanced Security**: Role-based access control, data encryption, and audit logs for complete security.

## Screenshots

- Dashboard
- Attendance
- Reports
- Employee Management
- Leave Management
- Mobile App

## Installation

1. Clone the repository:
   ```bash
   git clone <repository-url>
   ```

2. Install PHP dependencies using Composer:
   ```bash
   composer install
   ```

3. Set up the database:
   - Create a MySQL database named `employee_management_db`.
   - Import the database schema from `config/database.sql`.

4. Configure the database connection:
   - Open `config/dbcon.php` and update the database credentials if necessary.

5. Start the development server:
   ```bash
   php -S localhost:8000
   ```

## Usage

- **Registration**: Navigate to `auth/register.php` to create a new user account.
- **Login**: Use the login form on the homepage to access the system.
- **Dashboard**: After logging in, you will be redirected to the dashboard where you can manage employees, attendance, and payroll.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Contact

Developed with ❤️ by [Jed Andrei Lonsania](https://github.com/andrei-L1) 