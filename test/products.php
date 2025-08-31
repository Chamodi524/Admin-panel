<?php
// Database configuration
$host = 'localhost';
$dbname = 'testbase';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Define categories and subcategories
$categories = [
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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_subcategories':
            $category = $_POST['category'] ?? '';
            $subcategories = $categories[$category] ?? [];
            echo json_encode(['success' => true, 'subcategories' => $subcategories]);
            exit;
            
        case 'add_product':
            try {
                $stmt = $pdo->prepare("INSERT INTO products (name, category, subcategory, price, original_price, image, description, availability, is_new, is_sale) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['category'],
                    $_POST['subcategory'],
                    $_POST['price'],
                    $_POST['original_price'] ?: null,
                    $_POST['image'],
                    $_POST['description'],
                    $_POST['availability'],
                    isset($_POST['is_new']) ? 1 : 0,
                    isset($_POST['is_sale']) ? 1 : 0
                ]);
                
                $product_id = $pdo->lastInsertId();
                
                // Add sizes if provided
                if (!empty($_POST['sizes'])) {
                    $sizes = json_decode($_POST['sizes'], true);
                    foreach ($sizes as $size) {
                        $stmt = $pdo->prepare("INSERT INTO product_sizes (product_id, size, stock_quantity, is_available) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$product_id, $size['size'], $size['stock'], $size['available']]);
                    }
                }
                
                echo json_encode(['success' => true, 'message' => 'Product added successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'update_product':
            try {
                $stmt = $pdo->prepare("UPDATE products SET name=?, category=?, subcategory=?, price=?, original_price=?, image=?, description=?, availability=?, is_new=?, is_sale=? WHERE id=?");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['category'],
                    $_POST['subcategory'],
                    $_POST['price'],
                    $_POST['original_price'] ?: null,
                    $_POST['image'],
                    $_POST['description'],
                    $_POST['availability'],
                    isset($_POST['is_new']) ? 1 : 0,
                    isset($_POST['is_sale']) ? 1 : 0,
                    $_POST['id']
                ]);
                
                // Update sizes
                if (!empty($_POST['sizes'])) {
                    // Delete existing sizes
                    $stmt = $pdo->prepare("DELETE FROM product_sizes WHERE product_id = ?");
                    $stmt->execute([$_POST['id']]);
                    
                    // Add new sizes
                    $sizes = json_decode($_POST['sizes'], true);
                    foreach ($sizes as $size) {
                        $stmt = $pdo->prepare("INSERT INTO product_sizes (product_id, size, stock_quantity, is_available) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$_POST['id'], $size['size'], $size['stock'], $size['available']]);
                    }
                }
                
                echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'delete_product':
            try {
                $stmt = $pdo->prepare("DELETE FROM product_sizes WHERE product_id = ?");
                $stmt->execute([$_POST['id']]);
                
                $stmt = $pdo->prepare("DELETE FROM product_images WHERE product_id = ?");
                $stmt->execute([$_POST['id']]);
                
                $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                
                echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'get_product':
            try {
                $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Get sizes
                $stmt = $pdo->prepare("SELECT * FROM product_sizes WHERE product_id = ?");
                $stmt->execute([$_POST['id']]);
                $sizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $product['sizes'] = $sizes;
                
                echo json_encode(['success' => true, 'product' => $product]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
    }
}

// Get all products
$stmt = $pdo->query("SELECT p.*, GROUP_CONCAT(ps.size ORDER BY ps.size) as sizes FROM products p LEFT JOIN product_sizes ps ON p.id = ps.product_id GROUP BY p.id ORDER BY p.created_at DESC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper function to get category display name
function getCategoryDisplayName($category) {
    $categoryNames = [
        'dresses' => 'Dresses',
        'tops' => 'Tops',
        'bottoms' => 'Bottoms',
        'rompers-jumpsuits' => 'Rompers & Jumpsuits',
        'office-work' => 'Office & Work'
    ];
    return $categoryNames[$category] ?? ucfirst($category);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management - Allura Estella</title>
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

        /* Formal Header - Matching Inventory Management */
        .formal-header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            color: white;
            padding: 20px 0;
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
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
        }

        .company-logo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255,255,255,0.3);
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
            flex-shrink: 0;
        }

        .header-text {
            text-align: left;
        }

        .company-name {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 5px;
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4, #45b7d1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .company-subtitle {
            font-size: 16px;
            font-weight: 300;
            opacity: 0.9;
            margin-bottom: 8px;
        }

        .system-title {
            font-size: 22px;
            font-weight: 600;
            color: #45b7d1;
            margin-bottom: 5px;
        }

        .current-date-time {
            font-size: 14px;
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

        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .search-box {
            position: relative;
            flex: 1;
            min-width: 300px;
        }

        .search-box input {
            width: 100%;
            padding: 16px 20px 16px 20px;
            border: 2px solid #e1e8ed;
            border-radius: 12px;
            font-size: 20px;
            transition: all 0.3s ease;
            background: white;
        }

        .search-box input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            transform: translateY(-2px);
        }

        .search-icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
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

        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }

        .btn-sm {
            padding: 10px 20px;
            font-size: 18px;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }

        .product-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }

        .product-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 18px;
            position: relative;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-badges {
            position: absolute;
            top: 15px;
            left: 15px;
            display: flex;
            gap: 8px;
        }

        .badge {
            padding: 6px 12px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-new {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        }

        .badge-sale {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }

        .product-info {
            padding: 25px;
        }

        .product-name {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #2c3e50;
        }

        .product-category {
            color: #666;
            font-size: 18px;
            margin-bottom: 15px;
        }

        .product-price {
            font-size: 24px;
            font-weight: 700;
            color: #3498db;
            margin-bottom: 15px;
        }

        .product-price .original-price {
            text-decoration: line-through;
            color: #999;
            font-size: 20px;
            margin-left: 10px;
        }

        .product-stock {
            font-size: 18px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .stock-in {
            color: #27ae60;
        }

        .stock-out {
            color: #e74c3c;
        }

        .product-sizes {
            margin-bottom: 20px;
        }

        .sizes-label {
            font-size: 16px;
            color: #666;
            margin-bottom: 8px;
        }

        .sizes-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .size-tag {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 16px;
            color: #495057;
            border: 1px solid #dee2e6;
        }

        .product-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .product-actions .btn {
            flex: 1;
            padding: 12px 20px;
            font-size: 18px;
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

        .modal-header h2 {
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

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 20px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e1e8ed;
            border-radius: 12px;
            font-size: 20px;
            transition: all 0.3s ease;
            background: white;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            transform: translateY(-2px);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-row {
            display: flex;
            gap: 20px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .checkbox-group {
            display: flex;
            gap: 30px;
            margin-top: 15px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-item input[type="checkbox"] {
            width: auto;
            margin: 0;
            transform: scale(1.2);
        }

        .sizes-management {
            border: 2px solid #e1e8ed;
            border-radius: 12px;
            padding: 25px;
            background: linear-gradient(135deg, #f8f9ff 0%, #ffffff 100%);
        }

        .sizes-management h4 {
            margin-bottom: 20px;
            color: #2c3e50;
            font-size: 22px;
        }

        .size-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            align-items: center;
        }

        .size-row select,
        .size-row input {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 18px;
        }

        .size-row button {
            padding: 12px 20px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .size-row button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }

        .modal-footer {
            padding: 25px 30px;
            border-top: 2px solid #f1f3f4;
            display: flex;
            gap: 20px;
            justify-content: flex-end;
            background: linear-gradient(135deg, #f8f9ff 0%, #ffffff 100%);
            border-radius: 0 0 20px 20px;
        }

        .alert {
            padding: 20px 30px;
            border-radius: 12px;
            margin-bottom: 25px;
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

        .no-products {
            text-align: center;
            padding: 80px 20px;
            color: #666;
        }

        .no-products h3 {
            margin-bottom: 20px;
            font-size: 32px;
            color: #2c3e50;
        }

        .no-products p {
            font-size: 22px;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .container {
                padding: 20px;
            }
            
            .formal-header {
                margin: -20px -20px 30px -20px;
                padding: 15px 0;
            }

            .formal-header-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .header-text {
                text-align: center;
            }
            
            .company-name {
                font-size: 28px;
            }
            
            .system-title {
                font-size: 20px;
            }
            
            .content-wrapper {
                padding: 25px;
            }
            
            .controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                min-width: auto;
            }
            
            .products-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            body {
                font-size: 18px;
            }
            
            .formal-header {
                padding: 10px 0;
            }

            .company-logo {
                width: 80px;
                height: 80px;
            }
            
            .company-name {
                font-size: 24px;
            }
            
            .company-subtitle {
                font-size: 14px;
            }
            
            .system-title {
                font-size: 18px;
            }

            .current-date-time {
                font-size: 12px;
            }
            
            .form-row {
                flex-direction: column;
            }
            
            .modal-content {
                width: 95%;
                margin: 5% auto;
            }

            .checkbox-group {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Formal Header -->
    <div class="formal-header">
        <div class="formal-header-content">
            <img src="allura_estrella.png" alt="Allura Estrella Logo" class="company-logo">
            <div class="header-text">
                <h1 class="company-name">ALLURA ESTELLA</h1>
                <p class="company-subtitle">Premium Women's Clothing & Accessories</p>
                <h2 class="system-title">PRODUCT MANAGEMENT SYSTEM</h2>
                <p class="current-date-time" id="currentDateTime"></p>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="content-wrapper">
            <div id="alert-container"></div>
            
            <div class="controls">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search products by name or category...">
                    <span class="search-icon">🔍</span>
                </div>
                <button class="btn btn-primary" onclick="openModal()">Add New Product</button>
            </div>

            <div class="products-grid" id="productsGrid">
                <?php if (empty($products)): ?>
                    <div class="no-products">
                        <h3>No products found</h3>
                        <p>Start by adding your first product to the inventory</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <div class="product-card" data-name="<?php echo strtolower($product['name']); ?>" data-category="<?php echo strtolower($product['category']); ?>">
                            <div class="product-image">
                                <?php if ($product['image']): ?>
                                    <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <?php else: ?>
                                    <span>No Image</span>
                                <?php endif; ?>
                                
                                <div class="product-badges">
                                    <?php if ($product['is_new']): ?>
                                        <span class="badge badge-new">NEW</span>
                                    <?php endif; ?>
                                    <?php if ($product['is_sale']): ?>
                                        <span class="badge badge-sale">SALE</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="product-info">
                                <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                                <p class="product-category">
                                    <?php echo getCategoryDisplayName($product['category']); ?>
                                    <?php if ($product['subcategory']): ?>
                                        <?php 
                                        // Get subcategory display name
                                        $subcategoryDisplay = $categories[$product['category']][$product['subcategory']] ?? ucfirst(str_replace('-', ' ', $product['subcategory']));
                                        echo ' / ' . htmlspecialchars($subcategoryDisplay); 
                                        ?>
                                    <?php endif; ?>
                                </p>
                                
                                <div class="product-price">
                                    Rs <?php echo number_format($product['price'], 2); ?>
                                    <?php if ($product['original_price'] && $product['original_price'] > $product['price']): ?>
                                        <span class="original-price">Rs <?php echo number_format($product['original_price'], 2); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="product-stock">
                                    <span class="<?php echo $product['availability'] == 'in_stock' ? 'stock-in' : 'stock-out'; ?>">
                                        <?php echo $product['availability'] == 'in_stock' ? 'In Stock' : 'Out of Stock'; ?>
                                    </span>
                                </div>
                                
                                <?php if ($product['sizes']): ?>
                                    <div class="product-sizes">
                                        <div class="sizes-label">Available Sizes:</div>
                                        <div class="sizes-list">
                                            <?php 
                                            $sizes = explode(',', $product['sizes']);
                                            foreach ($sizes as $size): ?>
                                                <span class="size-tag"><?php echo htmlspecialchars($size); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="product-actions">
                                    <button class="btn btn-secondary" onclick="editProduct(<?php echo $product['id']; ?>)">Edit</button>
                                    <button class="btn btn-danger" onclick="deleteProduct(<?php echo $product['id']; ?>)">Delete</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Product Modal -->
    <div id="productModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add New Product</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="productForm">
                    <input type="hidden" id="productId" name="id">
                    
                    <div class="form-group">
                        <label for="productName">Product Name *</label>
                        <input type="text" id="productName" name="name" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="productCategory">Category *</label>
                            <select id="productCategory" name="category" required onchange="updateSubcategories()">
                                <option value="">Select Category</option>
                                <option value="dresses">Dresses</option>
                                <option value="tops">Tops</option>
                                <option value="bottoms">Bottoms</option>
                                <option value="rompers-jumpsuits">Rompers & Jumpsuits</option>
                                <option value="office-work">Office & Work</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="productSubcategory">Subcategory</label>
                            <select id="productSubcategory" name="subcategory">
                                <option value="">Select Subcategory</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="productPrice">Price *</label>
                            <input type="number" id="productPrice" name="price" step="0.01" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="productOriginalPrice">Original Price</label>
                            <input type="number" id="productOriginalPrice" name="original_price" step="0.01">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="productImage">Image URL</label>
                        <input type="url" id="productImage" name="image">
                    </div>
                    
                    <div class="form-group">
                        <label for="productDescription">Description</label>
                        <textarea id="productDescription" name="description"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="productAvailability">Availability</label>
                        <select id="productAvailability" name="availability">
                            <option value="in_stock">In Stock</option>
                            <option value="out_of_stock">Out of Stock</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Product Flags</label>
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="checkbox" id="productIsNew" name="is_new">
                                <label for="productIsNew">New Product</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="productIsSale" name="is_sale">
                                <label for="productIsSale">On Sale</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="sizes-management">
                            <h4>Size Management</h4>
                            <div id="sizesContainer">
                                <div class="size-row">
                                    <select class="size-select">
                                        <option value="XS">XS</option>
                                        <option value="S">S</option>
                                        <option value="M">M</option>
                                        <option value="L">L</option>
                                        <option value="XL">XL</option>
                                        <option value="XXL">XXL</option>
                                        <option value="FREE_SIZE">Free Size</option>
                                    </select>
                                    <input type="number" class="stock-input" placeholder="Stock Quantity" min="0">
                                    <button type="button" onclick="removeSizeRow(this)">Remove</button>
                                </div>
                            </div>
                            <button type="button" onclick="addSizeRow()" class="btn btn-secondary" style="margin-top: 15px;">Add Size</button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveProduct()">Save Product</button>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let isEditing = false;
        let currentProductId = null;

        // Categories and subcategories data
        const categoriesData = <?php echo json_encode($categories); ?>;

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

        // Initialize date time on load
        document.addEventListener('DOMContentLoaded', function() {
            updateDateTime();
            setInterval(updateDateTime, 1000);
        });

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const productCards = document.querySelectorAll('.product-card');
            
            productCards.forEach(card => {
                const name = card.dataset.name;
                const category = card.dataset.category;
                
                if (name.includes(searchTerm) || category.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        // Update subcategories based on selected category
        function updateSubcategories() {
            const categorySelect = document.getElementById('productCategory');
            const subcategorySelect = document.getElementById('productSubcategory');
            const selectedCategory = categorySelect.value;
            
            // Clear existing options
            subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';
            
            if (selectedCategory && categoriesData[selectedCategory]) {
                Object.entries(categoriesData[selectedCategory]).forEach(([key, value]) => {
                    const option = document.createElement('option');
                    option.value = key;
                    option.textContent = value;
                    subcategorySelect.appendChild(option);
                });
            }
        }

        // Modal functions
        function openModal() {
            document.getElementById('productModal').style.display = 'block';
            document.getElementById('modalTitle').textContent = 'Add New Product';
            document.getElementById('productForm').reset();
            document.getElementById('productId').value = '';
            document.getElementById('productSubcategory').innerHTML = '<option value="">Select Subcategory</option>';
            isEditing = false;
            currentProductId = null;
            resetSizes();
        }

        function closeModal() {
            document.getElementById('productModal').style.display = 'none';
        }

        function resetSizes() {
            const container = document.getElementById('sizesContainer');
            container.innerHTML = `
                <div class="size-row">
                    <select class="size-select">
                        <option value="XS">XS</option>
                        <option value="S">S</option>
                        <option value="M">M</option>
                        <option value="L">L</option>
                        <option value="XL">XL</option>
                        <option value="XXL">XXL</option>
                        <option value="FREE_SIZE">Free Size</option>
                    </select>
                    <input type="number" class="stock-input" placeholder="Stock Quantity" min="0">
                    <button type="button" onclick="removeSizeRow(this)">Remove</button>
                </div>
            `;
        }

        function addSizeRow() {
            const container = document.getElementById('sizesContainer');
            const newRow = document.createElement('div');
            newRow.className = 'size-row';
            newRow.innerHTML = `
                <select class="size-select">
                    <option value="XS">XS</option>
                    <option value="S">S</option>
                    <option value="M">M</option>
                    <option value="L">L</option>
                    <option value="XL">XL</option>
                    <option value="XXL">XXL</option>
                    <option value="FREE_SIZE">Free Size</option>
                </select>
                <input type="number" class="stock-input" placeholder="Stock Quantity" min="0">
                <button type="button" onclick="removeSizeRow(this)">Remove</button>
            `;
            container.appendChild(newRow);
        }

        function removeSizeRow(button) {
            const container = document.getElementById('sizesContainer');
            if (container.children.length > 1) {
                button.parentElement.remove();
            }
        }

        function getSizesData() {
            const sizeRows = document.querySelectorAll('#sizesContainer .size-row');
            const sizes = [];
            
            sizeRows.forEach(row => {
                const size = row.querySelector('.size-select').value;
                const stock = row.querySelector('.stock-input').value;
                
                if (size && stock !== '') {
                    sizes.push({
                        size: size,
                        stock: parseInt(stock),
                        available: parseInt(stock) > 0 ? 1 : 0
                    });
                }
            });
            
            return sizes;
        }

        function setSizesData(sizes) {
            const container = document.getElementById('sizesContainer');
            container.innerHTML = '';
            
            if (sizes.length === 0) {
                resetSizes();
                return;
            }
            
            sizes.forEach(size => {
                const row = document.createElement('div');
                row.className = 'size-row';
                row.innerHTML = `
                    <select class="size-select">
                        <option value="XS" ${size.size === 'XS' ? 'selected' : ''}>XS</option>
                        <option value="S" ${size.size === 'S' ? 'selected' : ''}>S</option>
                        <option value="M" ${size.size === 'M' ? 'selected' : ''}>M</option>
                        <option value="L" ${size.size === 'L' ? 'selected' : ''}>L</option>
                        <option value="XL" ${size.size === 'XL' ? 'selected' : ''}>XL</option>
                        <option value="XXL" ${size.size === 'XXL' ? 'selected' : ''}>XXL</option>
                        <option value="FREE_SIZE" ${size.size === 'FREE_SIZE' ? 'selected' : ''}>Free Size</option>
                    </select>
                    <input type="number" class="stock-input" placeholder="Stock Quantity" min="0" value="${size.stock_quantity}">
                    <button type="button" onclick="removeSizeRow(this)">Remove</button>
                `;
                container.appendChild(row);
            });
        }

        // Product CRUD operations
        function saveProduct() {
            const form = document.getElementById('productForm');
            const formData = new FormData(form);
            
            // Add sizes data
            const sizes = getSizesData();
            formData.append('sizes', JSON.stringify(sizes));
            
            // Add action
            formData.append('action', isEditing ? 'update_product' : 'add_product');
            
            // Validate required fields
            if (!formData.get('name') || !formData.get('category') || !formData.get('price')) {
                showAlert('Please fill in all required fields', 'error');
                return;
            }
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    closeModal();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                showAlert('An error occurred: ' + error.message, 'error');
            });
        }

        function editProduct(id) {
            const formData = new FormData();
            formData.append('action', 'get_product');
            formData.append('id', id);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const product = data.product;
                    
                    // Fill form with product data
                    document.getElementById('productId').value = product.id;
                    document.getElementById('productName').value = product.name;
                    document.getElementById('productCategory').value = product.category;
                    
                    // Update subcategories first
                    updateSubcategories();
                    
                    // Then set subcategory value
                    setTimeout(() => {
                        document.getElementById('productSubcategory').value = product.subcategory || '';
                    }, 100);
                    
                    document.getElementById('productPrice').value = product.price;
                    document.getElementById('productOriginalPrice').value = product.original_price || '';
                    document.getElementById('productImage').value = product.image || '';
                    document.getElementById('productDescription').value = product.description || '';
                    document.getElementById('productAvailability').value = product.availability;
                    document.getElementById('productIsNew').checked = product.is_new == 1;
                    document.getElementById('productIsSale').checked = product.is_sale == 1;
                    
                    // Set sizes
                    setSizesData(product.sizes);
                    
                    // Update modal
                    document.getElementById('modalTitle').textContent = 'Edit Product';
                    document.getElementById('productModal').style.display = 'block';
                    isEditing = true;
                    currentProductId = id;
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                showAlert('An error occurred: ' + error.message, 'error');
            });
        }

        function deleteProduct(id) {
            if (!confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete_product');
            formData.append('id', id);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                showAlert('An error occurred: ' + error.message, 'error');
            });
        }

        // Alert system
        function showAlert(message, type) {
            const alertContainer = document.getElementById('alert-container');
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.textContent = message;
            
            alertContainer.appendChild(alertDiv);
            
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('productModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Add fade-in animation to cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.product-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>