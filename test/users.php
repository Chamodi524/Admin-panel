<?php
// Database configuration
$host = 'localhost';
$dbname = 'testbase';
$username = 'root';
$password = '';

// Create connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_users':
            try {
                $page = intval($_POST['page'] ?? 1);
                $limit = 10;
                $offset = ($page - 1) * $limit;
                
                $search = $_POST['search'] ?? '';
                $role_filter = $_POST['role_filter'] ?? '';
                
                $where_conditions = [];
                $params = [];
                
                if (!empty($search)) {
                    $where_conditions[] = "(username LIKE :search OR email LIKE :search)";
                    $params[':search'] = "%$search%";
                }
                
                if (!empty($role_filter)) {
                    $where_conditions[] = "role = :role";
                    $params[':role'] = $role_filter;
                }
                
                $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
                
                // Get total count
                $count_sql = "SELECT COUNT(*) as total FROM admin_users $where_clause";
                $count_stmt = $pdo->prepare($count_sql);
                $count_stmt->execute($params);
                $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
                
                // Get users
                $sql = "SELECT id, username, email, role, is_active, created_at, updated_at 
                        FROM admin_users $where_clause 
                        ORDER BY created_at DESC 
                        LIMIT :limit OFFSET :offset";
                $stmt = $pdo->prepare($sql);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'users' => $users,
                    'total' => $total_records,
                    'page' => $page,
                    'total_pages' => ceil($total_records / $limit)
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'toggle_status':
            try {
                $user_id = intval($_POST['user_id']);
                $new_status = intval($_POST['status']);
                
                $sql = "UPDATE admin_users SET is_active = :status WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':status' => $new_status, ':id' => $user_id]);
                
                echo json_encode(['success' => true, 'message' => 'User status updated successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'update_role':
            try {
                $user_id = intval($_POST['user_id']);
                $new_role = $_POST['role'];
                
                $sql = "UPDATE admin_users SET role = :role WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':role' => $new_role, ':id' => $user_id]);
                
                echo json_encode(['success' => true, 'message' => 'User role updated successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'delete_user':
            try {
                $user_id = intval($_POST['user_id']);
                
                $sql = "DELETE FROM admin_users WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':id' => $user_id]);
                
                echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'add_user':
            try {
                $username = $_POST['username'];
                $email = $_POST['email'];
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $role = $_POST['role'];
                
                $sql = "INSERT INTO admin_users (username, email, password, role) VALUES (:username, :email, :password, :role)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':username' => $username,
                    ':email' => $email,
                    ':password' => $password,
                    ':role' => $role
                ]);
                
                echo json_encode(['success' => true, 'message' => 'User added successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management System - Allura Estella</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
            max-width: 1600px; /* Further increased for better table fit */
            margin: 0 auto;
            padding: 20px;
        }

        /* Formal Header - Matching Coupon Management */
        .formal-header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            color: white;
            padding: 20px 0;
            margin: -20px -20px 40px -20px;
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
            max-width: 1600px; /* Further increased to match container */
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

        /* Main Content Wrapper */
        .content-wrapper {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 24px;
            padding: 50px; /* Increased from 40px */
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            width: 100%;
        }

        /* Updated Controls Section - Increased horizontal sizing */
        .controls {
            padding: 40px 50px; /* Increased horizontal padding from 30px to 50px */
            background: #f8f9fa;
            border-radius: 15px;
            margin-bottom: 30px;
            border: 1px solid rgba(255,255,255,0.2);
            width: 100%;
        }

        .controls-row {
            display: flex;
            gap: 50px; /* Further increased gap for better spacing */
            align-items: center;
            flex-wrap: wrap;
            width: 100%;
            justify-content: space-between;
        }

        /* Updated Search Box - Further increased width */
        .search-box {
            flex: 2.5; /* Increased from flex: 2 */
            min-width: 400px; /* Increased from 350px */
            position: relative;
            max-width: 600px; /* Increased from 500px */
        }

        .search-box input {
            width: 100%;
            padding: 18px 25px 18px 55px; /* Increased padding */
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 20px;
            transition: border-color 0.3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: #3498db;
        }

        .search-box i {
            position: absolute;
            left: 18px; /* Adjusted for increased padding */
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }

        /* Updated Filter Select - Further increased sizing */
        .filter-select {
            padding: 18px 25px; /* Increased padding */
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 20px;
            background: white;
            cursor: pointer;
            transition: border-color 0.3s;
            min-width: 200px; /* Increased from 180px */
            flex-shrink: 0;
        }

        .filter-select:focus {
            outline: none;
            border-color: #3498db;
        }

        .btn {
            padding: 15px 30px; /* Increased padding */
            border: none;
            border-radius: 8px;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px; /* Increased gap */
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            white-space: nowrap;
            flex-shrink: 0;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #2980b9, #1f5582);
        }

        .btn-success {
            background: linear-gradient(135deg, #27ae60, #229954);
            color: white;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #229954, #1e7e34);
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #c0392b, #a93226);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
        }

        .btn-warning:hover {
            background: linear-gradient(135deg, #e67e22, #d68910);
        }

        .btn-small {
            padding: 10px 16px; /* Optimized padding for table fit */
            font-size: 14px; /* Reduced font size for better fit */
            white-space: nowrap;
            min-width: 90px; /* Reduced for better table fit */
        }

        /* Table Responsive Wrapper */
        .table-responsive {
            overflow-x: auto;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            background: white;
            margin-top: 20px;
        }

        .users-table {
            width: 100%;
            min-width: 1200px; /* Minimum width to maintain proper layout */
            border-collapse: collapse;
            background: white;
            margin: 0; /* Remove margin since wrapper handles it */
        }

        .users-table th,
        .users-table td {
            padding: 20px 18px; /* Restored original padding */
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 20px; /* Restored original font size */
            white-space: nowrap; /* Prevent text wrapping */
        }

        /* Remove fixed column widths and text overflow constraints */
        .users-table th, .users-table td {
            /* Remove all width and overflow constraints */
        }

        .users-table th {
            background: linear-gradient(135deg, #f8f9ff 0%, #ffffff 100%);
            font-weight: 600;
            color: #2c3e50;
            text-transform: uppercase;
            font-size: 20px;
            letter-spacing: 0.5px;
        }

        .users-table tr:hover {
            background: linear-gradient(135deg, #f8f9ff 0%, #ffffff 100%);
        }

        .role-badge {
            padding: 12px 20px; /* Restored original padding */
            border-radius: 25px; /* Restored original border radius */
            font-size: 18px; /* Restored original font size */
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px; /* Restored original letter spacing */
            display: inline-block;
            text-align: center;
        }

        .role-super_admin {
            background: #e74c3c;
            color: white;
        }

        .role-manager {
            background: #f39c12;
            color: white;
        }

        .role-staff {
            background: #3498db;
            color: white;
        }

        .status-badge {
            padding: 12px 20px; /* Restored original padding */
            border-radius: 25px; /* Restored original border radius */
            font-size: 18px; /* Restored original font size */
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
            text-align: center;
        }

        .status-active {
            background: #27ae60;
            color: white;
        }

        .status-inactive {
            background: #95a5a6;
            color: white;
        }

        /* Updated Actions - Further increased horizontal spacing */
        .actions {
            display: flex;
            gap: 25px; /* Increased gap further */
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-start;
            min-width: 600px; /* Increased from 500px to match table better */
            width: 100%;
        }

        .actions .filter-select {
            min-width: 130px; /* Reduced for better table fit */
            height: 42px; /* Slightly reduced height */
            padding: 8px 12px; /* Reduced padding */
            font-size: 14px; /* Reduced font size */
        }

        /* Updated Pagination - Better horizontal spacing */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px; /* Increased gap from 10px */
            padding: 40px 30px; /* Increased padding */
            background: #f8f9fa;
            border-radius: 12px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .pagination button {
            padding: 12px 20px; /* Increased padding */
            border: 1px solid #ddd;
            background: white;
            cursor: pointer;
            border-radius: 5px;
            transition: all 0.3s;
            min-width: 60px; /* Added minimum width */
        }

        .pagination button:hover {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }

        .pagination button.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }

        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(5px);
            overflow-y: auto; /* Added vertical scroll */
            padding: 20px 0; /* Added padding for scroll spacing */
        }

        .modal-content {
            background: white;
            margin: 20px auto; /* Reduced top margin for scroll */
            padding: 40px;
            border-radius: 20px;
            width: 90%;
            max-width: 600px;
            position: relative;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-height: 90vh; /* Added max height */
            overflow-y: auto; /* Added vertical scroll for content */
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 25px; /* Increased margin */
            padding-bottom: 18px; /* Increased padding */
            border-bottom: 1px solid #eee;
        }

        .modal-header h2 {
            color: #2c3e50;
            margin: 0;
        }

        .close {
            position: absolute;
            right: 18px; /* Increased from 15px */
            top: 18px; /* Increased from 15px */
            font-size: 28px;
            cursor: pointer;
            color: #999;
            transition: color 0.3s;
        }

        .close:hover {
            color: #333;
        }

        .form-group {
            margin-bottom: 25px; /* Increased margin */
        }

        .form-group label {
            display: block;
            margin-bottom: 8px; /* Increased margin */
            font-weight: 600;
            color: #2c3e50;
            font-size: 20px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 18px 25px; /* Increased padding */
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 20px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3498db;
        }

        .loading {
            text-align: center;
            padding: 50px;
            color: #666;
        }

        .empty-state {
            text-align: center;
            padding: 50px;
            color: #666;
        }

        .empty-state i {
            font-size: 4em;
            margin-bottom: 20px;
            color: #bbb;
        }

        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 18px 25px; /* Increased padding */
            border-radius: 8px;
            color: white;
            font-weight: 600;
            z-index: 1100;
            display: none;
            min-width: 300px; /* Added minimum width */
        }

        .toast.success {
            background: #27ae60;
        }

        .toast.error {
            background: #e74c3c;
        }

        /* Stats Cards - Increased horizontal spacing */
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); /* Increased min width */
            gap: 35px; /* Increased gap from 25px */
            margin-bottom: 40px; /* Increased margin */
        }

        .stat-card {
            background: white;
            padding: 40px 30px; /* Increased vertical padding */
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
            margin-top: 15px; /* Increased margin */
            font-size: 20px;
            font-weight: 500;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .container {
                max-width: 100%;
                padding: 15px;
            }
            
            .formal-header-content {
                max-width: 100%;
            }
        }

        @media (max-width: 1024px) {
            .container {
                padding: 15px;
            }
            
            .formal-header {
                margin: -15px -15px 30px -15px;
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
                padding: 30px;
            }
            
            .controls {
                padding: 25px 30px;
            }
            
            .controls-row {
                gap: 25px;
            }

            .search-box {
                min-width: 100%;
                flex: 1;
            }
            
            .stats {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 25px;
            }
            
            .actions {
                min-width: 350px;
                gap: 15px;
            }
        }

        @media (max-width: 768px) {
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
            
            .content-wrapper {
                padding: 20px;
            }
            
            .controls {
                padding: 20px;
            }
            
            .controls-row {
                flex-direction: column;
                align-items: stretch;
                gap: 20px;
            }

            .search-box {
                min-width: 100%;
            }

            .users-table {
                font-size: 14px;
            }

            .users-table th,
            .users-table td {
                padding: 10px 5px;
            }

            .actions {
                flex-direction: column;
                min-width: 100%;
                gap: 10px;
            }

            .btn-small {
                padding: 8px 12px;
                font-size: 12px;
                min-width: 100px;
            }
            
            .stats {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .modal-content {
                padding: 25px;
                max-width: 95%;
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
                <h2 class="system-title">USER MANAGEMENT SYSTEM</h2>
                <p class="current-date-time" id="currentDateTime"></p>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="content-wrapper">

        <?php
        // Get user statistics
        $user_stats = [
            'total' => $pdo->query("SELECT COUNT(*) FROM admin_users")->fetchColumn(),
            'active' => $pdo->query("SELECT COUNT(*) FROM admin_users WHERE is_active = 1")->fetchColumn(),
            'super_admins' => $pdo->query("SELECT COUNT(*) FROM admin_users WHERE role = 'super_admin'")->fetchColumn(),
            'managers' => $pdo->query("SELECT COUNT(*) FROM admin_users WHERE role = 'manager'")->fetchColumn()
        ];
        ?>

        <!-- User Statistics -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?= $user_stats['total'] ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $user_stats['active'] ?></div>
                <div class="stat-label">Active Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $user_stats['super_admins'] ?></div>
                <div class="stat-label">Super Admins</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $user_stats['managers'] ?></div>
                <div class="stat-label">Managers</div>
            </div>
        </div>

        <div class="controls">
            <div class="controls-row">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search users by name or email...">
                </div>
                <select id="roleFilter" class="filter-select">
                    <option value="">All Roles</option>
                    <option value="super_admin">Super Admin</option>
                    <option value="manager">Manager</option>
                    <option value="staff">Staff</option>
                </select>
                <button class="btn btn-primary" onclick="openAddUserModal()">
                    <i class="fas fa-plus"></i> Add User
                </button>
            </div>
        </div>

        <div id="usersContainer">
            <div class="loading">
                <i class="fas fa-spinner fa-spin"></i> Loading users...
            </div>
        </div>

        <div id="pagination" class="pagination" style="display: none;"></div>

        </div>
    </div>

    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addUserModal')">&times;</span>
            <div class="modal-header">
                <h2>Add New User</h2>
            </div>
            <form id="addUserForm">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="role">Role:</label>
                    <select id="role" name="role" required>
                        <option value="staff">Staff</option>
                        <option value="manager">Manager</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add User
                </button>
            </form>
        </div>
    </div>

    <div id="toast" class="toast"></div>

    <script>
        let currentPage = 1;
        let isLoading = false;

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

        document.addEventListener('DOMContentLoaded', function() {
            updateDateTime();
            setInterval(updateDateTime, 1000);
            
            loadUsers();
            
            // Search functionality
            document.getElementById('searchInput').addEventListener('input', function() {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    currentPage = 1;
                    loadUsers();
                }, 500);
            });

            // Role filter
            document.getElementById('roleFilter').addEventListener('change', function() {
                currentPage = 1;
                loadUsers();
            });

            // Add user form
            document.getElementById('addUserForm').addEventListener('submit', function(e) {
                e.preventDefault();
                addUser();
            });

            // Add fade-in animation to cards
            const cards = document.querySelectorAll('.stat-card, .controls, .users-table');
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

        function loadUsers() {
            if (isLoading) return;
            isLoading = true;

            const search = document.getElementById('searchInput').value;
            const roleFilter = document.getElementById('roleFilter').value;

            const formData = new FormData();
            formData.append('action', 'get_users');
            formData.append('page', currentPage);
            formData.append('search', search);
            formData.append('role_filter', roleFilter);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayUsers(data.users);
                    displayPagination(data.page, data.total_pages);
                } else {
                    showToast('Error loading users: ' + data.message, 'error');
                }
            })
            .catch(error => {
                showToast('Error loading users: ' + error.message, 'error');
            })
            .finally(() => {
                isLoading = false;
            });
        }

        function displayUsers(users) {
            const container = document.getElementById('usersContainer');
            
            if (users.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h3>No users found</h3>
                        <p>Try adjusting your search criteria or add a new user.</p>
                    </div>
                `;
                return;
            }

            let html = `
                <div class="table-responsive">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            users.forEach(user => {
                const createdDate = new Date(user.created_at).toLocaleDateString();
                const statusClass = user.is_active == 1 ? 'status-active' : 'status-inactive';
                const statusText = user.is_active == 1 ? 'Active' : 'Inactive';
                
                html += `
                    <tr>
                        <td>${user.id}</td>
                        <td>${user.username}</td>
                        <td>${user.email}</td>
                        <td><span class="role-badge role-${user.role}">${user.role.replace('_', ' ')}</span></td>
                        <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                        <td>${createdDate}</td>
                        <td>
                            <div class="actions">
                                <button class="btn btn-warning btn-small" onclick="toggleUserStatus(${user.id}, ${user.is_active})">
                                    <i class="fas fa-${user.is_active == 1 ? 'ban' : 'check'}"></i>
                                    ${user.is_active == 1 ? 'Ban' : 'Activate'}
                                </button>
                                <select class="filter-select" onchange="updateUserRole(${user.id}, this.value)">
                                    <option value="staff" ${user.role === 'staff' ? 'selected' : ''}>Staff</option>
                                    <option value="manager" ${user.role === 'manager' ? 'selected' : ''}>Manager</option>
                                    <option value="super_admin" ${user.role === 'super_admin' ? 'selected' : ''}>Super Admin</option>
                                </select>
                                <button class="btn btn-danger btn-small" onclick="deleteUser(${user.id})">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });

            html += `
                        </tbody>
                    </table>
                </div>
            `;

            container.innerHTML = html;
        }

        function displayPagination(page, totalPages) {
            const container = document.getElementById('pagination');
            
            if (totalPages <= 1) {
                container.style.display = 'none';
                return;
            }

            container.style.display = 'flex';
            
            let html = `
                <button onclick="goToPage(1)" ${page === 1 ? 'disabled' : ''}>First</button>
                <button onclick="goToPage(${page - 1})" ${page === 1 ? 'disabled' : ''}>Previous</button>
            `;

            for (let i = Math.max(1, page - 2); i <= Math.min(totalPages, page + 2); i++) {
                html += `<button onclick="goToPage(${i})" ${i === page ? 'class="active"' : ''}>${i}</button>`;
            }

            html += `
                <button onclick="goToPage(${page + 1})" ${page === totalPages ? 'disabled' : ''}>Next</button>
                <button onclick="goToPage(${totalPages})" ${page === totalPages ? 'disabled' : ''}>Last</button>
            `;

            container.innerHTML = html;
        }

        function goToPage(page) {
            currentPage = page;
            loadUsers();
        }

        function toggleUserStatus(userId, currentStatus) {
            const newStatus = currentStatus == 1 ? 0 : 1;
            const action = newStatus == 1 ? 'activate' : 'ban';
            
            if (!confirm(`Are you sure you want to ${action} this user?`)) return;

            const formData = new FormData();
            formData.append('action', 'toggle_status');
            formData.append('user_id', userId);
            formData.append('status', newStatus);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    loadUsers();
                } else {
                    showToast('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                showToast('Error: ' + error.message, 'error');
            });
        }

        function updateUserRole(userId, newRole) {
            if (!confirm('Are you sure you want to change this user\'s role?')) return;

            const formData = new FormData();
            formData.append('action', 'update_role');
            formData.append('user_id', userId);
            formData.append('role', newRole);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    loadUsers();
                } else {
                    showToast('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                showToast('Error: ' + error.message, 'error');
            });
        }

        function deleteUser(userId) {
            if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) return;

            const formData = new FormData();
            formData.append('action', 'delete_user');
            formData.append('user_id', userId);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    loadUsers();
                } else {
                    showToast('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                showToast('Error: ' + error.message, 'error');
            });
        }

        function addUser() {
            const formData = new FormData();
            formData.append('action', 'add_user');
            formData.append('username', document.getElementById('username').value);
            formData.append('email', document.getElementById('email').value);
            formData.append('password', document.getElementById('password').value);
            formData.append('role', document.getElementById('role').value);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal('addUserModal');
                    document.getElementById('addUserForm').reset();
                    loadUsers();
                } else {
                    showToast('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                showToast('Error: ' + error.message, 'error');
            });
        }

        function openAddUserModal() {
            document.getElementById('addUserModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function showToast(message, type) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = `toast ${type}`;
            toast.style.display = 'block';

            setTimeout(() => {
                toast.style.display = 'none';
            }, 3000);
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let i = 0; i < modals.length; i++) {
                if (event.target === modals[i]) {
                    modals[i].style.display = 'none';
                }
            }
        }
    </script>
</body>
</html>