<style>
    :root {
        --primary-color: #6366f1;
        --sidebar-width: 16rem;
        --sidebar-collapsed-width: 5rem;
        --sidebar-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Reset and Base Styles */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    html, body {
        height: 100%;
        width: 100%;
        overflow-x: hidden;
    }

    /* Sidebar Container */
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: var(--sidebar-width);
        height: 100vh;
        background: linear-gradient(180deg, var(--primary-color) 0%, #4f46e5 100%);
        color: white;
        z-index: 1000;
        transition: var(--sidebar-transition);
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: rgba(255,255,255,0.3) transparent;
    }

    .sidebar::-webkit-scrollbar {
        width: 6px;
    }

    .sidebar::-webkit-scrollbar-thumb {
        background-color: rgba(255,255,255,0.3);
        border-radius: 3px;
    }

    /* Sidebar Brand */
    .sidebar-brand {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1.25rem 1rem;
        min-height: 70px;
    }

    .sidebar-brand-text {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .sidebar-brand-text h4 {
        margin: 0;
        font-weight: 700;
        font-size: 1.25rem;
    }

    /* Navigation Links */
    .sidebar .nav {
        padding: 0.5rem;
    }

    .sidebar .nav-link {
        display: flex;
        align-items: center;
        padding: 0.85rem 1rem;
        margin: 0.15rem 0;
        color: rgba(255, 255, 255, 0.85);
        border-radius: 0.5rem;
        font-weight: 500;
        transition: var(--sidebar-transition);
        position: relative;
    }

    .sidebar .nav-link:hover {
        color: white;
        background-color: rgba(255, 255, 255, 0.15);
        transform: translateX(4px);
    }

    .sidebar .nav-link.active {
        color: white;
        background-color: rgba(255, 255, 255, 0.25);
        font-weight: 600;
    }

    .sidebar .nav-link.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: white;
        border-radius: 4px 0 0 4px;
    }

    .sidebar .nav-link i {
        width: 1.25rem;
        margin-right: 0.75rem;
        text-align: center;
        font-size: 1.1rem;
        transition: var(--sidebar-transition);
    }

    /* Role Badges */
    .role-badge {
        font-size: 0.65rem;
        padding: 0.35em 0.65em;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        border-radius: 0.25rem;
        align-self: flex-start;
    }

    .badge-admin { background-color: rgba(124, 58, 237, 0.9); }
    .badge-hr { background-color: rgba(219, 39, 119, 0.9); }
    .badge-manager { background-color: rgba(234, 88, 12, 0.9); }
    .badge-employee { background-color: rgba(5, 150, 105, 0.9); }

    /* Dividers */
    .sidebar-divider {
        border-top: 1px solid rgba(255, 255, 255, 0.15);
        margin: 0.75rem 1rem;
    }

    /* Collapsed State */
    .sidebar-collapsed {
        width: var(--sidebar-collapsed-width);
    }

    .sidebar-collapsed .sidebar-brand-text,
    .sidebar-collapsed .nav-link-text {
        opacity: 0;
        width: 0;
        height: 0;
        overflow: hidden;
    }

    .sidebar-collapsed .nav-link {
        padding: 0.85rem 0;
        margin: 0.15rem 0.5rem;
        justify-content: center;
    }

    .sidebar-collapsed .nav-link i {
        margin-right: 0;
        font-size: 1.2rem;
    }

    .sidebar-collapsed .role-badge {
        display: none;
    }

    .sidebar-collapsed .sidebar-divider {
        margin: 0.75rem 0.5rem;
    }

    .sidebar-collapsed .sidebar-brand {
        padding: 1.25rem 0.5rem;
        justify-content: center;
    }

    /* Tooltips */
    .nav-link .tooltip-text {
        visibility: hidden;
        position: absolute;
        left: 100%;
        top: 50%;
        transform: translateY(-50%);
        background-color: #333;
        color: #fff;
        text-align: center;
        border-radius: 6px;
        padding: 0.5rem 1rem;
        margin-left: 1rem;
        white-space: nowrap;
        z-index: 1;
        opacity: 0;
        transition: opacity 0.3s;
        pointer-events: none;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .sidebar-collapsed .nav-link:hover .tooltip-text {
        visibility: visible;
        opacity: 1;
    }

    /* Mobile Toggle Button */
    .sidebar-toggle {
        display: none;
        position: fixed;
        bottom: 1.5rem;
        left: 1.5rem;
        width: 3.25rem;
        height: 3.25rem;
        border-radius: 50%;
        background-color: var(--primary-color);
        color: white;
        border: none;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        cursor: pointer;
        z-index: 1100;
        transition: all 0.3s ease;
    }

    .sidebar-toggle:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
    }

    /* Main Content Adjustments */
    .main-content {
        margin-left: var(--sidebar-width);
        width: calc(100% - var(--sidebar-width));
        min-height: 100vh;
        transition: var(--sidebar-transition);
        background-color: #f5f7fa;
    }

    .sidebar-collapsed ~ .main-content {
        margin-left: var(--sidebar-collapsed-width);
        width: calc(100% - var(--sidebar-collapsed-width));
    }

    /* Mobile Responsiveness */
    @media (max-width: 992px) {
        .sidebar {
            transform: translateX(-100%);
        }
        
        .sidebar-mobile-show {
            transform: translateX(0);
        }
        
        .main-content {
            margin-left: 0 !important;
            width: 100% !important;
        }
        
        .sidebar-toggle {
            display: flex;
            align-items: center;
            justify-content: center;
        }
    }

    @media (min-width: 993px) {
        .sidebar-toggle {
            display: none !important;
        }
    }

    /* Animations */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateX(-10px); }
        to { opacity: 1; transform: translateX(0); }
    }
    
    .sidebar .nav-item {
        animation: fadeIn 0.3s ease forwards;
        opacity: 0;
    }
    
    .sidebar .nav-item:nth-child(1) { animation-delay: 0.1s; }
    .sidebar .nav-item:nth-child(2) { animation-delay: 0.15s; }
    .sidebar .nav-item:nth-child(3) { animation-delay: 0.2s; }
    .sidebar .nav-item:nth-child(4) { animation-delay: 0.25s; }
    .sidebar .nav-item:nth-child(5) { animation-delay: 0.3s; }
    .sidebar .nav-item:nth-child(6) { animation-delay: 0.35s; }
    .sidebar .nav-item:nth-child(7) { animation-delay: 0.4s; }
    .sidebar .nav-item:nth-child(8) { animation-delay: 0.45s; }
    .sidebar .nav-item:nth-child(9) { animation-delay: 0.5s; }
    .sidebar .nav-item:nth-child(10) { animation-delay: 0.55s; }
