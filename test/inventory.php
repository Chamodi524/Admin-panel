<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "testbase";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_inventory':
            $search = $_POST['search'] ?? '';
            $category = $_POST['category'] ?? '';
            $availability = $_POST['availability'] ?? '';
            
            $sql = "SELECT p.*, 
                           COALESCE(SUM(ps.stock_quantity), 0) as total_stock,
                           COUNT(ps.id) as size_variants
                    FROM products p 
                    LEFT JOIN product_sizes ps ON p.id = ps.product_id 
                    WHERE 1=1";
            $params = [];
            
            if ($search) {
                $sql .= " AND p.name LIKE ?";
                $params[] = "%$search%";
            }
            
            if ($category) {
                $sql .= " AND p.category = ?";
                $params[] = $category;
            }
            
            if ($availability) {
                $sql .= " AND p.availability = ?";
                $params[] = $availability;
            }
            
            $sql .= " GROUP BY p.id ORDER BY p.name";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($products);
            exit;
            
        case 'get_low_stock':
            $threshold = $_POST['threshold'] ?? 10;
            
            $sql = "SELECT p.name, p.category, ps.size, ps.stock_quantity
                    FROM products p
                    JOIN product_sizes ps ON p.id = ps.product_id
                    WHERE ps.stock_quantity <= ?
                    ORDER BY ps.stock_quantity ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$threshold]);
            $lowStock = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($lowStock);
            exit;
            
        case 'update_stock':
            $productId = $_POST['product_id'];
            $sizeId = $_POST['size_id'];
            $newStock = $_POST['new_stock'];
            
            $stmt = $pdo->prepare("UPDATE product_sizes SET stock_quantity = ? WHERE id = ? AND product_id = ?");
            $stmt->execute([$newStock, $sizeId, $productId]);
            
            echo json_encode(['success' => true]);
            exit;
            
        case 'export_csv':
            $sql = "SELECT p.name, p.category, p.subcategory, p.price, p.availability,
                           ps.size, ps.stock_quantity
                    FROM products p
                    LEFT JOIN product_sizes ps ON p.id = ps.product_id
                    ORDER BY p.name, ps.size";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="inventory_export.csv"');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Product Name', 'Category', 'Subcategory', 'Price', 'Availability', 'Size', 'Stock Quantity']);
            
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
            
            fclose($output);
            exit;
            
        case 'import_csv':
            if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
                $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
                $header = fgetcsv($file); // Skip header
                $imported = 0;
                $errors = [];
                
                while (($row = fgetcsv($file)) !== FALSE) {
                    try {
                        // Check if product exists
                        $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ?");
                        $stmt->execute([$row[0]]);
                        $product = $stmt->fetch();
                        
                        if (!$product) {
                            // Create new product
                            $stmt = $pdo->prepare("INSERT INTO products (name, category, subcategory, price, availability) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$row[0], $row[1], $row[2], $row[3], $row[4]]);
                            $productId = $pdo->lastInsertId();
                        } else {
                            $productId = $product['id'];
                        }
                        
                        // Update or insert size stock
                        $stmt = $pdo->prepare("INSERT INTO product_sizes (product_id, size, stock_quantity) 
                                               VALUES (?, ?, ?) 
                                               ON DUPLICATE KEY UPDATE stock_quantity = VALUES(stock_quantity)");
                        $stmt->execute([$productId, $row[5], $row[6]]);
                        $imported++;
                        
                    } catch (Exception $e) {
                        $errors[] = "Row " . ($imported + 1) . ": " . $e->getMessage();
                    }
                }
                
                fclose($file);
                echo json_encode(['success' => true, 'imported' => $imported, 'errors' => $errors]);
            } else {
                echo json_encode(['success' => false, 'message' => 'File upload failed']);
            }
            exit;
    }
}

