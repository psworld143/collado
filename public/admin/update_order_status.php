<?php
// Prevent any output before headers
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../../config/db.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if required parameters are present
if (!isset($_POST['order_id']) || !isset($_POST['status'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$status = htmlspecialchars($_POST['status'], ENT_QUOTES, 'UTF-8');

// Validate status
$valid_statuses = ['pending', 'paid', 'cancelled'];
if (!in_array($status, $valid_statuses)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Update order status
    $stmt = $pdo->prepare("
        UPDATE orders 
        SET payment_status = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([$status, $order_id]);

    // If order is cancelled, restore stock quantity
    if ($status === 'cancelled') {
        $stmt = $pdo->prepare("
            UPDATE coffins c
            JOIN orders o ON c.id = o.coffin_id
            SET c.stock_quantity = c.stock_quantity + o.quantity
            WHERE o.id = ?
        ");
        $stmt->execute([$order_id]);
    }

    // Commit transaction
    $pdo->commit();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Order status updated successfully']);
} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    error_log("Order Status Update Error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    error_log("Order Status Update Error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred']);
} 