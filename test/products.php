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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
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

// Get categories
$stmt = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management - Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            font-weight: 300;
        }

        .header p {
            font-size: 1.1em;
            opacity: 0.9;
        }

        .main-content {
            padding: 30px;
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
            padding: 15px 50px 15px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #f8f9fa;
            color: #495057;
            border: 2px solid #e0e0e0;
        }

        .btn-secondary:hover {
            background: #e9ecef;
            border-color: #ccc;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }

        .product-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid #f0f0f0;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        .product-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 14px;
            position: relative;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-badges {
            position: absolute;
            top: 10px;
            left: 10px;
            display: flex;
            gap: 5px;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: white;
        }

        .badge-new {
            background: #28a745;
        }

        .badge-sale {
            background: #dc3545;
        }

        .product-info {
            padding: 20px;
        }

        .product-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }

        .product-category {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .product-price {
            font-size: 20px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 10px;
        }

        .product-price .original-price {
            text-decoration: line-through;
            color: #999;
            font-size: 16px;
            margin-left: 8px;
        }

        .product-stock {
            font-size: 14px;
            margin-bottom: 15px;
        }

        .stock-in {
            color: #28a745;
        }

        .stock-out {
            color: #dc3545;
        }

        .product-sizes {
            margin-bottom: 15px;
        }

        .sizes-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }

        .sizes-list {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }

        .size-tag {
            background: #f8f9fa;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            color: #495057;
        }

        .product-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .product-actions .btn {
            flex: 1;
            padding: 8px 12px;
            font-size: 14px;
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
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background-color: white;
            margin: 3% auto;
            padding: 0;
            border-radius: 20px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 20px 20px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.5em;
            font-weight: 300;
        }

        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .close:hover {
            opacity: 0.7;
        }

        .modal-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .checkbox-group {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox-item input[type="checkbox"] {
            width: auto;
            margin: 0;
        }

        .sizes-management {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            background: #f8f9fa;
        }

        .sizes-management h4 {
            margin-bottom: 15px;
            color: #333;
        }

        .size-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }

        .size-row select,
        .size-row input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }

        .size-row button {
            padding: 8px 12px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid #e0e0e0;
            display: flex;
            gap: 15px;
            justify-content: flex-end;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
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

        .no-products {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .no-products h3 {
            margin-bottom: 15px;
            font-size: 1.5em;
        }

        @media (max-width: 768px) {
            .container {
                margin: 10px;
                border-radius: 15px;
            }
            
            .main-content {
                padding: 20px;
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
            
            .form-row {
                flex-direction: column;
            }
            
            .modal-content {
                width: 95%;
                margin: 5% auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Product Management</h1>
            <p>Manage your clothing store inventory with ease</p>
        </div>

        <div class="main-content">
            <div id="alert-container"></div>
            
            <div class="controls">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search products...">
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
                                <p class="product-category"><?php echo htmlspecialchars($product['category']); ?><?php echo $product['subcategory'] ? ' / ' . htmlspecialchars($product['subcategory']) : ''; ?></p>
                                
                                <div class="product-price">
                                    $<?php echo number_format($product['price'], 2); ?>
                                    <?php if ($product['original_price'] && $product['original_price'] > $product['price']): ?>
                                        <span class="original-price">$<?php echo number_format($product['original_price'], 2); ?></span>
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
                            <select id="productCategory" name="category" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category['name']); ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="productSubcategory">Subcategory</label>
                            <input type="text" id="productSubcategory" name="subcategory">
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
                            <button type="button" onclick="addSizeRow()" class="btn btn-secondary" style="margin-top: 10px;">Add Size</button>
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

        // Modal functions
        function openModal() {
            document.getElementById('productModal').style.display = 'block';
            document.getElementById('modalTitle').textContent = 'Add New Product';
            document.getElementById('productForm').reset();
            document.getElementById('productId').value = '';
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
                    document.getElementById('productSubcategory').value = product.subcategory || '';
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

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Add any initialization code here
            console.log('Product Management Dashboard loaded successfully');
        });
    </script>
</body>
</html>