</style>

<?php
// Get the current page filename, e.g. 'dashboard.php' or 'record.php'
$currentPage = basename($_SERVER['PHP_SELF']);

// Get the current file's directory path
$currentDir = dirname($_SERVER['PHP_SELF']);
$rootDir = '/employeeYA';

// Calculate the relative path to auth directory
$pathToRoot = '';
if (strpos($currentDir, '/views/employees') !== false || 
    strpos($currentDir, '/views/attendance') !== false || 
    strpos($currentDir, '/views/payroll') !== false || 
    strpos($currentDir, '/views/leave') !== false || 
    strpos($currentDir, '/views/admin') !== false) {
    $pathToRoot = '../../';
} elseif (strpos($currentDir, '/views') !== false) {
    $pathToRoot = '../';
} else {
    $pathToRoot = '';
}
?>

<!-- Mobile Toggle Button -->
<button class="sidebar-toggle d-flex align-items-center justify-content-center" id="sidebarToggle" aria-label="Toggle sidebar">
    <svg width="28" height="28" viewBox="0 0 28 28" fill="none" style="pointer-events: none;" xmlns="http://www.w3.org/2000/svg">
        <rect y="6" width="28" height="3" rx="1.5" fill="white"/>
        <rect y="13" width="28" height="3" rx="1.5" fill="white"/>
        <rect y="20" width="28" height="3" rx="1.5" fill="white"/>
    </svg>
