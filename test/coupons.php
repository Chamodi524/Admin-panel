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
    <title>Coupons Management - Allura Estella</title>
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

        /* Formal Header - Matching Product Management */
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

        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .card h2 {
            color: #2c3e50;
            margin-bottom: 25px;
            font-size: 28px;
            font-weight: 600;
        }

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .form-group {
            flex: 1;
            min-width: 250px;
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

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 15px;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
            transform: scale(1.2);
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

        .btn-warning {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
        }

        .btn-sm {
            padding: 10px 20px;
            font-size: 18px;
        }

        .filters {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
            flex-wrap: wrap;
            align-items: end;
        }

        .table-container {
            overflow-x: auto;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
        }

        th, td {
            padding: 15px 12px;
            text-align: left;
            border-bottom: 2px solid #f1f3f4;
            font-size: 20px;
        }

        th {
            background: linear-gradient(135deg, #f8f9ff 0%, #ffffff 100%);
            font-weight: 600;
            color: #2c3e50;
        }

        tr:hover {
            background: linear-gradient(135deg, #f8f9ff 0%, #ffffff 100%);
        }

        .badge {
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
        }

        .badge-success {
            background: linear-gradient(135deg, #c6f6d5 0%, #9ae6b4 100%);
            color: #22543d;
        }

        .badge-danger {
            background: linear-gradient(135deg, #fed7d7 0%, #feb2b2 100%);
            color: #742a2a;
        }

        .badge-warning {
            background: linear-gradient(135deg, #feebc8 0%, #fbd38d 100%);
            color: #744210;
        }

        .progress-bar {
            width: 120px;
            height: 24px;
            background: #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
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
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 20px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 36px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
            transition: color 0.3s ease;
        }

        .close:hover {
            color: #333;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 35px;
        }

        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            color: #718096;
            margin-top: 10px;
            font-size: 20px;
            font-weight: 500;
        }

        .alert {
            padding: 20px 30px;
            margin-bottom: 25px;
            border-radius: 12px;
            border-left: 4px solid;
            font-size: 20px;
            font-weight: 500;
        }

        .alert-warning {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            border-color: #f59e0b;
            color: #92400e;
        }

        .alert-danger {
            background: linear-gradient(135deg, #fef2f2 0%, #fecaca 100%);
            border-color: #ef4444;
            color: #dc2626;
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
            
            .modal-content {
                width: 95%;
                margin: 5% auto;
                padding: 20px;
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
                <h2 class="system-title">COUPONS MANAGEMENT SYSTEM</h2>
                <p class="current-date-time" id="currentDateTime"></p>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="content-wrapper">

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
                <strong>Expiring Soon:</strong> 
                <?php foreach ($expiring as $coupon): ?>
                    <span><?= htmlspecialchars($coupon['code']) ?> (expires <?= date('M d, Y', strtotime($coupon['expires_at'])) ?>)</span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Create/Edit Coupon Form -->
        <div class="card">
            <h2><?= $edit_coupon ? 'Edit Coupon' : 'Create New Coupon' ?></h2>
            <form method="POST" id="couponForm">
                <input type="hidden" name="action" value="<?= $edit_coupon ? 'update' : 'create' ?>">
                <?php if ($edit_coupon): ?>
                    <input type="hidden" name="id" value="<?= $edit_coupon['id'] ?>">
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label for="code">Coupon Code</label>
                        <input type="text" id="code" name="code" value="<?= $edit_coupon['code'] ?? '' ?>" required>
                        <button type="button" class="btn btn-primary btn-sm" onclick="generateCode()" style="margin-top: 10px;">Generate Code</button>
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

                <div style="margin-top: 25px;">
                    <button type="submit" class="btn btn-primary"><?= $edit_coupon ? 'Update Coupon' : 'Create Coupon' ?></button>
                    <?php if ($edit_coupon): ?>
                        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-warning">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Filters and Search -->
        <div class="card">
            <h2>Filter & Search Coupons</h2>
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
            <h2>Coupons List</h2>
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
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Confirm Delete</h2>
            <p>Are you sure you want to delete this coupon? This action cannot be undone.</p>
            <div style="margin-top: 25px;">
                <button id="confirmDelete" class="btn btn-danger">Delete</button>
                <button onclick="closeModal()" class="btn btn-warning">Cancel</button>
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

        // Initialize date time on load
        document.addEventListener('DOMContentLoaded', function() {
            updateDateTime();
            setInterval(updateDateTime, 1000);
        });

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

        // Add fade-in animation to cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.card, .stat-card');
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