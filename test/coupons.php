<?php
// Database connection
$host = 'localhost';
$dbname = 'testbase';
$username = 'root'; // Change as needed
$password = ''; // Change as needed

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $stmt = $pdo->prepare("INSERT INTO coupons (code, discount_type, discount_value, min_order_amount, max_uses, expires_at, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['code'],
                    $_POST['discount_type'],
                    $_POST['discount_value'],
                    $_POST['min_order_amount'] ?: 0,
                    $_POST['max_uses'] ?: null,
                    $_POST['expires_at'] ?: null,
                    isset($_POST['is_active']) ? 1 : 0
                ]);
                break;
            
            case 'update':
                $stmt = $pdo->prepare("UPDATE coupons SET code=?, discount_type=?, discount_value=?, min_order_amount=?, max_uses=?, expires_at=?, is_active=? WHERE id=?");
                $stmt->execute([
                    $_POST['code'],
                    $_POST['discount_type'],
                    $_POST['discount_value'],
                    $_POST['min_order_amount'] ?: 0,
                    $_POST['max_uses'] ?: null,
                    $_POST['expires_at'] ?: null,
                    isset($_POST['is_active']) ? 1 : 0,
                    $_POST['id']
                ]);
                break;
            
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM coupons WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                break;
            
            case 'toggle_status':
                $stmt = $pdo->prepare("UPDATE coupons SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                break;
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Get filters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$type_filter = $_GET['type'] ?? '';

// Build query with filters
$query = "SELECT * FROM coupons WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND code LIKE ?";
    $params[] = "%$search%";
}

if ($status_filter) {
    if ($status_filter === 'active') {
        $query .= " AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())";
    } elseif ($status_filter === 'expired') {
        $query .= " AND expires_at < NOW()";
    } elseif ($status_filter === 'inactive') {
        $query .= " AND is_active = 0";
    }
}

if ($type_filter) {
    $query .= " AND discount_type = ?";
    $params[] = $type_filter;
}

