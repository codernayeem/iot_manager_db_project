<!-- Sidebar -->
<div id="sidebar" class="sidebar fixed top-0 left-0 h-full bg-gray-900 shadow-2xl transition-all duration-300 ease-in-out z-50">
    <!-- Sidebar Header -->
    <div class="sidebar-header flex items-center justify-between p-4 border-b border-gray-700">
        <div class="sidebar-logo flex items-center space-x-3">
            <i class="fas fa-microchip text-2xl text-blue-400"></i>
            <span class="sidebar-text text-white text-lg font-bold">IoT Manager</span>
        </div>
        <button id="sidebar-toggle" class="sidebar-toggle-btn text-gray-300 hover:text-white hover:bg-gray-700 p-2 rounded-lg transition-all duration-200">
            <i class="fas fa-chevron-left text-sm sidebar-toggle-icon"></i>
        </button>
    </div>
    
    <!-- Navigation Menu -->
    <div class="sidebar-menu flex-1 py-4 overflow-y-auto">
        <nav class="space-y-1 px-3">
            <a href="index.php" class="sidebar-link flex items-center p-3 text-gray-300 hover:text-white hover:bg-gray-800 rounded-lg transition-all duration-200 group">
                <i class="fas fa-database text-lg w-6"></i>
                <span class="sidebar-text ml-3 font-medium">Database Setup</span>
                <div class="sidebar-tooltip">Database Setup</div>
            </a>
            
            <a href="dashboard.php" class="sidebar-link flex items-center p-3 text-gray-300 hover:text-white hover:bg-gray-800 rounded-lg transition-all duration-200 group">
                <i class="fas fa-tachometer-alt text-lg w-6"></i>
                <span class="sidebar-text ml-3 font-medium">Overview</span>
                <div class="sidebar-tooltip">Dashboard Overview</div>
            </a>            
            <a href="users.php" class="sidebar-link flex items-center p-3 text-gray-300 hover:text-white hover:bg-gray-800 rounded-lg transition-all duration-200 group">
                <i class="fas fa-users text-lg w-6"></i>
                <span class="sidebar-text ml-3 font-medium">All Users</span>
                <div class="sidebar-tooltip">User Management</div>
            </a>
            
            <a href="device_types.php" class="sidebar-link flex items-center p-3 text-gray-300 hover:text-white hover:bg-gray-800 rounded-lg transition-all duration-200 group">
                <i class="fas fa-tags text-lg w-6"></i>
                <span class="sidebar-text ml-3 font-medium">Device Types</span>
                <div class="sidebar-tooltip">Device Categories</div>
            </a>
            
            <a href="devices.php" class="sidebar-link flex items-center p-3 text-gray-300 hover:text-white hover:bg-gray-800 rounded-lg transition-all duration-200 group">
                <i class="fas fa-microchip text-lg w-6"></i>
                <span class="sidebar-text ml-3 font-medium">All Devices</span>
                <div class="sidebar-tooltip">Manage Devices</div>
            </a>
            
            <a href="locations.php" class="sidebar-link flex items-center p-3 text-gray-300 hover:text-white hover:bg-gray-800 rounded-lg transition-all duration-200 group">
                <i class="fas fa-map-marker-alt text-lg w-6"></i>
                <span class="sidebar-text ml-3 font-medium">All Locations</span>
                <div class="sidebar-tooltip">Manage Locations</div>
            </a>
            
            
            <a href="map.php" class="sidebar-link flex items-center p-3 text-gray-300 hover:text-white hover:bg-gray-800 rounded-lg transition-all duration-200 group">
                <i class="fas fa-map-marked-alt text-lg w-6"></i>
                <span class="sidebar-text ml-3 font-medium">Map View</span>
                <div class="sidebar-tooltip">Location Map</div>
            </a>
            
            <a href="device_logs.php" class="sidebar-link flex items-center p-3 text-gray-300 hover:text-white hover:bg-gray-800 rounded-lg transition-all duration-200 group">
                <i class="fas fa-list-alt text-lg w-6"></i>
                <span class="sidebar-text ml-3 font-medium">Device Logs</span>
                <div class="sidebar-tooltip">Activity Logs</div>
            </a>
            
            <a href="alerts.php" class="sidebar-link flex items-center p-3 text-gray-300 hover:text-white hover:bg-gray-800 rounded-lg transition-all duration-200 group">
                <i class="fas fa-bell text-lg w-6"></i>
                <span class="sidebar-text ml-3 font-medium">Alerts</span>
                <div class="sidebar-tooltip">System Alerts</div>
            </a>
        </nav>
    </div>
    
    <!-- Account Section -->
    <div class="sidebar-footer border-t border-gray-700 p-4">
        <!-- User Info -->
        <div class="flex items-center p-3 bg-gray-800 rounded-lg mb-3">
            <div class="flex items-center justify-center w-10 h-10 bg-blue-600 rounded-full">
                <i class="fas fa-user text-white"></i>
            </div>
            <div class="sidebar-text ml-3 flex-1">
                <div class="text-white text-sm font-medium">
                    <?php echo isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Guest'; ?>
                </div>
                <div class="text-gray-400 text-xs">
                    <?php echo isset($_SESSION['user_email']) ? $_SESSION['user_email'] : 'guest@example.com'; ?>
                </div>
            </div>
        </div>
        
        <!-- Logout Button -->
        <a href="logout.php" class="sidebar-link flex items-center p-3 text-gray-300 hover:text-white hover:bg-red-600 rounded-lg transition-all duration-200 group">
            <i class="fas fa-sign-out-alt text-lg w-6"></i>
            <span class="sidebar-text ml-3 font-medium">Logout</span>
            <div class="sidebar-tooltip">Logout</div>
        </a>
    </div>
