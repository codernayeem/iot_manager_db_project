<?php
require_once 'config/sql_features.php';
$tracker = new SQLFeatureTracker();
$allFeatures = $tracker->getAllFeatures();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SQL Features Overview - IoT Device Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .feature-card {
            transition: all 0.3s ease;
            border-left: 4px solid #3b82f6;
        }
        
        .feature-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-left-color: #1d4ed8;
        }
        
        .category-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .complexity-basic { border-left-color: #10b981; }
        .complexity-intermediate { border-left-color: #f59e0b; }
        .complexity-advanced { border-left-color: #ef4444; }
        
        .fade-in {
            animation: fadeIn 0.6s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .search-highlight {
            background-color: #fef3c7;
            padding: 2px 4px;
            border-radius: 3px;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="container mx-auto px-4 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">
                        <i class="fas fa-database text-blue-600 mr-3"></i>
                        SQL Features Comprehensive Overview
                    </h1>
                    <p class="text-gray-600">Complete demonstration of MySQL/SQL features in IoT Device Manager</p>
                </div>
                <a href="dashboard.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </div>
    
    <!-- Statistics Section -->
    <div class="container mx-auto px-4 py-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6 text-center">
                <i class="fas fa-list-ul text-blue-600 text-3xl mb-3"></i>
                <h3 class="text-2xl font-bold text-gray-800"><?php echo count($allFeatures); ?></h3>
                <p class="text-gray-600">Total SQL Features</p>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6 text-center">
                <i class="fas fa-seedling text-green-600 text-3xl mb-3"></i>
                <h3 class="text-2xl font-bold text-gray-800"><?php echo count(array_filter($allFeatures, function($f) { return $f['complexity'] === 'Basic'; })); ?></h3>
                <p class="text-gray-600">Basic Features</p>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6 text-center">
                <i class="fas fa-chart-line text-orange-600 text-3xl mb-3"></i>
                <h3 class="text-2xl font-bold text-gray-800"><?php echo count(array_filter($allFeatures, function($f) { return $f['complexity'] === 'Intermediate'; })); ?></h3>
                <p class="text-gray-600">Intermediate Features</p>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6 text-center">
                <i class="fas fa-rocket text-red-600 text-3xl mb-3"></i>
                <h3 class="text-2xl font-bold text-gray-800"><?php echo count(array_filter($allFeatures, function($f) { return $f['complexity'] === 'Advanced'; })); ?></h3>
                <p class="text-gray-600">Advanced Features</p>
            </div>
        </div>
        
        <!-- Search and Filter -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search Features</label>
                    <input type="text" id="search" placeholder="Search SQL features, categories, or descriptions..."
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="md:w-48">
                    <label for="complexity" class="block text-sm font-medium text-gray-700 mb-2">Filter by Complexity</label>
                    <select id="complexity" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">All Complexities</option>
                        <option value="Basic">Basic</option>
                        <option value="Intermediate">Intermediate</option>
                        <option value="Advanced">Advanced</option>
                    </select>
                </div>
                
                <div class="md:w-48">
                    <label for="category" class="block text-sm font-medium text-gray-700 mb-2">Filter by Category</label>
                    <select id="category" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">All Categories</option>
                        <?php
                        $categories = array_unique(array_column($allFeatures, 'category'));
                        sort($categories);
                        foreach ($categories as $category) {
                            echo "<option value='" . htmlspecialchars($category) . "'>" . htmlspecialchars($category) . "</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Feature Categories -->
        <?php
        $categorizedFeatures = [];
        foreach ($allFeatures as $feature) {
            $categorizedFeatures[$feature['category']][] = $feature;
        }
        ksort($categorizedFeatures);
        ?>
        
        <?php foreach ($categorizedFeatures as $categoryName => $features): ?>
            <div class="category-section mb-8" data-category="<?php echo htmlspecialchars($categoryName); ?>">
                <div class="category-header text-white p-6 rounded-t-lg">
                    <h2 class="text-2xl font-bold flex items-center">
                        <i class="fas fa-folder-open mr-3"></i>
                        <?php echo htmlspecialchars($categoryName); ?>
                        <span class="ml-auto bg-white bg-opacity-20 px-3 py-1 rounded-full text-sm">
                            <?php echo count($features); ?> features
                        </span>
                    </h2>
                </div>
                
                <div class="bg-white rounded-b-lg shadow-md">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 p-6">
                        <?php foreach ($features as $feature): ?>
                            <div class="feature-card bg-white border rounded-lg p-4 complexity-<?php echo strtolower($feature['complexity']); ?>"
                                 data-feature-name="<?php echo htmlspecialchars($feature['name']); ?>"
                                 data-complexity="<?php echo htmlspecialchars($feature['complexity']); ?>"
                                 data-description="<?php echo htmlspecialchars($feature['description']); ?>">
                                
                                <!-- Feature Header -->
                                <div class="flex justify-between items-start mb-3">
                                    <h3 class="font-bold text-gray-800 text-lg">
                                        <?php echo htmlspecialchars($feature['name']); ?>
                                    </h3>
                                    
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full
                                        <?php echo $feature['complexity'] === 'Basic' ? 'bg-green-100 text-green-800' : 
                                                   ($feature['complexity'] === 'Intermediate' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                        <?php echo $feature['complexity']; ?>
                                    </span>
                                </div>
                                
                                <!-- Feature Description -->
                                <p class="text-gray-600 text-sm mb-4 leading-relaxed">
                                    <?php echo htmlspecialchars($feature['description']); ?>
                                </p>
                                
                                <!-- Implementation Files -->
                                <div class="mb-4">
                                    <h4 class="text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                        <i class="fas fa-file-code mr-2 text-blue-500"></i>
                                        Implemented in:
                                    </h4>
                                    <div class="flex flex-wrap gap-1">
                                        <?php foreach ($feature['files'] as $file): ?>
                                            <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">
                                                <?php echo htmlspecialchars($file); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <!-- Example Usage -->
                                <?php if (!empty($feature['example'])): ?>
                                    <div class="mb-4">
                                        <h4 class="text-sm font-semibold text-gray-700 mb-2 flex items-center">
                                            <i class="fas fa-code mr-2 text-green-500"></i>
                                            Example:
                                        </h4>
                                        <div class="bg-gray-900 text-green-400 p-3 rounded text-xs font-mono overflow-x-auto">
                                            <?php echo htmlspecialchars($feature['example']); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Educational Value -->
                                <div class="border-t pt-3">
                                    <h4 class="text-sm font-semibold text-gray-700 mb-1 flex items-center">
                                        <i class="fas fa-graduation-cap mr-2 text-purple-500"></i>
                                        Educational Value:
                                    </h4>
                                    <p class="text-xs text-gray-600">
                                        Demonstrates <?php echo strtolower($feature['complexity']); ?>-level SQL concepts 
                                        essential for database management and query optimization.
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <!-- Project Navigation -->
        <div class="bg-white rounded-lg shadow-md p-6 mt-8">
            <h3 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-compass mr-2 text-blue-600"></i>
                Explore Project Features
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="dashboard.php" class="bg-blue-50 hover:bg-blue-100 p-4 rounded-lg transition group">
                    <i class="fas fa-tachometer-alt text-blue-600 text-2xl mb-2 group-hover:scale-110 transition-transform"></i>
                    <h4 class="font-semibold text-gray-800">Dashboard</h4>
                    <p class="text-sm text-gray-600">Complex analytics & CTEs</p>
                </a>
                
                <a href="devices.php" class="bg-green-50 hover:bg-green-100 p-4 rounded-lg transition group">
                    <i class="fas fa-microchip text-green-600 text-2xl mb-2 group-hover:scale-110 transition-transform"></i>
                    <h4 class="font-semibold text-gray-800">Devices</h4>
                    <p class="text-sm text-gray-600">Advanced filtering & JOINs</p>
                </a>
                
                <a href="analytics.php" class="bg-purple-50 hover:bg-purple-100 p-4 rounded-lg transition group">
                    <i class="fas fa-chart-line text-purple-600 text-2xl mb-2 group-hover:scale-110 transition-transform"></i>
                    <h4 class="font-semibold text-gray-800">Analytics</h4>
                    <p class="text-sm text-gray-600">Window functions & trends</p>
                </a>
                
                <a href="device_logs.php" class="bg-orange-50 hover:bg-orange-100 p-4 rounded-lg transition group">
                    <i class="fas fa-list-alt text-orange-600 text-2xl mb-2 group-hover:scale-110 transition-transform"></i>
                    <h4 class="font-semibold text-gray-800">Logs</h4>
                    <p class="text-sm text-gray-600">Full-text search & indexing</p>
                </a>
                
                <a href="locations.php" class="bg-indigo-50 hover:bg-indigo-100 p-4 rounded-lg transition group">
                    <i class="fas fa-map-marker-alt text-indigo-600 text-2xl mb-2 group-hover:scale-110 transition-transform"></i>
                    <h4 class="font-semibold text-gray-800">Locations</h4>
                    <p class="text-sm text-gray-600">Geospatial queries</p>
                </a>
                
                <a href="advanced_sql.php" class="bg-red-50 hover:bg-red-100 p-4 rounded-lg transition group">
                    <i class="fas fa-cogs text-red-600 text-2xl mb-2 group-hover:scale-110 transition-transform"></i>
                    <h4 class="font-semibold text-gray-800">Advanced SQL</h4>
                    <p class="text-sm text-gray-600">Procedures & triggers</p>
                </a>
                
                <a href="add_device.php" class="bg-teal-50 hover:bg-teal-100 p-4 rounded-lg transition group">
                    <i class="fas fa-plus text-teal-600 text-2xl mb-2 group-hover:scale-110 transition-transform"></i>
                    <h4 class="font-semibold text-gray-800">Add Device</h4>
                    <p class="text-sm text-gray-600">Transactions & validation</p>
                </a>
                
                <a href="sql_features.php" class="bg-gray-50 hover:bg-gray-100 p-4 rounded-lg transition group">
                    <i class="fas fa-database text-gray-600 text-2xl mb-2 group-hover:scale-110 transition-transform"></i>
                    <h4 class="font-semibold text-gray-800">SQL Features</h4>
                    <p class="text-sm text-gray-600">Interactive feature explorer</p>
                </a>
            </div>
        </div>
        
        <!-- Summary Statistics -->
        <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg p-8 mt-8">
            <div class="text-center">
                <h3 class="text-2xl font-bold mb-4">
                    🎓 Educational Database Project Achievement
                </h3>
                <p class="text-lg mb-6">
                    This IoT Device Manager successfully demonstrates comprehensive MySQL/SQL knowledge
                </p>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <i class="fas fa-award text-3xl mb-2"></i>
                        <h4 class="font-bold">Complete Coverage</h4>
                        <p class="text-sm">Every major SQL feature category implemented</p>
                    </div>
                    
                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <i class="fas fa-lightbulb text-3xl mb-2"></i>
                        <h4 class="font-bold">Educational Focus</h4>
                        <p class="text-sm">Tooltips and explanations for learning</p>
                    </div>
                    
                    <div class="bg-white bg-opacity-20 rounded-lg p-4">
                        <i class="fas fa-rocket text-3xl mb-2"></i>
                        <h4 class="font-bold">Production Ready</h4>
                        <p class="text-sm">Real-world applicable code patterns</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Search and filter functionality
        const searchInput = document.getElementById('search');
        const complexityFilter = document.getElementById('complexity');
        const categoryFilter = document.getElementById('category');
        
        function filterFeatures() {
            const searchTerm = searchInput.value.toLowerCase();
            const selectedComplexity = complexityFilter.value;
            const selectedCategory = categoryFilter.value;
            
            const featureCards = document.querySelectorAll('.feature-card');
            const categorySection = document.querySelectorAll('.category-section');
            
            featureCards.forEach(card => {
                const featureName = card.dataset.featureName.toLowerCase();
                const complexity = card.dataset.complexity;
                const description = card.dataset.description.toLowerCase();
                const category = card.closest('.category-section').dataset.category;
                
                const matchesSearch = !searchTerm || 
                    featureName.includes(searchTerm) || 
                    description.includes(searchTerm) ||
                    category.toLowerCase().includes(searchTerm);
                
                const matchesComplexity = !selectedComplexity || complexity === selectedComplexity;
                const matchesCategory = !selectedCategory || category === selectedCategory;
                
                if (matchesSearch && matchesComplexity && matchesCategory) {
                    card.style.display = 'block';
                    card.classList.add('fade-in');
                    
                    // Highlight search terms
                    if (searchTerm) {
                        highlightSearchTerm(card, searchTerm);
                    } else {
                        removeHighlights(card);
                    }
                } else {
                    card.style.display = 'none';
                    card.classList.remove('fade-in');
                }
            });
            
            // Hide empty categories
            categorySection.forEach(section => {
                const visibleCards = section.querySelectorAll('.feature-card[style="display: block"]');
                if (visibleCards.length === 0) {
                    section.style.display = 'none';
                } else {
                    section.style.display = 'block';
                }
            });
        }
        
        function highlightSearchTerm(card, term) {
            const textNodes = getTextNodes(card);
            textNodes.forEach(node => {
                if (node.textContent.toLowerCase().includes(term)) {
                    const regex = new RegExp(`(${term})`, 'gi');
                    const highlighted = node.textContent.replace(regex, '<span class="search-highlight">$1</span>');
                    const wrapper = document.createElement('span');
                    wrapper.innerHTML = highlighted;
                    node.parentNode.replaceChild(wrapper, node);
                }
            });
        }
        
        function removeHighlights(card) {
            const highlights = card.querySelectorAll('.search-highlight');
            highlights.forEach(highlight => {
                const parent = highlight.parentNode;
                parent.replaceChild(document.createTextNode(highlight.textContent), highlight);
                parent.normalize();
            });
        }
        
        function getTextNodes(element) {
            const textNodes = [];
            const walker = document.createTreeWalker(
                element,
                NodeFilter.SHOW_TEXT,
                null,
                false
            );
            
            let node;
            while (node = walker.nextNode()) {
                if (node.textContent.trim() && !node.parentElement.closest('.bg-gray-900')) {
                    textNodes.push(node);
                }
            }
            return textNodes;
        }
        
        // Attach event listeners
        searchInput.addEventListener('input', filterFeatures);
        complexityFilter.addEventListener('change', filterFeatures);
        categoryFilter.addEventListener('change', filterFeatures);
        
        // Add smooth scrolling for better UX
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading animation to cards
            const cards = document.querySelectorAll('.feature-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.classList.add('fade-in');
                }, index * 50);
            });
        });
    </script>
</body>
</html>