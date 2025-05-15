<?php
require_once '../config/db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$order_ids = $input['order_ids'] ?? [];

if (empty($order_ids)) {
    echo json_encode(['success' => false, 'message' => 'No order IDs provided']);
    exit;
}

try {
    // Get order statuses
    $placeholders = str_repeat('?,', count($order_ids) - 1) . '?';
    $sql = "SELECT id, status, payment_status 
            FROM orders 
            WHERE id IN ($placeholders) 
            AND user_id = ?";
    
    $params = array_merge($order_ids, [$_SESSION['user_id']]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'orders' => $orders
    ]);
} catch (PDOException $e) {
    error_log("Error fetching order statuses: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
} 