</div>

<!-- Sidebar Overlay for Mobile -->
<div id="sidebar-overlay" class="sidebar-overlay fixed inset-0 bg-black bg-opacity-50 z-40 hidden"></div>

<!-- Mobile Header -->
<div class="mobile-header md:hidden fixed top-0 left-0 right-0 bg-gray-900 border-b border-gray-700 z-30 h-16">
    <div class="flex items-center justify-between h-full px-4">
        <button id="mobile-sidebar-toggle" class="text-gray-300 hover:text-white">
            <i class="fas fa-bars text-xl"></i>
        </button>
        <div class="flex items-center space-x-2">
            <i class="fas fa-microchip text-xl text-blue-400"></i>
            <span class="text-white font-bold">IoT Manager</span>
        </div>
        <div class="w-8"></div> <!-- Spacer for centering -->
    </div>
</div>

<!-- Main Content Wrapper -->
<div id="main-content" class="main-content transition-all duration-300 ease-in-out">
    <!-- Content will be placed here by the including page -->

<style>
/* Sidebar Styles */
.sidebar {
    width: 280px;
    display: flex;
    flex-direction: column;
}

.sidebar.collapsed {
    width: 72px;
}

.sidebar-text {
    transition: opacity 0.3s ease;
}

.sidebar.collapsed .sidebar-text {
    opacity: 0;
    pointer-events: none;
}

