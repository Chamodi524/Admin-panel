<?php
$host = 'localhost';
$dbname = 'testbase';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die(json_encode([]));
}

$type = $_GET['type'] ?? '';
$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';

if(!$start || !$end) { echo json_encode([]); exit; }

switch($type) {
    case 'sales':
        // Example: sum total_amount per day
        $stmt = $pdo->prepare("SELECT DATE(created_at) as day, SUM(total_amount) as total 
            FROM orders 
            WHERE created_at BETWEEN ? AND ? GROUP BY DATE(created_at) ORDER BY day ASC");
        $stmt->execute([$start.' 00:00:00', $end.' 23:59:59']);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $labels = array_column($data, 'day');
        $values = array_column($data, 'total');
        echo json_encode(['labels'=>$labels,'values'=>$values]);
        break;

    case 'customers':
        $stmt = $pdo->prepare("SELECT user_id,name,email,created_at as registered_at 
            FROM customer 
            WHERE created_at BETWEEN ? AND ? ORDER BY created_at ASC");
        $stmt->execute([$start.' 00:00:00', $end.' 23:59:59']);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'admin_logs':
        $stmt = $pdo->prepare("SELECT * FROM admin_logs 
            WHERE created_at BETWEEN ? AND ? ORDER BY created_at ASC");
        $stmt->execute([$start.' 00:00:00', $end.' 23:59:59']);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    default:
        echo json_encode([]);
        break;
}
