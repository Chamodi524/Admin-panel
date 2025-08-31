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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Reports</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f9f9f9; }
h2 { color: #2c3e50; }
.report-section { background: #fff; padding: 20px; margin-bottom: 30px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
.filter-container { margin-bottom: 15px; }
input[type="date"] { padding: 5px 10px; margin-right: 10px; }
button { padding: 5px 15px; background: #2980b9; color: #fff; border: none; border-radius: 5px; cursor: pointer; }
button:hover { background: #1c5980; }
table { width: 100%; border-collapse: collapse; display: none; margin-top: 10px; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background: #2980b9; color: #fff; }
</style>
</head>
<body>

<h1>Dashboard Reports</h1>

<!-- Sales Report -->
<div class="report-section">
    <h2>Sales Report</h2>
    <div class="filter-container">
        <input type="date" id="salesStart"> 
        <input type="date" id="salesEnd"> 
        <button onclick="loadSales()">Show Report</button>
    </div>
    <canvas id="salesChart" height="100" style="display:none;"></canvas>
</div>

<!-- Customer Report -->
<div class="report-section">
    <h2>Customer Report</h2>
    <div class="filter-container">
        <input type="date" id="customerStart"> 
        <input type="date" id="customerEnd"> 
        <button onclick="loadCustomers()">Show Report</button>
    </div>
    <table id="customerTable">
        <thead>
            <tr><th>ID</th><th>Name</th><th>Email</th><th>Registered At</th></tr>
        </thead>
        <tbody></tbody>
    </table>
</div>

<!-- Admin Logs -->
<div class="report-section">
    <h2>Admin Activity Log</h2>
    <div class="filter-container">
        <input type="date" id="logStart"> 
        <input type="date" id="logEnd"> 
        <button onclick="loadAdminLogs()">Show Report</button>
    </div>
    <table id="adminLogTable">
        <thead>
            <tr><th>ID</th><th>Admin ID</th><th>Action</th><th>Details</th><th>Date</th></tr>
        </thead>
        <tbody></tbody>
    </table>
</div>

<script>
function loadSales() {
    const start = document.getElementById('salesStart').value;
    const end = document.getElementById('salesEnd').value;
    if(!start || !end) { alert("Select both start and end dates"); return; }

    fetch(`report_api.php?type=sales&start=${start}&end=${end}`)
    .then(res => res.json())
    .then(data => {
        const ctx = document.getElementById('salesChart').getContext('2d');
        document.getElementById('salesChart').style.display = 'block';
        if(window.salesChart) window.salesChart.destroy();
        window.salesChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Revenue',
                    data: data.values,
                    backgroundColor: 'rgba(41, 128, 185, 0.7)'
                }]
            },
            options: { responsive:true, scales:{ y:{ beginAtZero:true } } }
        });
    });
}

function loadCustomers() {
    const start = document.getElementById('customerStart').value;
    const end = document.getElementById('customerEnd').value;
    if(!start || !end) { alert("Select both dates"); return; }

    fetch(`report_api.php?type=customers&start=${start}&end=${end}`)
    .then(res => res.json())
    .then(data => {
        const tbody = document.querySelector('#customerTable tbody');
        tbody.innerHTML = '';
        if(data.length > 0) document.getElementById('customerTable').style.display='table';
        else document.getElementById('customerTable').style.display='none';
        data.forEach(c => {
            tbody.innerHTML += `<tr>
                <td>${c.user_id}</td>
                <td>${c.name}</td>
                <td>${c.email}</td>
                <td>${c.registered_at}</td>
            </tr>`;
        });
    });
}

function loadAdminLogs() {
    const start = document.getElementById('logStart').value;
    const end = document.getElementById('logEnd').value;
    if(!start || !end) { alert("Select both dates"); return; }

    fetch(`report_api.php?type=admin_logs&start=${start}&end=${end}`)
    .then(res => res.json())
    .then(data => {
        const tbody = document.querySelector('#adminLogTable tbody');
        tbody.innerHTML = '';
        if(data.length > 0) document.getElementById('adminLogTable').style.display='table';
        else document.getElementById('adminLogTable').style.display='none';
        data.forEach(l => {
            tbody.innerHTML += `<tr>
                <td>${l.id}</td>
                <td>${l.admin_id}</td>
                <td>${l.action}</td>
                <td>${l.details}</td>
                <td>${l.created_at}</td>
            </tr>`;
        });
    });
}
</script>

</body>
</html>
