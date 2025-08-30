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
    <title>Inventory Management System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .header h1 {
            color: #2c3e50;
            font-size: 28px;
            font-weight: 600;
        }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 500;
            margin-bottom: 5px;
            color: #555;
        }

        .form-group input, .form-group select {
            padding: 10px 12px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #3498db;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background: #229954;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-info:hover {
            background: #138496;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        .stat-in-stock .stat-number { color: #27ae60; }
        .stat-low-stock .stat-number { color: #f39c12; }
        .stat-out-of-stock .stat-number { color: #e74c3c; }
        .stat-total .stat-number { color: #3498db; }

        .inventory-table {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .table-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
        }

        .table-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: #2c3e50;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .product-name {
            font-weight: 600;
            color: #2c3e50;
        }

        .stock-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-in-stock {
            background: #d4edda;
            color: #155724;
        }

        .status-low-stock {
            background: #fff3cd;
            color: #856404;
        }

        .status-out-of-stock {
            background: #f8d7da;
            color: #721c24;
        }

        .size-stock {
            display: inline-block;
            margin: 2px 4px;
            padding: 2px 8px;
            background: #f8f9fa;
            border-radius: 4px;
            font-size: 12px;
            border: 1px solid #dee2e6;
        }

        .actions {
            white-space: nowrap;
        }

        .actions button {
            margin: 0 2px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            color: #2c3e50;
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }

        .close:hover {
            color: #000;
        }

        .modal-body {
            padding: 20px;
        }

        .size-update-grid {
            display: grid;
            gap: 15px;
        }

        .size-update-item {
            display: grid;
            grid-template-columns: auto 1fr auto auto auto;
            gap: 10px;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .size-label {
            font-weight: 600;
            min-width: 40px;
        }

        .current-stock {
            color: #666;
            font-size: 14px;
        }

        .quantity-input {
            width: 80px;
            padding: 6px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .reason-input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin: 10px 0;
            width: 100%;
        }

        .admin-input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 15px;
            width: 100%;
        }

        .history-table {
            margin-top: 20px;
        }

        .history-table table {
            font-size: 14px;
        }

        .history-table th, .history-table td {
            padding: 10px;
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
            padding: 20px;
            color: #666;
        }

        .alert {
            padding: 12px 20px;
            margin: 15px 0;
            border-radius: 8px;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            table {
                font-size: 14px;
            }
            
            th, td {
                padding: 10px 8px;
            }
            
            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📦 Inventory Management System</h1>
        </div>

        <!-- Filters -->
        <div class="filters">
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
                    <button type="submit" class="btn btn-primary">Filter</button>
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
                <div class="stat-label">Total Products</div>
            </div>
            <div class="stat-card stat-in-stock">
                <div class="stat-number"><?php echo $inStock; ?></div>
                <div class="stat-label">In Stock</div>
            </div>
            <div class="stat-card stat-low-stock">
                <div class="stat-number"><?php echo $lowStock; ?></div>
                <div class="stat-label">Low Stock</div>
            </div>
            <div class="stat-card stat-out-of-stock">
                <div class="stat-number"><?php echo $outOfStock; ?></div>
                <div class="stat-label">Out of Stock</div>
            </div>
        </div>

        <!-- Inventory Table -->
        <div class="inventory-table">
            <div class="table-header">
                <h2>Stock Management</h2>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Product ID</th>
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
                            <td><?php echo htmlspecialchars($product['id']); ?></td>
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
                            <td><strong><?php echo $totalStock; ?></strong></td>
                            <td><span class="stock-status <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                            <td class="actions">
                                <button class="btn btn-success btn-sm" onclick="openUpdateModal(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>', '<?php echo htmlspecialchars($product['sizes_stock'] ?? ''); ?>')">
                                    Update Stock
                                </button>
                                <button class="btn btn-info btn-sm" onclick="viewHistory(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')">
                                    View History
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Update Stock Modal -->
    <div id="updateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Stock</h3>
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
                <h3>Inventory History</h3>
                <span class="close" onclick="closeModal('historyModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div id="historyModalContent">
                    <div class="loading">Loading history...</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openUpdateModal(productId, productName, sizesStock) {
            const modal = document.getElementById('updateModal');
            const content = document.getElementById('updateModalContent');
            
            let html = `<h4>${productName}</h4>`;
            html += `<input type="text" class="admin-input" id="adminName" placeholder="Admin Name" required>`;
            html += `<input type="text" class="reason-input" id="updateReason" placeholder="Reason for stock change" required>`;
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
                                <div class="size-label">${size}</div>
                                <div class="current-stock">Current: ${stock}</div>
                                <input type="number" class="quantity-input" placeholder="±0" data-size-id="${sizeId}" data-product-id="${productId}">
                                <button class="btn btn-success btn-sm" onclick="updateStock(${sizeId}, ${productId}, this)">Add</button>
                                <button class="btn btn-danger btn-sm" onclick="updateStock(${sizeId}, ${productId}, this, true)">Deduct</button>
                            </div>
                        `;
                    }
                });
            } else {
                html += '<p>No size variants found for this product.</p>';
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
                alert('Please enter admin name');
                return;
            }
            
            if (!reason) {
                alert('Please enter reason for stock change');
                return;
            }
            
            let quantity = parseInt(input.value) || 0;
            if (quantity <= 0) {
                alert('Please enter a valid quantity');
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
            button.textContent = 'Updating...';
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Stock updated successfully!', 'success');
                    // Update the current stock display
                    const currentStockEl = button.parentElement.querySelector('.current-stock');
                    currentStockEl.textContent = `Current: ${data.new_stock}`;
                    input.value = '';
                    
                    // Refresh page after 1 second to show updated data
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showAlert('Error: ' + data.error, 'error');
                }
            })
            .catch(error => {
                showAlert('Network error occurred', 'error');
            })
            .finally(() => {
                button.disabled = false;
                button.textContent = isDeduction ? 'Deduct' : 'Add';
            });
        }

        function viewHistory(productId, productName) {
            const modal = document.getElementById('historyModal');
            const content = document.getElementById('historyModalContent');
            
            content.innerHTML = `<h4>${productName}</h4><div class="loading">Loading history...</div>`;
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
                    let html = `<h4>${productName}</h4>`;
                    
                    if (data.history.length > 0) {
                        html += `
                            <div class="history-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Size</th>
                                            <th>Change</th>
                                            <th>Reason</th>
                                            <th>Admin</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        `;
                        
                        data.history.forEach(record => {
                            const changeClass = record.quantity_change > 0 ? 'quantity-positive' : 'quantity-negative';
                            const changeSymbol = record.quantity_change > 0 ? '+' : '';
                            const date = new Date(record.created_at).toLocaleDateString() + ' ' + new Date(record.created_at).toLocaleTimeString();
                            
                            html += `
                                <tr>
                                    <td>${date}</td>
                                    <td>${record.size || 'N/A'}</td>
                                    <td class="${changeClass}">${changeSymbol}${record.quantity_change}</td>
                                    <td>${record.reason}</td>
                                    <td>${record.admin_name}</td>
                                </tr>
                            `;
                        });
                        
                        html += `
                                    </tbody>
                                </table>
                            </div>
                        `;
                    } else {
                        html += '<p>No history records found for this product.</p>';
                    }
                    
                    content.innerHTML = html;
                } else {
                    content.innerHTML = '<p>Error loading history.</p>';
                }
            })
            .catch(error => {
                content.innerHTML = '<p>Network error occurred while loading history.</p>';
            });
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function showAlert(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.textContent = message;
            
            // Insert at the top of the container
            const container = document.querySelector('.container');
            container.insertBefore(alertDiv, container.firstChild);
            
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
        });



        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape key to close modals
            if (e.key === 'Escape') {
                closeModal('updateModal');
                closeModal('historyModal');
            }
        });

        // Add low stock alert on page load
        document.addEventListener('DOMContentLoaded', function() {
            const lowStockCount = <?php echo $lowStock; ?>;
            const outOfStockCount = <?php echo $outOfStock; ?>;
            
            if (lowStockCount > 0 || outOfStockCount > 0) {
                let message = '';
                if (outOfStockCount > 0) {
                    message += `⚠️ ${outOfStockCount} product(s) are out of stock. `;
                }
                if (lowStockCount > 0) {
                    message += `📉 ${lowStockCount} product(s) have low stock.`;
                }
                
                if (message) {
                    setTimeout(() => {
                        showAlert(message.trim(), 'error');
                    }, 1000);
                }
            }
        });

        // Auto-refresh functionality (optional)
        function enableAutoRefresh(minutes = 5) {
            setInterval(() => {
                if (!document.getElementById('updateModal').style.display === 'block' && 
                    !document.getElementById('historyModal').style.display === 'block') {
                    location.reload();
                }
            }, minutes * 60 * 1000);
        }

        // Uncomment the line below to enable auto-refresh every 5 minutes
        // enableAutoRefresh(5);
    </script>
</body>
</html>