$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get coupon for editing
$edit_coupon = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_coupon = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coupons Management - Admin Panel</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
            font-size: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
            border-radius: 10px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 25px;
        }

        .card h2 {
            color: #4a5568;
            margin-bottom: 20px;
            font-size: 1.5rem;
        }

        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .form-group {
            flex: 1;
            min-width: 200px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #4a5568;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e2e8f0;
            border-radius: 5px;
            font-size: 20px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 20px;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-2px);
        }

        .btn-success {
            background: #48bb78;
            color: white;
        }

        .btn-success:hover {
            background: #38a169;
        }

        .btn-danger {
            background: #f56565;
            color: white;
        }

        .btn-danger:hover {
            background: #e53e3e;
        }

        .btn-warning {
            background: #ed8936;
            color: white;
        }

        .btn-warning:hover {
            background: #dd6b20;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 20px;
        }

        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: end;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            font-size: 20px;
        }

        th {
            background: #f7fafc;
            font-weight: 600;
            color: #4a5568;
        }

        tr:hover {
            background: #f7fafc;
        }

        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 20px;
            font-weight: 600;
        }

        .badge-success {
            background: #c6f6d5;
            color: #22543d;
        }

        .badge-danger {
            background: #fed7d7;
            color: #742a2a;
        }

        .badge-warning {
            background: #feebc8;
            color: #744210;
        }

        .progress-bar {
            width: 100px;
            height: 20px;
            background: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: #48bb78;
            transition: width 0.3s;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 36px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #000;
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
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }

        .stat-label {
            color: #718096;
            margin-top: 5px;
            font-size: 20px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            border-left: 4px solid;
            font-size: 20px;
        }

        .alert-warning {
            background: #fffbeb;
            border-color: #f59e0b;
            color: #92400e;
        }

        .alert-danger {
            background: #fef2f2;
            border-color: #ef4444;
            color: #dc2626;
        }

        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
            }
            
            .filters {
                flex-direction: column;
            }
            
            .stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏷️ Coupons Management</h1>
            <p>Create, manage, and track your discount coupons</p>
        </div>

        <?php
        // Statistics
        $stats = [
            'total' => $pdo->query("SELECT COUNT(*) FROM coupons")->fetchColumn(),
            'active' => $pdo->query("SELECT COUNT(*) FROM coupons WHERE is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())")->fetchColumn(),
            'expired' => $pdo->query("SELECT COUNT(*) FROM coupons WHERE expires_at < NOW()")->fetchColumn(),
            'used' => $pdo->query("SELECT SUM(used_count) FROM coupons")->fetchColumn()
        ];
        ?>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total'] ?></div>
                <div class="stat-label">Total Coupons</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['active'] ?></div>
                <div class="stat-label">Active Coupons</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['expired'] ?></div>
                <div class="stat-label">Expired Coupons</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['used'] ?></div>
                <div class="stat-label">Total Uses</div>
            </div>
        </div>

        <!-- Alerts for expiring coupons -->
        <?php
        $expiring = $pdo->query("SELECT * FROM coupons WHERE expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) AND is_active = 1")->fetchAll();
        if ($expiring): ?>
            <div class="alert alert-warning">
                <strong>⚠️ Expiring Soon:</strong> 
                <?php foreach ($expiring as $coupon): ?>
                    <span><?= htmlspecialchars($coupon['code']) ?> (expires <?= date('M d, Y', strtotime($coupon['expires_at'])) ?>)</span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Create/Edit Coupon Form -->
        <div class="card">
            <h2><?= $edit_coupon ? '✏️ Edit Coupon' : '➕ Create New Coupon' ?></h2>
            <form method="POST" id="couponForm">
                <input type="hidden" name="action" value="<?= $edit_coupon ? 'update' : 'create' ?>">
                <?php if ($edit_coupon): ?>
                    <input type="hidden" name="id" value="<?= $edit_coupon['id'] ?>">
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label for="code">Coupon Code</label>
                        <input type="text" id="code" name="code" value="<?= $edit_coupon['code'] ?? '' ?>" required>
                        <button type="button" class="btn btn-primary btn-sm" onclick="generateCode()" style="margin-top: 5px;">Generate Code</button>
                    </div>
                    <div class="form-group">
                        <label for="discount_type">Discount Type</label>
                        <select id="discount_type" name="discount_type" required>
                            <option value="percentage" <?= ($edit_coupon['discount_type'] ?? '') === 'percentage' ? 'selected' : '' ?>>Percentage</option>
                            <option value="fixed" <?= ($edit_coupon['discount_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Fixed Amount</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="discount_value">Discount Value</label>
                        <input type="number" id="discount_value" name="discount_value" step="0.01" value="<?= $edit_coupon['discount_value'] ?? '' ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="min_order_amount">Minimum Order Amount</label>
                        <input type="number" id="min_order_amount" name="min_order_amount" step="0.01" value="<?= $edit_coupon['min_order_amount'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label for="max_uses">Maximum Uses (leave empty for unlimited)</label>
                        <input type="number" id="max_uses" name="max_uses" value="<?= $edit_coupon['max_uses'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label for="expires_at">Expires At</label>
                        <input type="datetime-local" id="expires_at" name="expires_at" value="<?= $edit_coupon ? date('Y-m-d\TH:i', strtotime($edit_coupon['expires_at'])) : '' ?>">
                    </div>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="is_active" name="is_active" <?= ($edit_coupon['is_active'] ?? 1) ? 'checked' : '' ?>>
                    <label for="is_active">Active</label>
                </div>

                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary"><?= $edit_coupon ? 'Update Coupon' : 'Create Coupon' ?></button>
                    <?php if ($edit_coupon): ?>
                        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-warning">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Filters and Search -->
        <div class="card">
            <h2>🔍 Filter & Search Coupons</h2>
            <form method="GET" class="filters">
                <div class="form-group">
                    <label for="search">Search by Code</label>
                    <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Enter coupon code">
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">All Status</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="expired" <?= $status_filter === 'expired' ? 'selected' : '' ?>>Expired</option>
                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="type">Type</label>
                    <select id="type" name="type">
                        <option value="">All Types</option>
                        <option value="percentage" <?= $type_filter === 'percentage' ? 'selected' : '' ?>>Percentage</option>
                        <option value="fixed" <?= $type_filter === 'fixed' ? 'selected' : '' ?>>Fixed Amount</option>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-warning">Reset</a>
                </div>
            </form>
        </div>

        <!-- Coupons Table -->
        <div class="card">
            <h2>📋 Coupons List</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Type</th>
                            <th>Value</th>
                            <th>Min Order</th>
                            <th>Usage</th>
                            <th>Status</th>
                            <th>Expires</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($coupons as $coupon): ?>
                            <?php
                            $is_expired = $coupon['expires_at'] && strtotime($coupon['expires_at']) < time();
                            $is_active = $coupon['is_active'] && !$is_expired;
                            $usage_percentage = $coupon['max_uses'] ? ($coupon['used_count'] / $coupon['max_uses']) * 100 : 0;
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($coupon['code']) ?></strong></td>
                                <td><?= ucfirst($coupon['discount_type']) ?></td>
                                <td>
                                    <?= $coupon['discount_type'] === 'percentage' ? $coupon['discount_value'] . '%' : 'Rs.' . number_format($coupon['discount_value'], 2) ?>
                                </td>
                                <td>Rs.<?= number_format($coupon['min_order_amount'], 2) ?></td>
                                <td>
                                    <?= $coupon['used_count'] ?><?= $coupon['max_uses'] ? '/' . $coupon['max_uses'] : '' ?>
                                    <?php if ($coupon['max_uses']): ?>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?= min($usage_percentage, 100) ?>%"></div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($is_expired): ?>
                                        <span class="badge badge-danger">Expired</span>
                                    <?php elseif ($is_active): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $coupon['expires_at'] ? date('M d, Y H:i', strtotime($coupon['expires_at'])) : 'Never' ?>
                                </td>
                                <td><?= date('M d, Y', strtotime($coupon['created_at'])) ?></td>
                                <td>
                                    <a href="?edit=<?= $coupon['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                                    <button onclick="toggleStatus(<?= $coupon['id'] ?>)" class="btn btn-primary btn-sm">
                                        <?= $coupon['is_active'] ? 'Disable' : 'Enable' ?>
                                    </button>
                                    <button onclick="deleteCoupon(<?= $coupon['id'] ?>)" class="btn btn-danger btn-sm">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Confirm Delete</h2>
            <p>Are you sure you want to delete this coupon? This action cannot be undone.</p>
            <div style="margin-top: 20px;">
                <button id="confirmDelete" class="btn btn-danger">Delete</button>
                <button onclick="closeModal()" class="btn btn-warning">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        function generateCode() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            let code = '';
            for (let i = 0; i < 8; i++) {
                code += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            document.getElementById('code').value = code;
        }

        function toggleStatus(id) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        let deleteId = null;

        function deleteCoupon(id) {
            deleteId = id;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
            deleteId = null;
        }

        document.getElementById('confirmDelete').onclick = function() {
            if (deleteId) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${deleteId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        };

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                closeModal();
            }
        };

        // Form validation
        document.getElementById('couponForm').addEventListener('submit', function(e) {
            const code = document.getElementById('code').value;
            const discountValue = document.getElementById('discount_value').value;
            const discountType = document.getElementById('discount_type').value;
            
            if (code.length < 3) {
                alert('Coupon code must be at least 3 characters long');
                e.preventDefault();
                return;
            }
            
            if (discountType === 'percentage' && discountValue > 100) {
                alert('Percentage discount cannot exceed 100%');
                e.preventDefault();
                return;
            }
            
            if (discountValue <= 0) {
                alert('Discount value must be greater than 0');
                e.preventDefault();
                return;
            }
        });

        // Auto-refresh page every 5 minutes to check for expiring coupons
        setTimeout(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>