/* Toggle button styles */
.sidebar-toggle-btn {
    min-width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.sidebar-toggle-icon {
    transition: transform 0.3s ease;
}

.sidebar.collapsed .sidebar-toggle-icon {
    transform: rotate(180deg);
}

.sidebar.collapsed .sidebar-header {
    justify-content: center;
}

.sidebar.collapsed .sidebar-logo {
    display: none;
}

.sidebar.collapsed .sidebar-toggle-btn {
    margin: 0;
}

.sidebar-tooltip {
    position: absolute;
    left: 100%;
    top: 50%;
    transform: translateY(-50%);
    background: #1f2937;
    color: white;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 12px;
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    transition: all 0.2s ease;
    margin-left: 8px;
    z-index: 100;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.sidebar-tooltip::before {
    content: '';
    position: absolute;
    top: 50%;
    left: -4px;
    transform: translateY(-50%);
    border: 4px solid transparent;
    border-right-color: #1f2937;
}

.sidebar.collapsed .sidebar-link:hover .sidebar-tooltip {
    opacity: 1;
    visibility: visible;
}

.sidebar:not(.collapsed) .sidebar-tooltip {
    display: none;
}

/* Main content area adjustment */
.main-content {
    margin-left: 280px;
    min-height: 100vh;
}

.main-content.collapsed {
    margin-left: 72px;
}

/* Mobile adjustments */
@media (max-width: 768px) {
    .main-content,
    .main-content.collapsed {
        margin-left: 0;
        padding-top: 64px;
    }
    
    .sidebar {
        transform: translateX(-100%);
    }
    
    .sidebar.mobile-open {
        transform: translateX(0);
    }
    
    .sidebar-overlay.active {
        display: block !important;
    }
}

/* Active link styling */
.sidebar-link.active {
    background-color: #1d4ed8;
    color: white;
}

.sidebar-link.active:hover {
    background-color: #1e40af;
}

/* Scrollbar styling for sidebar */
.sidebar-menu::-webkit-scrollbar {
    width: 4px;
}

.sidebar-menu::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar-menu::-webkit-scrollbar-thumb {
    background: #4b5563;
    border-radius: 2px;
}

.sidebar-menu::-webkit-scrollbar-thumb:hover {
    background: #6b7280;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const mobileSidebarToggle = document.getElementById('mobile-sidebar-toggle');
    const sidebarOverlay = document.getElementById('sidebar-overlay');
    const mainContent = document.getElementById('main-content');
    
    // Check if sidebar should start collapsed (stored preference)
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    const isMobile = window.innerWidth < 768;
    
    // Initialize sidebar state
    if (!isMobile) {
        if (isCollapsed) {
            sidebar.classList.add('collapsed');
            if (mainContent) mainContent.classList.add('collapsed');
        }
    }
    
    // Desktop sidebar toggle
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            if (window.innerWidth >= 768) {
                sidebar.classList.toggle('collapsed');
                const collapsed = sidebar.classList.contains('collapsed');
                
                // Update main content
                if (mainContent) {
                    if (collapsed) {
                        mainContent.classList.add('collapsed');
                    } else {
                        mainContent.classList.remove('collapsed');
                    }
                }
                
                // Save preference
                localStorage.setItem('sidebarCollapsed', collapsed);
            }
        });
    }
    
    // Mobile sidebar toggle
    if (mobileSidebarToggle) {
        mobileSidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('mobile-open');
            sidebarOverlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('mobile-open') ? 'hidden' : '';
        });
    }
    
    // Close mobile sidebar when overlay is clicked
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('mobile-open');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        });
    }
    
    // Handle window resize
    window.addEventListener('resize', function() {
        const isMobileNow = window.innerWidth < 768;
        
        if (isMobileNow) {
            // Mobile view
            sidebar.classList.remove('mobile-open');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
            if (mainContent) {
                mainContent.classList.remove('collapsed');
            }
        } else {
            // Desktop view
            const collapsed = sidebar.classList.contains('collapsed');
            if (mainContent) {
                if (collapsed) {
                    mainContent.classList.add('collapsed');
                } else {
                    mainContent.classList.remove('collapsed');
                }
            }
        }
    });
    
    // Highlight active page
    const currentPage = window.location.pathname.split('/').pop();
    const sidebarLinks = document.querySelectorAll('.sidebar-link');
    
    sidebarLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href && href.includes(currentPage)) {
            link.classList.add('active');
        }
    });
    
    // Handle ESC key to close mobile sidebar
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && window.innerWidth < 768) {
            sidebar.classList.remove('mobile-open');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
});
</script>

<!-- Closing div for main-content wrapper (to be included at the end of each page) -->
<!-- Add this to the end of your page content: </div> -->