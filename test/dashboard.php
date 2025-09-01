<?php
// Start session and handle all PHP logic BEFORE any HTML output
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "Testbase";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Create necessary tables if they don't exist
$createTables = [
    "CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('super_admin', 'manager', 'staff') DEFAULT 'staff',
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS admin_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        action VARCHAR(255) NOT NULL,
        details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL,
        status ENUM('pending', 'confirmed', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
        shipping_address TEXT NOT NULL,
        payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
        payment_method VARCHAR(50) DEFAULT 'cod',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        size VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    /*"CREATE TABLE IF NOT EXISTS reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        user_id INT NOT NULL,
        rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
        comment TEXT,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        description TEXT,
        image VARCHAR(500),
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",*/
    "CREATE TABLE IF NOT EXISTS coupons (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) UNIQUE NOT NULL,
        discount_type ENUM('percentage', 'fixed') NOT NULL,
        discount_value DECIMAL(10,2) NOT NULL,
        min_order_amount DECIMAL(10,2) DEFAULT 0,
        max_uses INT DEFAULT NULL,
        used_count INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        expires_at DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS site_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )"
];

foreach ($createTables as $table) {
    try {
        $pdo->exec($table);
    } catch(PDOException $e) {
        error_log("Table creation error: " . $e->getMessage());
    }
}

// Handle logout BEFORE any HTML output
if (isset($_GET['logout'])) {
    // Log logout activity
    $logStmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, details) VALUES (?, ?, ?)");
    $logStmt->execute([$_SESSION['admin_id'], 'logout', 'Admin logged out']);
    
    session_destroy();
    header('Location: login.php?logout=1');
    exit();
}

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Get dashboard statistics
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalCustomers = $pdo->query("SELECT COUNT(*) FROM customer")->fetchColumn();
$totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$totalRevenue = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'paid'")->fetchColumn();
$lowStockProducts = $pdo->query("SELECT COUNT(*) FROM products WHERE id IN (SELECT product_id FROM product_sizes WHERE stock_quantity < 10)")->fetchColumn();

// Get recent activities
$recentActivities = $pdo->query("SELECT * FROM admin_logs ORDER BY created_at DESC LIMIT 10")->fetchAll();

// NOW start the HTML output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Clothing Store</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            line-height: 1.6;
        }

        .sidebar {
            width: 250px;
            height: 100vh;
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h2 {
            color: white;
            font-size: 18px;
            margin-bottom: 5px;
        }

        .sidebar-header p {
            color: rgba(255,255,255,0.8);
            font-size: 12px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
        }

        .sidebar-menu li {
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-menu a {
            display: block;
            padding: 15px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            color: white;
            padding-left: 30px;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }

        .top-bar {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .top-bar h1 {
            color: #2c3e50;
            font-size: 24px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info span {
            color: #666;
        }

        .logout-btn {
            background: #e74c3c;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }

        .logout-btn:hover {
            background: #c0392b;
        }

        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .card-title {
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .card-value {
            font-size: 32px;
            font-weight: bold;
            color: #3498db;
            margin-bottom: 5px;
        }

        .card-subtitle {
            color: #666;
            font-size: 14px;
        }

        .charts-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .chart-title {
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .recent-activities {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .activity-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-text {
            color: #666;
        }

        .activity-time {
            color: #999;
            font-size: 12px;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .action-btn {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 15px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            font-size: 14px;
            transition: transform 0.2s;
        }

        .action-btn:hover {
            transform: translateY(-2px);
        }

        .action-btn.danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }

        .action-btn.success {
            background: linear-gradient(135deg, #27ae60, #229954);
        }

        .action-btn.warning {
            background: linear-gradient(135deg, #f39c12, #e67e22);
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }
            
            .main-content {
                margin-left: 200px;
            }
            
            .charts-section {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>🛍️  ALLURA ESTELLA</h2>
            <p>Admin Panel</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php" class="active">📊 Dashboard</a></li>
            <li><a href="products.php">📦 Product Management</a></li>
            <li><a href="orders.php">🛒 Order Management</a></li>
            <li><a href="customers.php">👥 Customer Management</a></li>
            <li><a href="inventory.php">📊 Inventory</a></li>
            <li><a href="reviews.php">⭐ Reviews</a></li>
            <li><a href="coupons.php">🎫 Coupons</a></li>
            <li><a href="reports.php">📈 Reports</a></li>
            <li><a href="users.php">👤 Admin Users</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <h1>Dashboard</h1>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>!</span>
                <span class="badge"><?php echo ucfirst($_SESSION['admin_role']); ?></span>
                <a href="?logout=1" class="logout-btn">Logout</a>
            </div>
        </div>

        <div class="dashboard-cards">
            <div class="card">
                <div class="card-icon">📦</div>
                <div class="card-title">Total Products</div>
                <div class="card-value"><?php echo number_format($totalProducts); ?></div>
                <div class="card-subtitle">Active products in store</div>
            </div>

            <div class="card">
                <div class="card-icon">👥</div>
                <div class="card-title">Total Customers</div>
                <div class="card-value"><?php echo number_format($totalCustomers); ?></div>
                <div class="card-subtitle">Registered customers</div>
            </div>

            <div class="card">
                <div class="card-icon">🛒</div>
                <div class="card-title">Total Orders</div>
                <div class="card-value"><?php echo number_format($totalOrders); ?></div>
                <div class="card-subtitle">All time orders</div>
            </div>

            <div class="card">
                <div class="card-icon">⏳</div>
                <div class="card-title">Pending Orders</div>
                <div class="card-value"><?php echo number_format($pendingOrders); ?></div>
                <div class="card-subtitle">Need attention</div>
            </div>

            <div class="card">
                <div class="card-icon">💰</div>
                <div class="card-title">Total Revenue</div>
                <div class="card-value">Rs.<?php echo number_format($totalRevenue, 2); ?></div>
                <div class="card-subtitle">From paid orders</div>
            </div>

            <div class="card">
                <div class="card-icon">⚠️</div>
                <div class="card-title">Low Stock Items</div>
                <div class="card-value"><?php echo number_format($lowStockProducts); ?></div>
                <div class="card-subtitle">Need restocking</div>
            </div>
        </div>

        <div class="charts-section">
            <div class="chart-container">
                <h3 class="chart-title">Sales Overview</h3>
                <canvas id="salesChart" width="400" height="200"></canvas>
            </div>

            <div class="recent-activities">
                <h3 class="chart-title">Recent Activities</h3>
                <?php if (empty($recentActivities)): ?>
                    <p>No recent activities found.</p>
                <?php else: ?>
                    <?php foreach ($recentActivities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-text">
                                <?php echo htmlspecialchars($activity['action']); ?>
                                <?php if ($activity['details']): ?>
                                    <br><small><?php echo htmlspecialchars($activity['details']); ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="activity-time">
                                <?php echo date('M j, Y H:i', strtotime($activity['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="recent-activities">
            <h3 class="chart-title">Quick Actions</h3>
            <div class="quick-actions">
                <a href="products.php?action=add" class="action-btn success">➕ Add New Product</a>
                <a href="orders.php?status=pending" class="action-btn warning">⏳ View Pending Orders</a>
                <a href="customers.php" class="action-btn">👥 Manage Customers</a>
                <a href="inventory.php?low_stock=1" class="action-btn danger">⚠️ Low Stock Alert</a>
                <a href="reports.php" class="action-btn">📊 Generate Reports</a>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script>
        // Sales Chart
        const ctx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Sales',
                    data: [1200, 1900, 3000, 5000, 2000, 3000],
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Auto-refresh dashboard every 5 minutes
        setInterval(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>