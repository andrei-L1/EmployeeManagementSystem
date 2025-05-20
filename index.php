<?php 
require_once 'config/dbcon.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EmployeeTrack Pro - Employee Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        
        .hero-section {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 5rem 0;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiPjxkZWZzPjxwYXR0ZXJuIGlkPSJwYXR0ZXJuIiB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHBhdHRlcm5Vbml0cz0idXNlclNwYWNlT25Vc2UiIHBhdHRlcm5UcmFuc2Zvcm09InJvdGF0ZSg0NSkiPjxyZWN0IHdpZHRoPSIyMCIgaGVpZ2h0PSIyMCIgZmlsbD0icmdiYSgyNTUsMjU1LDI1NSwwLjA1KSIvPjwvcGF0dGVybj48L2RlZnM+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0idXJsKCNwYXR0ZXJuKSIvPjwvc3ZnPg==');
            opacity: 0.3;
        }
        
        .feature-card {
            border: none;
            border-radius: 10px;
            transition: transform 0.3s, box-shadow 0.3s;
            height: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .feature-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }
        
        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .navbar {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            font-weight: 700;
            color: var(--secondary-color);
        }
        
        .nav-link {
            color: var(--dark-color);
            font-weight: 500;
        }
        
        .nav-link:hover {
            color: var(--primary-color);
        }
        
        .login-container {
            max-width: 400px;
            margin: 0 auto;
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        footer {
            background-color: var(--secondary-color);
            color: white;
            padding: 3rem 0;
        }
        
        .screenshot {
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .screenshot:hover {
            transform: scale(1.02);
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .stats-label {
            font-size: 1rem;
            color: var(--dark-color);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
    </style>
    <style>
        .alert.fade {
            opacity: 0 !important;
        }
    </style>
    <style>
    /* Modern modal enhancements */
    #loginModal .modal-content {
        border-radius: 1.5rem !important;
        border: none;
        box-shadow: 0 8px 32px rgba(44, 62, 80, 0.18);
    }
    #loginModal .form-control:focus {
        border-color: #3498db;
        box-shadow: 0 0 0 0.2rem rgba(52,152,219,.15);
    }
    #loginModal .btn-primary {
        background: linear-gradient(90deg, #3498db 60%, #6dd5fa 100%);
        border: none;
    }
    #loginModal .btn-primary:hover {
        background: linear-gradient(90deg, #2980b9 60%, #3498db 100%);
    }
</style>
    
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-user-clock me-2"></i>EmployeeTrack Pro
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#screenshots">Screenshots</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#pricing">Pricing</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-outline-primary" href="#" data-bs-toggle="modal" data-bs-target="#loginModal">Login</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">Modern Employee Management & Time Tracking</h1>
                    <p class="lead mb-4">Streamline your workforce management with our comprehensive solution that combines employee records, attendance tracking, and payroll management in one platform.</p>
                    <div class="d-flex gap-3">
                        <a href="register.html" class="btn btn-primary btn-lg px-4">Get Started</a>
                        <a href="#demo" class="btn btn-outline-light btn-lg px-4">View Demo</a>
                    </div>
                </div>
                <div class="col-lg-6 d-none d-lg-block">
                    <img src="https://via.placeholder.com/600x400" alt="Dashboard Preview" class="img-fluid rounded shadow screenshot">
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="py-5 bg-white">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-3">
                    <div class="stats-number">10,000+</div>
                    <div class="stats-label">Employees Tracked</div>
                </div>
                <div class="col-md-3">
                    <div class="stats-number">500+</div>
                    <div class="stats-label">Companies</div>
                </div>
                <div class="col-md-3">
                    <div class="stats-number">99.9%</div>
                    <div class="stats-label">Uptime</div>
                </div>
                <div class="col-md-3">
                    <div class="stats-number">24/7</div>
                    <div class="stats-label">Support</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Powerful Features</h2>
                <p class="lead text-muted">Everything you need to manage your workforce efficiently</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card feature-card p-4">
                        <div class="feature-icon">
                            <i class="fas fa-fingerprint"></i>
                        </div>
                        <h4>Biometric Integration</h4>
                        <p>Support for fingerprint and facial recognition for accurate time tracking and attendance management.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card p-4">
                        <div class="feature-icon">
                            <i class="fas fa-user-clock"></i>
                        </div>
                        <h4>Real-time Attendance</h4>
                        <p>Live tracking of employee clock-ins and outs with instant notifications for managers.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card p-4">
                        <div class="feature-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h4>Leave Management</h4>
                        <p>Automated leave requests, approvals, and tracking with calendar integration.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card p-4">
                        <div class="feature-icon">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <h4>Analytics Dashboard</h4>
                        <p>Comprehensive reports and visualizations for workforce analytics and decision making.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card p-4">
                        <div class="feature-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h4>Mobile App</h4>
                        <p>Employee self-service portal with mobile app for clocking in/out and leave requests.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card p-4">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h4>Advanced Security</h4>
                        <p>Role-based access control, data encryption, and audit logs for complete security.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Screenshots Section -->
    <section id="screenshots" class="py-5 bg-white">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">System Screenshots</h2>
                <p class="lead text-muted">See how EmployeeTrack Pro looks in action</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <img src="https://via.placeholder.com/400x250" alt="Dashboard" class="img-fluid screenshot">
                </div>
                <div class="col-md-4">
                    <img src="https://via.placeholder.com/400x250" alt="Attendance" class="img-fluid screenshot">
                </div>
                <div class="col-md-4">
                    <img src="https://via.placeholder.com/400x250" alt="Reports" class="img-fluid screenshot">
                </div>
                <div class="col-md-4">
                    <img src="https://via.placeholder.com/400x250" alt="Employee Management" class="img-fluid screenshot">
                </div>
                <div class="col-md-4">
                    <img src="https://via.placeholder.com/400x250" alt="Leave Management" class="img-fluid screenshot">
                </div>
                <div class="col-md-4">
                    <img src="https://via.placeholder.com/400x250" alt="Mobile App" class="img-fluid screenshot">
                </div>
            </div>
        </div>
    </section>

    <!-- Demo Section -->
    <section id="demo" class="py-5 bg-light">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h2 class="fw-bold mb-4">Experience the Power of EmployeeTrack Pro</h2>
                    <p class="lead mb-4">See how our system can transform your workforce management with a personalized demo.</p>
                    <ul class="list-unstyled mb-4">
                        <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i> 30-minute live demo</li>
                        <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i> Q&A with our experts</li>
                        <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i> Customized to your needs</li>
                    </ul>
                    <a href="#contact" class="btn btn-primary btn-lg px-4">Request Demo</a>
                </div>
                <div class="col-lg-6 d-none d-lg-block">
                    <img src="https://via.placeholder.com/600x400" alt="Demo Preview" class="img-fluid rounded shadow">
                </div>
            </div>
        </div>
    </section>

  <!-- Login Modal -->
<div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg p-0 overflow-hidden" style="background: linear-gradient(135deg, #f8fafc 60%, #e3eafc 100%);">
            <div class="px-4 py-5">
                <form id="loginForm" action="auth/login.php" method="POST">
                    <div class="text-center mb-4">
                        <div class="mb-3">
                            <span class="d-inline-flex align-items-center justify-content-center rounded-circle shadow" style="width:56px;height:56px;background:#3498db10;">
                                <i class="fas fa-user-lock fa-2x text-primary"></i>
                            </span>
                        </div>
                        <h2 class="fw-bold mb-1">Welcome Back</h2>
                        <?php if (isset($_SESSION['login_error'])): ?>
                            <div class="alert alert-danger show mt-3 mb-2" role="alert">
                                <?= htmlspecialchars($_SESSION['login_error']) ?>
                            </div>
                        <?php endif; ?>
                        <p class="text-muted mb-0">Log in to your account to continue</p>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="text" class="form-control rounded-3 shadow-sm" id="loginIdentifier" name="identifier" 
                               placeholder="Username or Email" required autocomplete="username">
                        <label for="loginIdentifier">Username or Email</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="password" class="form-control rounded-3 shadow-sm" id="loginPassword" name="password" 
                               placeholder="Password" required minlength="6" autocomplete="current-password">
                        <label for="loginPassword">Password</label>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <small><a href="auth/register.php" class="text-decoration-none link-primary">Don't have an account?</a></small>
                        <small><a href="auth/forgot-password.php" class="text-decoration-none link-primary">Forgot password?</a></small>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary py-2 rounded-3 fw-semibold shadow-sm">
                            <i class="fas fa-sign-in-alt me-2"></i>Login
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


    <!-- Footer -->
    <footer id="contact" class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h5 class="mb-4"><i class="fas fa-user-clock me-2"></i>EmployeeTrack Pro</h5>
                    <p>Modern workforce management solution designed to streamline your HR processes and improve productivity.</p>
                    <div class="mt-4">
                        <a href="#" class="text-white me-3"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6 mb-4">
                    <h5 class="mb-4">Product</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="text-white">Features</a></li>
                        <li class="mb-2"><a href="#" class="text-white">Pricing</a></li>
                        <li class="mb-2"><a href="#" class="text-white">Screenshots</a></li>
                        <li class="mb-2"><a href="#" class="text-white">Demo</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-6 mb-4">
                    <h5 class="mb-4">Company</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="text-white">About Us</a></li>
                        <li class="mb-2"><a href="#" class="text-white">Careers</a></li>
                        <li class="mb-2"><a href="#" class="text-white">Blog</a></li>
                        <li class="mb-2"><a href="#" class="text-white">Contact</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 mb-4">
                    <h5 class="mb-4">Contact Us</h5>
                    <ul class="list-unstyled">
                        <li class="mb-3"><i class="fas fa-map-marker-alt me-2"></i> 123 Business Ave, Suite 100, San Francisco, CA 94107</li>
                        <li class="mb-3"><i class="fas fa-phone me-2"></i> +1 (555) 123-4567</li>
                        <li class="mb-3"><i class="fas fa-envelope me-2"></i> info@employeetrackpro.com</li>
                    </ul>
                </div>
            </div>
            <hr class="mt-4 mb-4" style="border-color: rgba(255,255,255,0.1);">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0">© 2023 EmployeeTrack Pro. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <a href="#" class="text-white me-3">Privacy Policy</a>
                    <a href="#" class="text-white me-3">Terms of Service</a>
                    <a href="#" class="text-white">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    <?php if (isset($_SESSION['login_error']) || (isset($_GET['registered']) && $_GET['registered'] == 1)): ?>
        var loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
        loginModal.show();

        document.getElementById('loginModal').addEventListener('shown.bs.modal', function () {
            const alert = document.querySelector('#loginModal .alert');
            if (alert) {
                setTimeout(() => {
                    alert.classList.add('fade', 'show');
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }, 3000);
            }
        });
    <?php unset($_SESSION['login_error']); ?>
    <?php endif; ?>
});
</script>





</body>
</html>