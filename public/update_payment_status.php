<?php
require_once '../config/db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get and validate input
$payment_method = $_POST['payment_method'] ?? '';
$reference_number = $_POST['reference_number'] ?? '';
$coffin_id = filter_input(INPUT_POST, 'coffin_id', FILTER_VALIDATE_INT);
$amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);

if (!$payment_method || !$reference_number || !$coffin_id || !$amount) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Get the latest order for this user and coffin
    $stmt = $pdo->prepare("
        SELECT id, total_amount 
        FROM orders 
        WHERE user_id = ? 
        AND coffin_id = ? 
        AND payment_status = 'pending'
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id'], $coffin_id]);
    $order = $stmt->fetch();

    if (!$order) {
        throw new Exception('No pending order found');
    }

    // Insert payment record
    $stmt = $pdo->prepare("
        INSERT INTO payments (
            order_id, amount, payment_method, transaction_id, 
            status, payment_date
        ) VALUES (?, ?, ?, ?, 'completed', NOW())
    ");
    $stmt->execute([
        $order['id'],
        $order['total_amount'],
        $payment_method,
        $reference_number
    ]);

    // Update order payment status
    $stmt = $pdo->prepare("
        UPDATE orders 
        SET payment_status = 'paid'
        WHERE id = ?
    ");
    $stmt->execute([$order['id']]);

    // Log payment update
    error_log("Payment Update - Order ID: {$order['id']}, Method: $payment_method, Reference: $reference_number");

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Payment status updated successfully',
        'order_id' => $order['id']
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    error_log("Payment Update Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error updating payment status: ' . $e->getMessage()
    ]);
} 