// Get categories for filter
$stmt = $pdo->prepare("SELECT DISTINCT category FROM products ORDER BY category");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
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
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .main-content {
            padding: 30px;
        }

        .tabs {
            display: flex;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 30px;
        }

        .tab {
            padding: 15px 25px;
            cursor: pointer;
            background: none;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            color: #666;
            transition: all 0.3s ease;
            border-radius: 10px 10px 0 0;
        }

        .tab.active {
            color: #667eea;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 15px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }

        .filter-group input,
        .filter-group select {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .inventory-table {
            overflow-x: auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .stock-input {
            width: 80px;
            padding: 8px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            text-align: center;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-in-stock {
            background: #d4edda;
            color: #155724;
        }

        .status-out-of-stock {
            background: #f8d7da;
            color: #721c24;
        }

        .low-stock-item {
            background: #fff3cd;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 10px;
            border-left: 4px solid #ffc107;
        }

        .file-upload {
            padding: 20px;
            border: 2px dashed #667eea;
            border-radius: 15px;
            text-align: center;
            background: #f8f9ff;
            margin-bottom: 20px;
        }

        .file-upload input[type="file"] {
            margin: 10px 0;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 10px;
            font-weight: 600;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #667eea;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 1rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .loading {
            text-align: center;
            padding: 50px;
            font-size: 1.2rem;
            color: #666;
        }

        @media (max-width: 768px) {
            .filters {
                grid-template-columns: 1fr;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .tab {
                border-radius: 0;
            }
            
            .main-content {
                padding: 15px;
            }
            
            th, td {
                padding: 10px 5px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📦 Inventory Management</h1>
            <p>Track, manage, and optimize your product inventory</p>
        </div>

        <div class="main-content">
            <div class="tabs">
                <button class="tab active" onclick="showTab('inventory')">📋 Current Stock</button>
                <button class="tab" onclick="showTab('alerts')">⚠️ Low Stock Alerts</button>
                <button class="tab" onclick="showTab('reports')">📊 Reports</button>
                <button class="tab" onclick="showTab('import-export')">📁 Import/Export</button>
            </div>

            <!-- Current Stock Tab -->
            <div id="inventory" class="tab-content active">
                <div class="filters">
                    <div class="filter-group">
                        <label>Search Products</label>
                        <input type="text" id="searchInput" placeholder="Enter product name...">
                    </div>
                    <div class="filter-group">
                        <label>Category</label>
                        <select id="categoryFilter">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Availability</label>
                        <select id="availabilityFilter">
                            <option value="">All Status</option>
                            <option value="in_stock">In Stock</option>
                            <option value="out_of_stock">Out of Stock</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button class="btn btn-primary" onclick="loadInventory()">🔍 Filter</button>
                    </div>
                </div>

                <div class="inventory-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Total Stock</th>
                                <th>Sizes</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="inventoryTableBody">
                            <tr>
                                <td colspan="7" class="loading">Loading inventory...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Low Stock Alerts Tab -->
            <div id="alerts" class="tab-content">
                <div class="filters">
                    <div class="filter-group">
                        <label>Low Stock Threshold</label>
                        <input type="number" id="thresholdInput" value="10" min="1">
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button class="btn btn-warning" onclick="loadLowStock()">⚠️ Check Alerts</button>
                    </div>
                </div>

                <div id="lowStockContainer">
                    <div class="loading">Click "Check Alerts" to view low stock items</div>
                </div>
            </div>

            <!-- Reports Tab -->
            <div id="reports" class="tab-content">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number" id="totalProducts">-</div>
                        <div class="stat-label">Total Products</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="totalStock">-</div>
                        <div class="stat-label">Total Stock Units</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="lowStockCount">-</div>
                        <div class="stat-label">Low Stock Items</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="outOfStockCount">-</div>
                        <div class="stat-label">Out of Stock</div>
                    </div>
                </div>

                <div style="text-align: center; margin-top: 30px;">
                    <button class="btn btn-primary" onclick="generateReport()">📊 Generate Report</button>
                </div>
            </div>

            <!-- Import/Export Tab -->
            <div id="import-export" class="tab-content">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                    <div>
                        <h3>📤 Export Inventory</h3>
                        <p>Download your current inventory data as CSV file</p>
                        <br>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="action" value="export_csv">
                            <button type="submit" class="btn btn-success">📥 Download CSV</button>
                        </form>
                    </div>

                    <div>
                        <h3>📥 Import Inventory</h3>
                        <div class="file-upload">
                            <p>Upload CSV file to import/update inventory</p>
                            <form id="importForm" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="import_csv">
                                <input type="file" name="csv_file" accept=".csv" required>
                                <br>
                                <button type="submit" class="btn btn-primary">📤 Import CSV</button>
                            </form>
                        </div>
                        <div id="importResult"></div>
                    </div>
                </div>

                <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 15px;">
                    <h4>CSV Format Requirements:</h4>
                    <p><strong>Columns:</strong> Product Name, Category, Subcategory, Price, Availability, Size, Stock Quantity</p>
                    <p><strong>Availability:</strong> Use "in_stock" or "out_of_stock"</p>
                    <p><strong>Sizes:</strong> XS, S, M, L, XL, XXL, FREE_SIZE</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let currentInventory = [];

        // Load inventory on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadInventory();
        });

        // Tab switching
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });

            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });

            // Show selected tab content
            document.getElementById(tabName).classList.add('active');

            // Add active class to clicked tab
            event.target.classList.add('active');

            // Load data based on tab
            if (tabName === 'reports') {
                generateReport();
            }
        }

        // Load inventory data
        function loadInventory() {
            const search = document.getElementById('searchInput').value;
            const category = document.getElementById('categoryFilter').value;
            const availability = document.getElementById('availabilityFilter').value;

            const formData = new FormData();
            formData.append('action', 'get_inventory');
            formData.append('search', search);
            formData.append('category', category);
            formData.append('availability', availability);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                currentInventory = data;
                displayInventory(data);
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('inventoryTableBody').innerHTML = 
                    '<tr><td colspan="7" style="text-align: center; color: red;">Error loading inventory</td></tr>';
            });
        }

        // Display inventory in table
        function displayInventory(products) {
            const tbody = document.getElementById('inventoryTableBody');
            
            if (products.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align: center;">No products found</td></tr>';
                return;
            }

            tbody.innerHTML = products.map(product => `
                <tr>
                    <td>
                        <strong>${product.name}</strong>
                        ${product.subcategory ? `<br><small>${product.subcategory}</small>` : ''}
                    </td>
                    <td>${product.category}</td>
                    <td>$${parseFloat(product.price).toFixed(2)}</td>
                    <td>
                        <span style="font-weight: bold; color: ${product.total_stock > 10 ? '#28a745' : product.total_stock > 0 ? '#ffc107' : '#dc3545'}">
                            ${product.total_stock}
                        </span>
                    </td>
                    <td>${product.size_variants} variants</td>
                    <td>
                        <span class="status-badge status-${product.availability}">
                            ${product.availability.replace('_', ' ')}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-secondary" onclick="viewProductDetails(${product.id})" style="font-size: 0.8rem; padding: 5px 10px;">
                            View Details
                        </button>
                    </td>
                </tr>
            `).join('');
        }

        // View product details (sizes and stock)
        function viewProductDetails(productId) {
            // Find product in current inventory
            const product = currentInventory.find(p => p.id == productId);
            if (!product) return;

            // Fetch detailed size information
            const formData = new FormData();
            formData.append('action', 'get_product_sizes');
            formData.append('product_id', productId);

            // For now, we'll show a simple alert. In a real app, you'd show a modal
            alert(`Product: ${product.name}\nTotal Stock: ${product.total_stock}\nSize Variants: ${product.size_variants}`);
        }

        // Load low stock alerts
        function loadLowStock() {
            const threshold = document.getElementById('thresholdInput').value;
            const container = document.getElementById('lowStockContainer');

            container.innerHTML = '<div class="loading">Loading low stock alerts...</div>';

            const formData = new FormData();
            formData.append('action', 'get_low_stock');
            formData.append('threshold', threshold);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                displayLowStock(data);
            })
            .catch(error => {
                console.error('Error:', error);
                container.innerHTML = '<div class="alert alert-danger">Error loading low stock alerts</div>';
            });
        }

        // Display low stock items
        function displayLowStock(items) {
            const container = document.getElementById('lowStockContainer');

            if (items.length === 0) {
                container.innerHTML = '<div class="alert alert-success">✅ No low stock items found!</div>';
                return;
            }

            container.innerHTML = `
                <div class="alert alert-warning">
                    <strong>⚠️ ${items.length} items with low stock found</strong>
                </div>
                ${items.map(item => `
                    <div class="low-stock-item">
                        <strong>${item.name}</strong> (${item.category})
                        <br>
                        Size: ${item.size} - Stock: <strong style="color: #dc3545;">${item.stock_quantity}</strong>
                    </div>
                `).join('')}
            `;
        }

        // Generate report
        function generateReport() {
            if (currentInventory.length === 0) {
                loadInventory().then(() => calculateStats());
            } else {
                calculateStats();
            }
        }

        // Calculate statistics
        function calculateStats() {
            const totalProducts = currentInventory.length;
            const totalStock = currentInventory.reduce((sum, product) => sum + parseInt(product.total_stock), 0);
            const lowStockCount = currentInventory.filter(product => product.total_stock <= 10).length;
            const outOfStockCount = currentInventory.filter(product => product.total_stock === 0).length;

            document.getElementById('totalProducts').textContent = totalProducts;
            document.getElementById('totalStock').textContent = totalStock;
            document.getElementById('lowStockCount').textContent = lowStockCount;
            document.getElementById('outOfStockCount').textContent = outOfStockCount;
        }

        // Handle CSV import
        document.getElementById('importForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const resultDiv = document.getElementById('importResult');

            resultDiv.innerHTML = '<div class="loading">Importing data...</div>';

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-success">
                            ✅ Import successful! ${data.imported} records imported.inverntory
                            ${data.errors.length > 0 ? `<br><strong>Errors:</strong><br>${data.errors.join('<br>')}` : ''}
                        </div>
                    `;
                    loadInventory(); // Refresh inventory
                } else {
                    resultDiv.innerHTML = `<div class="alert alert-danger">❌ Import failed: ${data.message}</div>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultDiv.innerHTML = '<div class="alert alert-danger">❌ Import failed due to network error</div>';
            });
        });

        // Add search functionality with debounce
        let searchTimeout;
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                loadInventory();
            }, 500);
        });

        // Add filter change listeners
        document.getElementById('categoryFilter').addEventListener('change', loadInventory);
        document.getElementById('availabilityFilter').addEventListener('change', loadInventory);
    </script>
</body>
</html>