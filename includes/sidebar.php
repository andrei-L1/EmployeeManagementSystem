<style>
    :root {
        --primary-color: #6366f1;
        --sidebar-width: 14rem;
        --sidebar-collapsed-width: 4.5rem;
    }
    
    .sidebar {
        background: linear-gradient(180deg, var(--primary-color) 10%, #4f46e5 100%);
        min-height: 100vh;
        width: var(--sidebar-width);
        position: fixed;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        color: white;
        transition: all 0.3s ease;
        z-index: 1000;
    }
    
    .sidebar .nav-link {
        color: rgba(255, 255, 255, 0.8);
        padding: 1rem;
        margin: 0.2rem 0;
        border-radius: 0.35rem;
        font-weight: 600;
        white-space: nowrap;
        overflow: hidden;
        transition: all 0.3s ease;
    }
    
    .sidebar .nav-link:hover {
        color: white;
        background-color: rgba(255, 255, 255, 0.1);
    }
    
    .sidebar .nav-link.active {
        color: white;
        background-color: rgba(255, 255, 255, 0.2);
    }
    
    .sidebar .nav-link i {
        margin-right: 0.5rem;
        width: 1.25rem;
        text-align: center;
        transition: margin 0.3s ease;
    }
    
    .sidebar-brand {
        padding: 1.5rem 1rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .sidebar-brand-text {
        transition: opacity 0.3s ease;
    }
    
    .sidebar-divider {
        border-top: 1px solid rgba(255, 255, 255, 0.15);
        margin: 1rem 0;
    }
    
    .role-badge {
        font-size: 0.7rem;
        padding: 0.35em 0.65em;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }
    
    .badge-admin {
        background-color: #7c3aed;
    }
    
    .badge-hr {
        background-color: #db2777;
    }
    
    .badge-manager {
        background-color: #ea580c;
    }
    
    .badge-employee {
        background-color: #059669;
    }
    
    /* Mobile toggle button */
    .sidebar-toggle {
        display: none;
        position: fixed;
        bottom: 1rem;
        left: 1rem;
        z-index: 1100;
        width: 3rem;
        height: 3rem;
        border-radius: 50%;
        background-color: var(--primary-color);
        color: white;
        border: none;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .sidebar-toggle:hover {
        transform: scale(1.1);
    }
    
    /* Collapsed state */
    .sidebar-collapsed {
        width: var(--sidebar-collapsed-width);
    }
    
    .sidebar-collapsed .sidebar-brand-text,
    .sidebar-collapsed .nav-link-text {
        display: none;
    }
    
    .sidebar-collapsed .nav-link {
        padding: 1rem 0.5rem;
        text-align: center;
    }
    
    .sidebar-collapsed .nav-link i {
        margin-right: 0;
        font-size: 1.1rem;
    }
    
    .sidebar-collapsed .role-badge {
        display: none;
    }
    
    /* Mobile responsiveness */
    @media (max-width: 992px) {
        .sidebar {
            transform: translateX(-100%);
            width: var(--sidebar-width);
        }
        
        .sidebar-mobile-show {
            transform: translateX(0);
        }
        
        .sidebar-toggle {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .main-content {
            margin-left: 0 !important;
        }
    }
    
    /* Smooth transition for main content */
    .main-content {
        transition: margin 0.3s ease;
    }
</style>

<?php
// Get the current page filename, e.g. 'dashboard.php' or 'record.php'
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!-- Mobile Toggle Button -->
<button class="sidebar-toggle" id="sidebarToggle">
    <i class="fas fa-bars"></i>
</button>

<!-- Sidebar -->
<div class="sidebar p-3" id="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-text">
            <h4 class="fw-bold mb-1">EmployeeTrack</h4>
            <span class="badge role-badge badge-<?= strtolower($_SESSION['user_data']['role_name']) ?>">
                <?= $_SESSION['user_data']['role_name'] ?>
            </span>
        </div>
        <button class="btn btn-sm btn-link p-0 text-white d-none d-lg-inline" id="sidebarCollapse">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <div class="sidebar-divider"></div>
    
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                <i class="fas fa-tachometer-alt"></i>
                <span class="nav-link-text">Dashboard</span>
            </a>
        </li>
        
        <?php if (hasRole('Admin') || hasRole('HR') || hasRole('Manager')): ?>
        <li class="nav-item">
            <a class="nav-link <?= $currentPage === 'list.php' ? 'active' : '' ?>" href="employees/list.php">
                <i class="fas fa-users"></i>
                <span class="nav-link-text">Employees</span>
            </a>
        </li>
        <?php endif; ?>
        
        <li class="nav-item">
            <a class="nav-link <?= $currentPage === 'record.php' ? 'active' : '' ?>" href="attendance/record.php">
                <i class="fas fa-clipboard-check"></i>
                <span class="nav-link-text">Attendance</span>
            </a>
        </li>
        
        <li class="nav-item">
            <a class="nav-link <?= $currentPage === 'request.php' ? 'active' : '' ?>" href="leave/request.php">
                <i class="fas fa-calendar-alt"></i>
                <span class="nav-link-text">Leave</span>
            </a>
        </li>
        
        <?php if (hasRole('Admin') || hasRole('HR')): ?>
        <li class="nav-item">
            <a class="nav-link <?= $currentPage === 'process.php' ? 'active' : '' ?>" href="payroll/process.php">
                <i class="fas fa-money-bill-wave"></i>
                <span class="nav-link-text">Payroll</span>
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (hasRole('Admin')): ?>
        <li class="nav-item">
            <a class="nav-link <?= $currentPage === 'reports.php' ? 'active' : '' ?>" href="admin/reports.php">
                <i class="fas fa-chart-bar"></i>
                <span class="nav-link-text">Reports</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $currentPage === 'settings.php' ? 'active' : '' ?>" href="admin/settings.php">
                <i class="fas fa-cogs"></i>
                <span class="nav-link-text">Settings</span>
            </a>
        </li>
        <?php endif; ?>
    </ul>
    
    <div class="sidebar-divider"></div>
    
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?= $currentPage === 'profile.php' ? 'active' : '' ?>" href="profile.php">
                <i class="fas fa-user"></i>
                <span class="nav-link-text">Profile</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="../auth/logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span class="nav-link-text">Logout</span>
            </a>
        </li>
    </ul>
</div>

<script>
    // Toggle sidebar on mobile
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarCollapse = document.getElementById('sidebarCollapse');
    
    sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('sidebar-mobile-show');
    });
    
    // Collapse/expand sidebar on desktop
    if (sidebarCollapse) {
        sidebarCollapse.addEventListener('click', () => {
            sidebar.classList.toggle('sidebar-collapsed');
            
            // Adjust main content margin
            const mainContent = document.querySelector('.main-content');
            if (sidebar.classList.contains('sidebar-collapsed')) {
                mainContent.style.marginLeft = '4.5rem';
            } else {
                mainContent.style.marginLeft = '14rem';
            }
            
            // Store preference in localStorage
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('sidebar-collapsed'));
        });
    }
    
    // Check for saved sidebar state
    document.addEventListener('DOMContentLoaded', () => {
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            sidebar.classList.add('sidebar-collapsed');
            const mainContent = document.querySelector('.main-content');
            if (mainContent) {
                mainContent.style.marginLeft = '4.5rem';
            }
        }
        
        // Close mobile sidebar when clicking a link
        const navLinks = document.querySelectorAll('.sidebar .nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 992) {
                    sidebar.classList.remove('sidebar-mobile-show');
                }
            });
        });
    });
    
    // Close mobile sidebar when clicking outside
    document.addEventListener('click', (e) => {
        if (window.innerWidth < 992 && 
            !sidebar.contains(e.target) && 
            e.target !== sidebarToggle) {
            sidebar.classList.remove('sidebar-mobile-show');
        }
    });
</script>