</button>

<!-- Sidebar -->
<div class="sidebar p-2" id="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-text">
            <h4>EmployeeTrack</h4>
            <span class="badge role-badge badge-<?= strtolower($_SESSION['user_data']['role_name']) ?>">
                <?= $_SESSION['user_data']['role_name'] ?>
            </span>
        </div>
        <button class="btn btn-sm btn-link p-0 text-white d-none d-lg-inline" id="sidebarCollapse">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>
    
    <div class="sidebar-divider"></div>
    
    <ul class="nav flex-column px-1">
        <li class="nav-item">
            <a class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="/employeeYA/views/dashboard.php">
                <i class="fas fa-tachometer-alt"></i>
                <span class="nav-link-text">Dashboard</span>
                <span class="tooltip-text">Dashboard</span>
            </a>
        </li>
        
        <?php if (hasRole('Admin')): ?>
        <li class="nav-item">
            <a class="nav-link <?= $currentPage === 'users.php' ? 'active' : '' ?>" href="/employeeYA/views/admin/users.php">
                <i class="fas fa-user-cog"></i>
                <span class="nav-link-text">User Management</span>
                <span class="tooltip-text">User Management</span>
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (hasRole('HR') || hasRole('Manager')): ?>
        <li class="nav-item">
            <a class="nav-link <?= $currentPage === 'list.php' ? 'active' : '' ?>" href="/employeeYA/views/employees/list.php">
                <i class="fas fa-users"></i>
                <span class="nav-link-text">Employees</span>
                <span class="tooltip-text">Employees</span>
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (!hasRole('Admin')): ?>
        <li class="nav-item">
            <a class="nav-link <?= $currentPage === 'record.php' ? 'active' : '' ?>" href="/employeeYA/views/attendance/record.php">
                <i class="fas fa-clipboard-check"></i>
                <span class="nav-link-text">Attendance</span>
                <span class="tooltip-text">Attendance</span>
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (hasRole('HR') || hasRole('Manager')): ?>
        <li class="nav-item">
            <a class="nav-link <?= $currentPage === 'live-monitor.php' ? 'active' : '' ?>" href="/employeeYA/views/attendance/live-monitor.php">
                <i class="fas fa-desktop"></i>
                <span class="nav-link-text">Live Monitor</span>
                <span class="tooltip-text">Live Monitor</span>
            </a>
        </li>
        <?php endif; ?>
        
        <li class="nav-item">
            <a class="nav-link <?= $currentPage === 'request.php' ? 'active' : '' ?>" href="/employeeYA/views/leave/request.php">
                <i class="fas fa-calendar-alt"></i>
                <span class="nav-link-text">Leave</span>
                <span class="tooltip-text">Leave</span>
            </a>
        </li>
        
        <?php if (hasRole('Admin') || hasRole('HR')): ?>
        <li class="nav-item">
            <a class="nav-link <?= $currentPage === 'process.php' ? 'active' : '' ?>" href="/employeeYA/views/payroll/process.php">
                <i class="fas fa-money-bill-wave"></i>
                <span class="nav-link-text">Payroll</span>
                <span class="tooltip-text">Payroll</span>
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (hasRole('Admin')): ?>
        <li class="nav-item">
            <a class="nav-link <?= $currentPage === 'reports.php' ? 'active' : '' ?>" href="/employeeYA/views/admin/reports.php">
                <i class="fas fa-chart-bar"></i>
                <span class="nav-link-text">Reports</span>
                <span class="tooltip-text">Reports</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $currentPage === 'settings.php' ? 'active' : '' ?>" href="/employeeYA/views/admin/settings.php">
                <i class="fas fa-cogs"></i>
                <span class="nav-link-text">Settings</span>
                <span class="tooltip-text">Settings</span>
            </a>
        </li>
        <?php endif; ?>
    </ul>
    
    <div class="sidebar-divider"></div>
    
    <ul class="nav flex-column px-1">
        <li class="nav-item">
            <a class="nav-link <?= $currentPage === 'profile.php' ? 'active' : '' ?>" href="/employeeYA/views/profile.php">
                <i class="fas fa-user"></i>
                <span class="nav-link-text">Profile</span>
                <span class="tooltip-text">Profile</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#" onclick="confirmLogout(event)">
                <i class="fas fa-sign-out-alt"></i>
                <span class="nav-link-text">Logout</span>
                <span class="tooltip-text">Logout</span>
            </a>
        </li>
    </ul>
