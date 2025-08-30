<?php
// Database connection
$host = 'localhost';
$dbname = 'testbase';
$username = 'root'; // Change as needed
$password = ''; // Change as needed

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Create inventory_log table if it doesn't exist
$createLogTable = "
CREATE TABLE IF NOT EXISTS `inventory_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `size_id` int(11) DEFAULT NULL,
  `quantity_change` int(11) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `admin_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`size_id`) REFERENCES `product_sizes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

try {
    $pdo->exec($createLogTable);
} catch(PDOException $e) {
    // Table might already exist, continue
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'update_stock':
            $sizeId = $_POST['size_id'];
            $quantityChange = (int)$_POST['quantity_change'];
            $reason = $_POST['reason'];
            $adminName = $_POST['admin_name'];
            $productId = $_POST['product_id'];
            
            try {
                $pdo->beginTransaction();
                
                // Get current stock
                $stmt = $pdo->prepare("SELECT stock_quantity FROM product_sizes WHERE id = ?");
                $stmt->execute([$sizeId]);
                $currentStock = $stmt->fetchColumn();
                
                $newStock = $currentStock + $quantityChange;
                if ($newStock < 0) {
                    throw new Exception("Stock cannot be negative");
                }
                
                // Update stock
                $stmt = $pdo->prepare("UPDATE product_sizes SET stock_quantity = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$newStock, $sizeId]);
                
                // Log the change
                $stmt = $pdo->prepare("INSERT INTO inventory_log (product_id, size_id, quantity_change, reason, admin_name) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$productId, $sizeId, $quantityChange, $reason, $adminName]);
                
                $pdo->commit();
                echo json_encode(['success' => true, 'new_stock' => $newStock]);
            } catch (Exception $e) {
                $pdo->rollback();
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        case 'get_history':
            $productId = $_POST['product_id'] ?? null;
            $query = "
                SELECT il.*, p.name as product_name, ps.size 
                FROM inventory_log il
                JOIN products p ON il.product_id = p.id
                LEFT JOIN product_sizes ps ON il.size_id = ps.id
            ";
            $params = [];
            
            if ($productId) {
                $query .= " WHERE il.product_id = ?";
                $params[] = $productId;
            }
            
            $query .= " ORDER BY il.created_at DESC LIMIT 50";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'history' => $history]);
            exit;
    }
}

// Get products with stock information
function getProductsWithStock($pdo, $search = '', $mainCategory = '', $subCategory = '', $stockStatus = '') {
    $query = "
        SELECT p.*, 
               GROUP_CONCAT(
                   CONCAT(ps.size, ':', ps.stock_quantity, ':', ps.id) 
                   ORDER BY ps.size SEPARATOR '|'
               ) as sizes_stock,
               SUM(ps.stock_quantity) as total_stock
        FROM products p
        LEFT JOIN product_sizes ps ON p.id = ps.product_id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($search) {
        $query .= " AND (p.name LIKE ? OR p.id LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($mainCategory) {
        $query .= " AND p.category = ?";
        $params[] = $mainCategory;
    }
    
    if ($subCategory) {
        $query .= " AND p.subcategory = ?";
        $params[] = $subCategory;
    }
    
    $query .= " GROUP BY p.id";
    
    if ($stockStatus) {
        $having = [];
        switch ($stockStatus) {
            case 'in_stock':
                $having[] = "total_stock > 10";
                break;
            case 'low_stock':
                $having[] = "total_stock BETWEEN 1 AND 10";
                break;
            case 'out_of_stock':
                $having[] = "total_stock = 0 OR total_stock IS NULL";
                break;
        }
        if (!empty($having)) {
            $query .= " HAVING " . implode(' AND ', $having);
        }
    }
    
    $query .= " ORDER BY p.name";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Hardcoded categories
$categoriesData = [
    'dresses' => [
        'mini-dresses' => 'Mini Dresses',
        'midi-dresses' => 'Midi Dresses',
        'maxi-dresses' => 'Maxi Dresses',
        'casual-dresses' => 'Casual Dresses',
        'party-dresses' => 'Party Dresses',
        'formal-dresses' => 'Formal Dresses',
        'summer-dresses' => 'Summer Dresses'
    ],
    'tops' => [
        'blouses' => 'Blouses',
        'shirts' => 'Shirts',
        'crop-tops' => 'Crop Tops',
        'tank-tops' => 'Tank Tops',
        'sweaters' => 'Sweaters',
        'cardigans' => 'Cardigans'
    ],
    'bottoms' => [
        'jeans' => 'Jeans',
        'pants' => 'Pants',
        'skirts' => 'Skirts',
        'shorts' => 'Shorts',
        'leggings' => 'Leggings'
    ],
    'rompers-jumpsuits' => [
        'rompers' => 'Rompers',
        'jumpsuits' => 'Jumpsuits',
        'playsuits' => 'Playsuits'
    ],
    'office-work' => [
        'blazers' => 'Blazers',
        'office-dresses' => 'Office Dresses',
        'work-pants' => 'Work Pants',
        'office-blouses' => 'Office Blouses'
    ]
];

// Get main categories only
$mainCategories = array_keys($categoriesData);

// Get filtered products
$search = $_GET['search'] ?? '';
$mainCategoryFilter = $_GET['main_category'] ?? '';
$subCategoryFilter = $_GET['subcategory'] ?? '';
$stockStatusFilter = $_GET['stock_status'] ?? '';
$products = getProductsWithStock($pdo, $search, $mainCategoryFilter, $subCategoryFilter, $stockStatusFilter);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fashion Inventory Management System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
            line-height: 1.6;
            font-size: 20px;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 30px;
        }

        /* Formal Header */
        .formal-header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            color: white;
            padding: 40px 0;
            margin: -30px -30px 40px -30px;
            position: relative;
            overflow: hidden;
        }

        .formal-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 20"><defs><linearGradient id="a" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="%23ffffff" stop-opacity="0.1"/><stop offset="1" stop-color="%23ffffff" stop-opacity="0"/></linearGradient></defs><rect width="11" height="20" fill="url(%23a)" rx="5"/><rect x="22" width="11" height="20" fill="url(%23a)" rx="5"/><rect x="44" width="11" height="20" fill="url(%23a)" rx="5"/><rect x="66" width="11" height="20" fill="url(%23a)" rx="5"/><rect x="88" width="11" height="20" fill="url(%23a)" rx="5"/></svg>') repeat;
            opacity: 0.1;
        }

        .formal-header-content {
            max-width: 1600px;
            margin: 0 auto;
            padding: 0 30px;
            position: relative;
            z-index: 1;
            text-align: center;
        }

        .company-logo {
            display: inline-block;
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4);
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            margin: 0 auto 20px;
        }

        .company-name {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 10px;
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4, #45b7d1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .company-subtitle {
            font-size: 24px;
            font-weight: 300;
            opacity: 0.9;
            margin-bottom: 15px;
        }

        .system-title {
            font-size: 32px;
            font-weight: 600;
            color: #45b7d1;
            margin-bottom: 10px;
        }

        .current-date-time {
            font-size: 20px;
            opacity: 0.8;
            font-weight: 300;
        }

        /* Main Content Styling */
        .content-wrapper {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }

        .filters {
            background: linear-gradient(135deg, #f8f9ff 0%, #ffffff 100%);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            margin-bottom: 40px;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .filters-header {
            font-size: 28px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 25px;
            text-align: center;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 10px;
            color: #555;
            font-size: 20px;
        }

        .form-group input, .form-group select {
            padding: 16px 20px;
            border: 2px solid #e1e8ed;
            border-radius: 12px;
            font-size: 20px;
            transition: all 0.3s ease;
            background: white;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            transform: translateY(-2px);
        }

        .btn {
            padding: 16px 30px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 20px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }

        .btn-info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
        }

        .btn-sm {
            padding: 10px 20px;
            font-size: 18px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            text-align: center;
            border: 1px solid rgba(255,255,255,0.2);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .stat-label {
            color: #666;
            font-size: 22px;
            font-weight: 500;
        }

        .stat-in-stock .stat-number { color: #27ae60; }
        .stat-low-stock .stat-number { color: #f39c12; }
        .stat-out-of-stock .stat-number { color: #e74c3c; }
        .stat-total .stat-number { color: #3498db; }

        .inventory-table {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .table-header {
            padding: 30px;
            border-bottom: 2px solid #f1f3f4;
            background: linear-gradient(135deg, #f8f9ff 0%, #ffffff 100%);
        }

        .table-header h2 {
            font-size: 32px;
            font-weight: 600;
            color: #2c3e50;
            text-align: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 20px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 20px;
        }

        th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-weight: 600;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 18px;
        }

        .product-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 22px;
        }

        .stock-status {
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 18px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-in-stock {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }

        .status-low-stock {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #856404;
        }

        .status-out-of-stock {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
        }

        .size-stock {
            display: inline-block;
            margin: 4px 6px;
            padding: 6px 14px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 8px;
            font-size: 18px;
            font-weight: 500;
            border: 1px solid #dee2e6;
        }

        .actions {
            white-space: nowrap;
        }

        .actions button {
            margin: 0 4px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.6);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            margin: 3% auto;
            padding: 0;
            border-radius: 20px;
            width: 90%;
            max-width: 800px;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .modal-header {
            padding: 30px;
            border-bottom: 2px solid #f1f3f4;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #f8f9ff 0%, #ffffff 100%);
            border-radius: 20px 20px 0 0;
        }

        .modal-header h3 {
            margin: 0;
            color: #2c3e50;
            font-size: 28px;
            font-weight: 600;
        }

        .close {
            color: #aaa;
            font-size: 36px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
            transition: color 0.3s ease;
        }

        .close:hover {
            color: #333;
        }

        .modal-body {
            padding: 30px;
        }

        .modal-body h4 {
            font-size: 24px;
            margin-bottom: 20px;
            color: #2c3e50;
        }

        .size-update-grid {
            display: grid;
            gap: 20px;
        }

        .size-update-item {
            display: grid;
            grid-template-columns: auto 1fr auto auto auto;
            gap: 15px;
            align-items: center;
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            border: 1px solid #dee2e6;
        }

        .size-label {
            font-weight: 600;
            min-width: 60px;
            font-size: 22px;
        }

        .current-stock {
            color: #666;
            font-size: 20px;
            font-weight: 500;
        }

        .quantity-input {
            width: 100px;
            padding: 12px 16px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 20px;
            text-align: center;
        }

        .reason-input, .admin-input {
            padding: 16px 20px;
            border: 2px solid #ddd;
            border-radius: 12px;
            margin: 15px 0;
            width: 100%;
            font-size: 20px;
        }

        .history-table {
            margin-top: 30px;
        }

        .history-table table {
            font-size: 18px;
        }

        .history-table th, .history-table td {
            padding: 15px;
        }

        .quantity-positive {
            color: #27ae60;
            font-weight: 600;
        }

        .quantity-negative {
            color: #e74c3c;
            font-weight: 600;
        }

        .loading {
            text-align: center;
            padding: 30px;
            color: #666;
            font-size: 22px;
        }

        .alert {
            padding: 20px 30px;
            margin: 20px 0;
            border-radius: 12px;
            font-weight: 500;
            font-size: 20px;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 2px solid #c3e6cb;
        }

        .alert-error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border: 2px solid #f5c6cb;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .container {
                padding: 20px;
            }
            
            .formal-header {
                margin: -20px -20px 30px -20px;
                padding: 30px 0;
            }
            
            .company-name {
                font-size: 36px;
            }
            
            .system-title {
                font-size: 26px;
            }
            
            .content-wrapper {
                padding: 25px;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            body {
                font-size: 18px;
            }
            
            .formal-header {
                padding: 20px 0;
            }
            
            .company-name {
                font-size: 28px;
            }
            
            .company-subtitle {
                font-size: 18px;
            }
            
            .system-title {
                font-size: 22px;
            }
            
            .stats {
                grid-template-columns: 1fr;
            }
            
            .stat-number {
                font-size: 36px;
            }
            
            table {
                font-size: 16px;
            }
            
            th, td {
                padding: 12px 8px;
            }
            
            .modal-content {
                width: 95%;
                margin: 5% auto;
            }
            
            .size-update-item {
                grid-template-columns: 1fr;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <!-- Formal Header -->
    <div class="formal-header">
        <div class="formal-header-content">
            <div class="company-logo">👗</div>
            <h1 class="company-name">ELEGANCE FASHION</h1>
            <p class="company-subtitle">Premium Women's Clothing & Accessories</p>
            <h2 class="system-title">INVENTORY MANAGEMENT SYSTEM</h2>
            <p class="current-date-time" id="currentDateTime"></p>
        </div>
    </div>

    <div class="container">
        <div class="content-wrapper">
            <!-- Filters -->
            <div class="filters">
                <h3 class="filters-header">🔍 Search & Filter Products</h3>
                <form method="GET" class="filters-grid">
                    <div class="form-group">
                        <label for="search">🔍 Search Products</label>
                        <input type="text" id="search" name="search" placeholder="Search by name or ID..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="main_category">📂 Main Category</label>
                        <select id="main_category" name="main_category" onchange="updateSubcategories()">
                            <option value="">All Main Categories</option>
                            <?php foreach ($mainCategories as $mainCat): ?>
                                <option value="<?php echo htmlspecialchars($mainCat); ?>" <?php echo $mainCategoryFilter === $mainCat ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(ucwords(str_replace('-', ' ', $mainCat))); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="subcategory">📁 Subcategory</label>
                        <select id="subcategory" name="subcategory">
                            <option value="">All Subcategories</option>
                            <?php 
                            if ($mainCategoryFilter && isset($categoriesData[$mainCategoryFilter])) {
                                foreach ($categoriesData[$mainCategoryFilter] as $subCatKey => $subCatName) {
                                    $selected = $subCategoryFilter === $subCatKey ? 'selected' : '';
                                    echo "<option value='".htmlspecialchars($subCatKey)."' $selected>".htmlspecialchars($subCatName)."</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="stock_status">📊 Stock Status</label>
                        <select id="stock_status" name="stock_status">
                            <option value="">All Status</option>
                            <option value="in_stock" <?php echo $stockStatusFilter === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                            <option value="low_stock" <?php echo $stockStatusFilter === 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
                            <option value="out_of_stock" <?php echo $stockStatusFilter === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">🔍 Filter Results</button>
                    </div>
                </form>
            </div>

            <!-- Statistics -->
            <?php
            $totalProducts = count($products);
            $inStock = 0;
            $lowStock = 0;
            $outOfStock = 0;

            foreach ($products as $product) {
                $totalStock = (int)$product['total_stock'];
                if ($totalStock > 10) {
                    $inStock++;
                } elseif ($totalStock > 0) {
                    $lowStock++;
                } else {
                    $outOfStock++;
                }
            }
            ?>

            <div class="stats">
                <div class="stat-card stat-total">
                    <div class="stat-number"><?php echo $totalProducts; ?></div>
                    <div class="stat-label">📦 Total Products</div>
                </div>
                <div class="stat-card stat-in-stock">
                    <div class="stat-number"><?php echo $inStock; ?></div>
                    <div class="stat-label">✅ In Stock</div>
                </div>
                <div class="stat-card stat-low-stock">
                    <div class="stat-number"><?php echo $lowStock; ?></div>
                    <div class="stat-label">⚠️ Low Stock</div>
                </div>
                <div class="stat-card stat-out-of-stock">
                    <div class="stat-number"><?php echo $outOfStock; ?></div>
                    <div class="stat-label">❌ Out of Stock</div>
                </div>
            </div>

            <!-- Inventory Table -->
            <div class="inventory-table">
                <div class="table-header">
                    <h2>📋 Stock Management Dashboard</h2>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Sizes & Stock</th>
                            <th>Total Stock</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <?php
                            $totalStock = (int)$product['total_stock'];
                            $statusClass = 'status-out-of-stock';
                            $statusText = 'Out of Stock';
                            
                            if ($totalStock > 10) {
                                $statusClass = 'status-in-stock';
                                $statusText = 'In Stock';
                            } elseif ($totalStock > 0) {
                                $statusClass = 'status-low-stock';
                                $statusText = 'Low Stock';
                            }
                            ?>
                            <tr>
                                <td><strong>#<?php echo htmlspecialchars($product['id']); ?></strong></td>
                                <td class="product-name"><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo htmlspecialchars($product['category'] . ($product['subcategory'] ? ' > ' . $product['subcategory'] : '')); ?></td>
                                <td>
                                    <?php if ($product['sizes_stock']): ?>
                                        <?php foreach (explode('|', $product['sizes_stock']) as $sizeStock): ?>
                                            <?php
                                            $parts = explode(':', $sizeStock);
                                            if (count($parts) >= 2) {
                                                $size = $parts[0];
                                                $stock = $parts[1];
                                                echo "<span class='size-stock'>$size: $stock</span>";
                                            }
                                            ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="size-stock">No sizes</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo $totalStock; ?> units</strong></td>
                                <td><span class="stock-status <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                                <td class="actions">
                                    <button class="btn btn-success btn-sm" onclick="openUpdateModal(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>', '<?php echo htmlspecialchars($product['sizes_stock'] ?? ''); ?>')">
                                        📝 Update Stock
                                    </button>
                                    <button class="btn btn-info btn-sm" onclick="viewHistory(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')">
                                        📊 View History
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: #666; font-size: 24px;">
                                    🔍 No products found matching your criteria
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Update Stock Modal -->
    <div id="updateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>📝 Update Stock</h3>
                <span class="close" onclick="closeModal('updateModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div id="updateModalContent">
                    <!-- Content will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <!-- History Modal -->
    <div id="historyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>📊 Inventory History</h3>
                <span class="close" onclick="closeModal('historyModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div id="historyModalContent">
                    <div class="loading">📊 Loading history...</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Update current date and time
        function updateDateTime() {
            const now = new Date();
            const options = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                timeZoneName: 'short'
            };
            document.getElementById('currentDateTime').textContent = now.toLocaleDateString('en-US', options);
        }

        // Update time every second
        setInterval(updateDateTime, 1000);
        updateDateTime(); // Initial call

        // Update subcategories based on main category selection
        function updateSubcategories() {
            const mainCategory = document.getElementById('main_category').value;
            const subcategorySelect = document.getElementById('subcategory');
            
            // Clear existing options
            subcategorySelect.innerHTML = '<option value="">All Subcategories</option>';
            
            if (mainCategory) {
                const categories = <?php echo json_encode($categoriesData); ?>;
                if (categories[mainCategory]) {
                    Object.entries(categories[mainCategory]).forEach(([key, value]) => {
                        const option = document.createElement('option');
                        option.value = key;
                        option.textContent = value;
                        subcategorySelect.appendChild(option);
                    });
                }
            }
        }

        function openUpdateModal(productId, productName, sizesStock) {
            const modal = document.getElementById('updateModal');
            const content = document.getElementById('updateModalContent');
            
            let html = `<h4>📦 ${productName}</h4>`;
            html += `<input type="text" class="admin-input" id="adminName" placeholder="👤 Enter Admin Name" required>`;
            html += `<input type="text" class="reason-input" id="updateReason" placeholder="📝 Reason for stock change" required>`;
            html += `<div class="size-update-grid">`;
            
            if (sizesStock) {
                const sizes = sizesStock.split('|');
                sizes.forEach(sizeStock => {
                    const parts = sizeStock.split(':');
                    if (parts.length >= 3) {
                        const size = parts[0];
                        const stock = parts[1];
                        const sizeId = parts[2];
                        
                        html += `
                            <div class="size-update-item">
                                <div class="size-label">📏 Size ${size}</div>
                                <div class="current-stock">📦 Current: ${stock} units</div>
                                <input type="number" class="quantity-input" placeholder="±0" data-size-id="${sizeId}" data-product-id="${productId}" min="0">
                                <button class="btn btn-success btn-sm" onclick="updateStock(${sizeId}, ${productId}, this)">➕ Add</button>
                                <button class="btn btn-danger btn-sm" onclick="updateStock(${sizeId}, ${productId}, this, true)">➖ Deduct</button>
                            </div>
                        `;
                    }
                });
            } else {
                html += '<p style="text-align: center; font-size: 22px; color: #666;">📭 No size variants found for this product.</p>';
            }
            
            html += `</div>`;
            content.innerHTML = html;
            modal.style.display = 'block';
        }

        function updateStock(sizeId, productId, button, isDeduction = false) {
            const input = button.parentElement.querySelector('.quantity-input');
            const adminName = document.getElementById('adminName').value.trim();
            const reason = document.getElementById('updateReason').value.trim();
            
            if (!adminName) {
                showAlert('❌ Please enter admin name', 'error');
                return;
            }
            
            if (!reason) {
                showAlert('❌ Please enter reason for stock change', 'error');
                return;
            }
            
            let quantity = parseInt(input.value) || 0;
            if (quantity <= 0) {
                showAlert('❌ Please enter a valid quantity', 'error');
                return;
            }
            
            if (isDeduction) {
                quantity = -quantity;
            }
            
            const formData = new FormData();
            formData.append('action', 'update_stock');
            formData.append('size_id', sizeId);
            formData.append('product_id', productId);
            formData.append('quantity_change', quantity);
            formData.append('reason', reason);
            formData.append('admin_name', adminName);
            
            button.disabled = true;
            button.textContent = isDeduction ? '⏳ Deducting...' : '⏳ Adding...';
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('✅ Stock updated successfully!', 'success');
                    // Update the current stock display
                    const currentStockEl = button.parentElement.querySelector('.current-stock');
                    currentStockEl.textContent = `📦 Current: ${data.new_stock} units`;
                    input.value = '';
                    
                    // Refresh page after 2 seconds to show updated data
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    showAlert('❌ Error: ' + data.error, 'error');
                }
            })
            .catch(error => {
                showAlert('❌ Network error occurred', 'error');
            })
            .finally(() => {
                button.disabled = false;
                button.textContent = isDeduction ? '➖ Deduct' : '➕ Add';
            });
        }

        function viewHistory(productId, productName) {
            const modal = document.getElementById('historyModal');
            const content = document.getElementById('historyModalContent');
            
            content.innerHTML = `<h4>📊 ${productName}</h4><div class="loading">📊 Loading history...</div>`;
            modal.style.display = 'block';
            
            const formData = new FormData();
            formData.append('action', 'get_history');
            formData.append('product_id', productId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let html = `<h4>📊 ${productName}</h4>`;
                    
                    if (data.history.length > 0) {
                        html += `
                            <div class="history-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>📅 Date & Time</th>
                                            <th>📏 Size</th>
                                            <th>📊 Change</th>
                                            <th>📝 Reason</th>
                                            <th>👤 Admin</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        `;
                        
                        data.history.forEach(record => {
                            const changeClass = record.quantity_change > 0 ? 'quantity-positive' : 'quantity-negative';
                            const changeSymbol = record.quantity_change > 0 ? '+' : '';
                            const changeIcon = record.quantity_change > 0 ? '📈' : '📉';
                            const date = new Date(record.created_at).toLocaleDateString() + ' ' + new Date(record.created_at).toLocaleTimeString();
                            
                            html += `
                                <tr>
                                    <td>${date}</td>
                                    <td>📏 ${record.size || 'N/A'}</td>
                                    <td class="${changeClass}">${changeIcon} ${changeSymbol}${record.quantity_change}</td>
                                    <td>${record.reason}</td>
                                    <td>👤 ${record.admin_name}</td>
                                </tr>
                            `;
                        });
                        
                        html += `
                                    </tbody>
                                </table>
                            </div>
                        `;
                    } else {
                        html += '<p style="text-align: center; font-size: 22px; color: #666;">📭 No history records found for this product.</p>';
                    }
                    
                    content.innerHTML = html;
                } else {
                    content.innerHTML = '<p style="text-align: center; font-size: 22px; color: #e74c3c;">❌ Error loading history.</p>';
                }
            })
            .catch(error => {
                content.innerHTML = '<p style="text-align: center; font-size: 22px; color: #e74c3c;">❌ Network error occurred while loading history.</p>';
            });
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function showAlert(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.textContent = message;
            
            // Insert at the top of the content wrapper
            const contentWrapper = document.querySelector('.content-wrapper');
            contentWrapper.insertBefore(alertDiv, contentWrapper.firstChild);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.parentNode.removeChild(alertDiv);
                }
            }, 5000);
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const updateModal = document.getElementById('updateModal');
            const historyModal = document.getElementById('historyModal');
            
            if (event.target == updateModal) {
                updateModal.style.display = 'none';
            }
            if (event.target == historyModal) {
                historyModal.style.display = 'none';
            }
        }

        // Auto-submit form when filters change
        document.addEventListener('DOMContentLoaded', function() {
            const filters = document.querySelectorAll('#main_category, #subcategory, #stock_status');
            filters.forEach(filter => {
                filter.addEventListener('change', function() {
                    // Don't auto-submit on subcategory change if triggered by main category change
                    if (this.id !== 'subcategory' || this.dataset.userChanged) {
                        this.form.submit();
                    }
                    this.dataset.userChanged = false;
                });
            });
            
            // Mark subcategory changes as user-initiated
            document.getElementById('subcategory').addEventListener('change', function() {
                this.dataset.userChanged = true;
            });

            // Show stock alerts
            const lowStockCount = <?php echo $lowStock; ?>;
            const outOfStockCount = <?php echo $outOfStock; ?>;
            
            if (lowStockCount > 0 || outOfStockCount > 0) {
                let message = '';
                if (outOfStockCount > 0) {
                    message += `🚨 ${outOfStockCount} product(s) are completely out of stock! `;
                }
                if (lowStockCount > 0) {
                    message += `⚠️ ${lowStockCount} product(s) have low stock levels.`;
                }
                
                if (message) {
                    setTimeout(() => {
                        showAlert(message.trim(), 'error');
                    }, 2000);
                }
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape key to close modals
            if (e.key === 'Escape') {
                closeModal('updateModal');
                closeModal('historyModal');
            }
            
            // Ctrl+F to focus search
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.getElementById('search').focus();
            }
        });

        // Add loading states and smooth animations
        document.addEventListener('DOMContentLoaded', function() {
            // Add fade-in animation to cards
            const cards = document.querySelectorAll('.stat-card, .inventory-table, .filters');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 200);
            });

            // Add hover effects to table rows
            const tableRows = document.querySelectorAll('tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f8f9ff';
                    this.style.transform = 'scale(1.01)';
                    this.style.boxShadow = '0 4px 15px rgba(0,0,0,0.1)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                    this.style.transform = 'scale(1)';
                    this.style.boxShadow = '';
                });
            });
        });

        // Print functionality
        function printInventoryReport() {
            const printWindow = window.open('', '_blank');
            const printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Inventory Report - Elegance Fashion</title>
                    <style>
                        body { font-family: Arial, sans-serif; font-size: 12px; }
                        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f5f5f5; font-weight: bold; }
                        .header { text-align: center; margin: 20px 0; }
                        .status-in-stock { color: green; }
                        .status-low-stock { color: orange; }
                        .status-out-of-stock { color: red; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>ELEGANCE FASHION</h1>
                        <h2>Inventory Report</h2>
                        <p>Generated on: ${new Date().toLocaleString()}</p>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Total Stock</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${Array.from(document.querySelectorAll('tbody tr')).map(row => {
                                const cells = row.querySelectorAll('td');
                                if (cells.length >= 6) {
                                    return `<tr>
                                        <td>${cells[0].textContent}</td>
                                        <td>${cells[1].textContent}</td>
                                        <td>${cells[2].textContent}</td>
                                        <td>${cells[4].textContent}</td>
                                        <td>${cells[5].textContent}</td>
                                    </tr>`;
                                }
                                return '';
                            }).join('')}
                        </tbody>
                    </table>
                </body>
                </html>
            `;
            
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.print();
        }

        // Add print button (you can add this to your UI if needed)
        // document.querySelector('.table-header').innerHTML += '<button onclick="printInventoryReport()" class="btn btn-info" style="float: right;">🖨️ Print Report</button>';
    </script>
</body>
</html>