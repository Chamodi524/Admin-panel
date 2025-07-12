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
    <title>User Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .header p {
            font-size: 1.1em;
            opacity: 0.9;
        }

        .controls {
            padding: 30px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }

        .controls-row {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: #3498db;
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }

        .filter-select {
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            background: white;
            cursor: pointer;
            transition: border-color 0.3s;
        }

        .filter-select:focus {
            outline: none;
            border-color: #3498db;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #2980b9, #1f5582);
            transform: translateY(-2px);
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
            padding: 6px 12px;
            font-size: 14px;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .users-table th,
        .users-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .users-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
            text-transform: uppercase;
            font-size: 14px;
            letter-spacing: 0.5px;
        }

        .users-table tr:hover {
            background: #f8f9fa;
        }

        .role-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background: #27ae60;
            color: white;
        }

        .status-inactive {
            background: #95a5a6;
            color: white;
        }

        .actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            padding: 30px;
            background: #f8f9fa;
        }

        .pagination button {
            padding: 8px 16px;
            border: 1px solid #ddd;
            background: white;
            cursor: pointer;
            border-radius: 5px;
            transition: all 0.3s;
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
            background: rgba(0,0,0,0.5);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .modal-header h2 {
            color: #2c3e50;
            margin: 0;
        }

        .close {
            position: absolute;
            right: 15px;
            top: 15px;
            font-size: 28px;
            cursor: pointer;
            color: #999;
            transition: color 0.3s;
        }

        .close:hover {
            color: #333;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #2c3e50;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
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
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            z-index: 1100;
            display: none;
        }

        .toast.success {
            background: #27ae60;
        }

        .toast.error {
            background: #e74c3c;
        }

        @media (max-width: 768px) {
            .controls-row {
                flex-direction: column;
                align-items: stretch;
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
            }

            .btn-small {
                padding: 8px 12px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-users"></i> User Management System</h1>
            <p>Manage your admin users, roles, and permissions</p>
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

        document.addEventListener('DOMContentLoaded', function() {
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