</div>

<script>
    // Store the path to auth directory
    const authPath = '<?php echo $pathToRoot; ?>auth/logout.php';

    document.addEventListener('DOMContentLoaded', function() {
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarCollapse = document.getElementById('sidebarCollapse');
        const mainContent = document.querySelector('.main-content');
        
        // Mobile toggle functionality
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('sidebar-mobile-show');
            document.body.classList.toggle('no-scroll');
        });
        
        // Desktop collapse/expand functionality
        if (sidebarCollapse) {
            sidebarCollapse.addEventListener('click', () => {
                sidebar.classList.toggle('sidebar-collapsed');
                
                // Update icon based on state
                const icon = sidebarCollapse.querySelector('i');
                if (sidebar.classList.contains('sidebar-collapsed')) {
                    icon.classList.remove('fa-chevron-left');
                    icon.classList.add('fa-chevron-right');
                } else {
                    icon.classList.remove('fa-chevron-right');
                    icon.classList.add('fa-chevron-left');
                }
                
                // Adjust main content margin if it exists
                if (mainContent) {
                    if (sidebar.classList.contains('sidebar-collapsed')) {
                        mainContent.style.marginLeft = '5rem';
                    } else {
                        mainContent.style.marginLeft = '16rem';
                    }
                }
                
                // Store preference in localStorage
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('sidebar-collapsed'));
            });
        }
        
        // Initialize sidebar state
        function initializeSidebar() {
            // Check for saved sidebar state
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            
            if (isCollapsed) {
                sidebar.classList.add('sidebar-collapsed');
                if (mainContent) mainContent.style.marginLeft = '5rem';
                
                // Update icon if collapsed
                if (sidebarCollapse) {
                    const icon = sidebarCollapse.querySelector('i');
                    icon.classList.remove('fa-chevron-left');
                    icon.classList.add('fa-chevron-right');
                }
            }
            
            // Close mobile sidebar when clicking a link
            const navLinks = document.querySelectorAll('.sidebar .nav-link');
            navLinks.forEach(link => {
                link.addEventListener('click', () => {
                    if (window.innerWidth < 992) {
                        sidebar.classList.remove('sidebar-mobile-show');
                        document.body.classList.remove('no-scroll');
                    }
                });
            });
        }
        
        initializeSidebar();
        
        // Close mobile sidebar when clicking outside
        document.addEventListener('click', (e) => {
            if (window.innerWidth < 992 && 
                !sidebar.contains(e.target) && 
                e.target !== sidebarToggle) {
                sidebar.classList.remove('sidebar-mobile-show');
                document.body.classList.remove('no-scroll');
            }
        });
        
        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 992) {
                sidebar.classList.remove('sidebar-mobile-show');
                document.body.classList.remove('no-scroll');
            }
        });
    });

    // Add logout confirmation function
    function confirmLogout(event) {
        event.preventDefault();
        Swal.fire({
            title: 'Logout Confirmation',
            text: 'Are you sure you want to logout?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#6366f1',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, logout',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = authPath;
            }
        });
    }
</script>