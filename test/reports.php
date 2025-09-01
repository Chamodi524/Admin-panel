<?php
// Database connection
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

// Handle API requests
if (isset($_GET['type'])) {
    header('Content-Type: application/json');
    
    $start = $_GET['start'] ?? '';
    $end = $_GET['end'] ?? '';
    
    try {
        switch ($_GET['type']) {
            case 'sales':
                // Sales data query
                $stmt = $pdo->prepare("
                    SELECT DATE(created_at) as date, SUM(amount) as total 
                    FROM sales 
                    WHERE created_at BETWEEN :start AND DATE_ADD(:end, INTERVAL 1 DAY)
                    GROUP BY DATE(created_at)
                    ORDER BY date
                ");
                $stmt->execute([':start' => $start, ':end' => $end]);
                $salesData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $labels = [];
                $values = [];
                foreach ($salesData as $row) {
                    $labels[] = $row['date'];
                    $values[] = (float)$row['total'];
                }
                
                echo json_encode(['labels' => $labels, 'values' => $values]);
                break;
                
            case 'customers':
                // Customer data query
                $stmt = $pdo->prepare("
                    SELECT user_id, name, email, registered_at 
                    FROM customers 
                    WHERE registered_at BETWEEN :start AND DATE_ADD(:end, INTERVAL 1 DAY)
                    ORDER BY registered_at
                ");
                $stmt->execute([':start' => $start, ':end' => $end]);
                $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode($customers);
                break;
                
            case 'admin_logs':
                // Admin logs query
                $stmt = $pdo->prepare("
                    SELECT id, admin_id, action, details, created_at 
                    FROM admin_logs 
                    WHERE created_at BETWEEN :start AND DATE_ADD(:end, INTERVAL 1 DAY)
                    ORDER BY created_at DESC
                ");
                $stmt->execute([':start' => $start, ':end' => $end]);
                $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode($logs);
                break;
                
            default:
                echo json_encode(['error' => 'Invalid report type']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Reports</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --info: #4895ef;
            --warning: #f72585;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --bg-color: #f0f2f5;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: var(--bg-color);
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background: linear-gradient(120deg, var(--primary), var(--secondary));
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: var(--card-shadow);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        h1 {
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .subtitle {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .current-date {
            background: rgba(255, 255, 255, 0.15);
            padding: 10px 15px;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-header {
            background: linear-gradient(120deg, var(--info), var(--success));
            color: white;
            padding: 18px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h2 {
            font-size: 20px;
            font-weight: 600;
        }
        
        .card-icon {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .filter-container {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
            align-items: center;
        }
        
        input[type="date"] {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            flex: 1;
            min-width: 140px;
        }
        
        button {
            padding: 10px 18px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        button:hover {
            background: var(--secondary);
        }
        
        .chart-container {
            position: relative;
            height: 250px;
            width: 100%;
            margin-top: 15px;
        }
        
        .table-container {
            overflow-x: auto;
            margin-top: 15px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            display: none;
            margin-top: 10px;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        th {
            background-color: var(--primary);
            color: white;
            font-weight: 600;
        }
        
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        tr:hover {
            background-color: #f1f1f1;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        
        .sales-icon { background: var(--success); }
        .customers-icon { background: var(--info); }
        .admin-icon { background: var(--warning); }
        
        .stat-info h3 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .stat-info p {
            color: var(--gray);
            font-size: 14px;
        }
        
        .loading {
            text-align: center;
            padding: 20px;
            color: var(--gray);
            display: none;
        }
        
        .error {
            color: #e74c3c;
            padding: 10px;
            background: #ffeaea;
            border-radius: 6px;
            margin-top: 10px;
            display: none;
        }
        
        .no-data {
            text-align: center;
            padding: 20px;
            color: var(--gray);
            display: none;
        }
        
        .export-btn {
            background: var(--success);
            margin-top: 15px;
            width: 100%;
            justify-content: center;
        }
        
        .export-btn:hover {
            background: #3aa5d3;
        }
        
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-container {
                flex-direction: column;
                align-items: stretch;
            }
            
            .header-content {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-content">
                <div>
                    <h1>Business Intelligence Dashboard</h1>
                    <p class="subtitle">Comprehensive overview of your business performance</p>
                </div>
                <div class="current-date" id="currentDate"></div>
            </div>
        </header>
        
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon sales-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-info">
                    <h3 id="totalSales">$12,489</h3>
                    <p>Total Sales</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon customers-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3 id="totalCustomers">1,248</h3>
                    <p>Total Customers</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon admin-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="stat-info">
                    <h3 id="totalActivities">384</h3>
                    <p>Admin Activities</p>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <!-- Sales Report -->
            <div class="card">
                <div class="card-header">
                    <h2>Sales Report</h2>
                    <div class="card-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
                <div class="card-body">
                    <div class="filter-container">
                        <input type="date" id="salesStart"> 
                        <input type="date" id="salesEnd"> 
                        <button onclick="loadSales()">
                            <i class="fas fa-chart-bar"></i> Generate Report
                        </button>
                    </div>
                    <div class="error" id="salesError"></div>
                    <div class="loading" id="salesLoading">Loading sales data...</div>
                    <div class="no-data" id="salesNoData">No sales data available for the selected period</div>
                    <div class="chart-container">
                        <canvas id="salesChart"></canvas>
                    </div>
                    <button class="export-btn" onclick="exportSalesData()">
                        <i class="fas fa-download"></i> Export as CSV
                    </button>
                </div>
            </div>

            <!-- Customer Report -->
            <div class="card">
                <div class="card-header">
                    <h2>Customer Report</h2>
                    <div class="card-icon">
                        <i class="fas fa-user-friends"></i>
                    </div>
                </div>
                <div class="card-body">
                    <div class="filter-container">
                        <input type="date" id="customerStart"> 
                        <input type="date" id="customerEnd"> 
                        <button onclick="loadCustomers()">
                            <i class="fas fa-search"></i> Generate Report
                        </button>
                    </div>
                    <div class="error" id="customerError"></div>
                    <div class="loading" id="customerLoading">Loading customer data...</div>
                    <div class="no-data" id="customerNoData">No customer data available for the selected period</div>
                    <div class="table-container">
                        <table id="customerTable">
                            <thead>
                                <tr><th>ID</th><th>Name</th><th>Email</th><th>Registered At</th></tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <button class="export-btn" onclick="exportCustomerData()">
                        <i class="fas fa-download"></i> Export as CSV
                    </button>
                </div>
            </div>

            <!-- Admin Logs -->
            <div class="card">
                <div class="card-header">
                    <h2>Admin Activity Log</h2>
                    <div class="card-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                </div>
                <div class="card-body">
                    <div class="filter-container">
                        <input type="date" id="logStart"> 
                        <input type="date" id="logEnd"> 
                        <button onclick="loadAdminLogs()">
                            <i class="fas fa-search"></i> Generate Report
                        </button>
                    </div>
                    <div class="error" id="logError"></div>
                    <div class="loading" id="logLoading">Loading admin logs...</div>
                    <div class="no-data" id="logNoData">No admin logs available for the selected period</div>
                    <div class="table-container">
                        <table id="adminLogTable">
                            <thead>
                                <tr><th>ID</th><th>Admin ID</th><th>Action</th><th>Details</th><th>Date</th></tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <button class="export-btn" onclick="exportLogData()">
                        <i class="fas fa-download"></i> Export as CSV
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Set current date display
        document.getElementById('currentDate').textContent = new Date().toLocaleDateString('en-US', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });

        // Set default date ranges (last 30 days)
        const today = new Date();
        const thirtyDaysAgo = new Date();
        thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);

        document.getElementById('salesStart').valueAsDate = thirtyDaysAgo;
        document.getElementById('salesEnd').valueAsDate = today;
        document.getElementById('customerStart').valueAsDate = thirtyDaysAgo;
        document.getElementById('customerEnd').valueAsDate = today;
        document.getElementById('logStart').valueAsDate = thirtyDaysAgo;
        document.getElementById('logEnd').valueAsDate = today;

        // Initialize charts
        let salesChart = null;

        function showLoader(type) {
            document.getElementById(`${type}Loading`).style.display = 'block';
            document.getElementById(`${type}Error`).style.display = 'none';
            document.getElementById(`${type}NoData`).style.display = 'none';
            
            if (type === 'sales') {
                document.getElementById('salesChart').style.display = 'none';
            } else {
                document.getElementById(`${type}Table`).style.display = 'none';
            }
        }

        function hideLoader(type) {
            document.getElementById(`${type}Loading`).style.display = 'none';
        }

        function showError(type, message) {
            document.getElementById(`${type}Error`).textContent = message;
            document.getElementById(`${type}Error`).style.display = 'block';
            document.getElementById(`${type}NoData`).style.display = 'none';
            
            if (type === 'sales') {
                document.getElementById('salesChart').style.display = 'none';
            } else {
                document.getElementById(`${type}Table`).style.display = 'none';
            }
        }

        function showNoData(type) {
            document.getElementById(`${type}NoData`).style.display = 'block';
            document.getElementById(`${type}Error`).style.display = 'none';
            
            if (type === 'sales') {
                document.getElementById('salesChart').style.display = 'none';
            } else {
                document.getElementById(`${type}Table`).style.display = 'none';
            }
        }

        function loadSales() {
            const start = document.getElementById('salesStart').value;
            const end = document.getElementById('salesEnd').value;
            
            if(!start || !end) { 
                showError('sales', "Please select both start and end dates");
                return; 
            }
            
            if (new Date(start) > new Date(end)) {
                showError('sales', "Start date cannot be after end date");
                return;
            }
            
            showLoader('sales');
            
            // Simulate API call with timeout
            setTimeout(() => {
                hideLoader('sales');
                
                // Sample data for demonstration
                const sampleData = {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    values: [12500, 19000, 18000, 22000, 21000, 25000, 30000, 32000, 29000, 33000, 35000, 38000]
                };
                
                if (sampleData.labels.length === 0) {
                    showNoData('sales');
                    return;
                }
                
                const ctx = document.getElementById('salesChart').getContext('2d');
                document.getElementById('salesChart').style.display = 'block';
                
                if (salesChart) salesChart.destroy();
                
                salesChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: sampleData.labels,
                        datasets: [{
                            label: 'Revenue ($)',
                            data: sampleData.values,
                            backgroundColor: 'rgba(67, 97, 238, 0.7)',
                            borderColor: 'rgba(67, 97, 238, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: { 
                        responsive: true, 
                        maintainAspectRatio: false,
                        scales: { 
                            y: { 
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '$' + value.toLocaleString();
                                    }
                                }
                            } 
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return `Revenue: $${context.raw.toLocaleString()}`;
                                    }
                                }
                            }
                        }
                    }
                });
            }, 1500);
        }

        function loadCustomers() {
            const start = document.getElementById('customerStart').value;
            const end = document.getElementById('customerEnd').value;
            
            if(!start || !end) { 
                showError('customer', "Please select both start and end dates");
                return; 
            }
            
            if (new Date(start) > new Date(end)) {
                showError('customer', "Start date cannot be after end date");
                return;
            }
            
            showLoader('customer');
            
            // Simulate API call with timeout
            setTimeout(() => {
                hideLoader('customer');
                
                // Sample data for demonstration
                const sampleData = [
                    {user_id: 1001, name: 'John Smith', email: 'john@example.com', registered_at: '2023-01-15'},
                    {user_id: 1002, name: 'Emma Johnson', email: 'emma@example.com', registered_at: '2023-02-22'},
                    {user_id: 1003, name: 'Michael Brown', email: 'michael@example.com', registered_at: '2023-03-10'},
                    {user_id: 1004, name: 'Sarah Davis', email: 'sarah@example.com', registered_at: '2023-04-05'},
                    {user_id: 1005, name: 'David Wilson', email: 'david@example.com', registered_at: '2023-05-18'},
                ];
                
                const tbody = document.querySelector('#customerTable tbody');
                tbody.innerHTML = '';
                
                if (sampleData.length > 0) {
                    document.getElementById('customerTable').style.display = 'table';
                    document.getElementById('customerNoData').style.display = 'none';
                    document.getElementById('customerError').style.display = 'none';
                    
                    sampleData.forEach(c => {
                        tbody.innerHTML += `<tr>
                            <td>${c.user_id}</td>
                            <td>${c.name}</td>
                            <td>${c.email}</td>
                            <td>${new Date(c.registered_at).toLocaleDateString()}</td>
                        </tr>`;
                    });
                } else {
                    showNoData('customer');
                }
            }, 1500);
        }

        function loadAdminLogs() {
            const start = document.getElementById('logStart').value;
            const end = document.getElementById('logEnd').value;
            
            if(!start || !end) { 
                showError('log', "Please select both start and end dates");
                return; 
            }
            
            if (new Date(start) > new Date(end)) {
                showError('log', "Start date cannot be after end date");
                return;
            }
            
            showLoader('log');
            
            // Simulate API call with timeout
            setTimeout(() => {
                hideLoader('log');
                
                // Sample data for demonstration
                const sampleData = [
                    {id: 1, admin_id: 101, action: 'Login', details: 'User logged in', created_at: '2023-06-01 09:15:32'},
                    {id: 2, admin_id: 102, action: 'Update', details: 'Updated product details', created_at: '2023-06-02 14:22:18'},
                    {id: 3, admin_id: 101, action: 'Create', details: 'Created new user account', created_at: '2023-06-03 11:05:47'},
                    {id: 4, admin_id: 103, action: 'Delete', details: 'Removed outdated entry', created_at: '2023-06-04 16:30:22'},
                    {id: 5, admin_id: 102, action: 'Export', details: 'Exported sales report', created_at: '2023-06-05 10:11:05'},
                ];
                
                const tbody = document.querySelector('#adminLogTable tbody');
                tbody.innerHTML = '';
                
                if (sampleData.length > 0) {
                    document.getElementById('adminLogTable').style.display = 'table';
                    document.getElementById('logNoData').style.display = 'none';
                    document.getElementById('logError').style.display = 'none';
                    
                    sampleData.forEach(l => {
                        tbody.innerHTML += `<tr>
                            <td>${l.id}</td>
                            <td>${l.admin_id}</td>
                            <td>${l.action}</td>
                            <td>${l.details}</td>
                            <td>${new Date(l.created_at).toLocaleString()}</td>
                        </tr>`;
                    });
                } else {
                    showNoData('log');
                }
            }, 1500);
        }

        // Export functions
        function exportSalesData() {
            if (!salesChart) {
                alert('No sales data to export');
                return;
            }
            
            const labels = salesChart.data.labels;
            const values = salesChart.data.datasets[0].data;
            
            let csvContent = "data:text/csv;charset=utf-8,Date,Revenue\n";
            
            labels.forEach((label, i) => {
                csvContent += `${label},${values[i]}\n`;
            });
            
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "sales_report.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function exportCustomerData() {
            const table = document.getElementById('customerTable');
            if (table.style.display === 'none') {
                alert('No customer data to export');
                return;
            }
            
            let csvContent = "data:text/csv;charset=utf-8,ID,Name,Email,Registered At\n";
            
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const cols = row.querySelectorAll('td');
                const rowData = Array.from(cols).map(col => `"${col.textContent}"`).join(',');
                csvContent += rowData + '\n';
            });
            
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "customer_report.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function exportLogData() {
            const table = document.getElementById('adminLogTable');
            if (table.style.display === 'none') {
                alert('No admin log data to export');
                return;
            }
            
            let csvContent = "data:text/csv;charset=utf-8,ID,Admin ID,Action,Details,Date\n";
            
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const cols = row.querySelectorAll('td');
                const rowData = Array.from(cols).map(col => `"${col.textContent}"`).join(',');
                csvContent += rowData + '\n';
            });
            
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "admin_logs_report.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Load initial data
        window.addEventListener('load', function() {
            loadSales();
            loadCustomers();
            loadAdminLogs();
        });
    </script>
</body>
</html>