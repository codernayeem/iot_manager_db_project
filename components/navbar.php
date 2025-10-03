<nav class="bg-gray-800 shadow-lg">
    <div class="container mx-auto px-4">
        <div class="flex justify-between items-center h-16">
            <!-- Logo and Brand -->
            <div class="flex items-center space-x-4">
                <i class="fas fa-microchip text-2xl text-blue-400"></i>
                <span class="text-white text-xl font-bold">IoT Manager</span>
            </div>
            
            <!-- Navigation Links -->
            <div class="hidden md:flex space-x-6">
                <a href="dashboard.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium flex items-center">
                    <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                </a>
                <a href="devices.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium flex items-center">
                    <i class="fas fa-microchip mr-2"></i>Devices
                </a>
                <a href="device_logs.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium flex items-center">
                    <i class="fas fa-list-alt mr-2"></i>Logs
                </a>
                <a href="locations.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium flex items-center">
                    <i class="fas fa-map-marker-alt mr-2"></i>Locations
                </a>
                <a href="analytics.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium flex items-center">
                    <i class="fas fa-chart-bar mr-2"></i>Analytics
                </a>
                <a href="sql_features.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium flex items-center">
                    <i class="fas fa-database mr-2"></i>SQL Features
                </a>
                <a href="advanced_sql.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium flex items-center">
                    <i class="fas fa-cogs mr-2"></i>Advanced SQL
                </a>
            </div>
            
            <!-- User Menu -->
            <div class="flex items-center space-x-4">
                <span class="text-gray-300 text-sm">
                    <i class="fas fa-user mr-1"></i>
                    <?php echo isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Guest'; ?>
                </span>
                <a href="logout.php" class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-md text-sm font-medium">
                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                </a>
            </div>
            
            <!-- Mobile menu button -->
            <div class="md:hidden">
                <button type="button" class="text-gray-300 hover:text-white focus:outline-none focus:text-white" id="mobile-menu-button">
                    <i class="fas fa-bars text-xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Mobile menu -->
        <div class="md:hidden hidden" id="mobile-menu">
            <div class="px-2 pt-2 pb-3 space-y-1">
                <a href="dashboard.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                </a>
                <a href="devices.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-microchip mr-2"></i>Devices
                </a>
                <a href="device_logs.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-list-alt mr-2"></i>Logs
                </a>
                <a href="locations.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-map-marker-alt mr-2"></i>Locations
                </a>
                <a href="analytics.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-chart-bar mr-2"></i>Analytics
                </a>
                <a href="sql_features.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-database mr-2"></i>SQL Features
                </a>
                <a href="advanced_sql.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-cogs mr-2"></i>Advanced SQL
                </a>
            </div>
        </div>
    </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    
    mobileMenuButton.addEventListener('click', function() {
        mobileMenu.classList.toggle('hidden');
    });
});
</script>