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
$order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$payment_method = $_POST['payment_method'] ?? '';
$reference_number = $_POST['reference_number'] ?? '';
$amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);

if (!$order_id || !$payment_method || !$reference_number || !$amount) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Verify order exists and belongs to user
    $stmt = $pdo->prepare("
        SELECT id, total_amount 
        FROM orders 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    $order = $stmt->fetch();

    if (!$order) {
        throw new Exception('Order not found or unauthorized');
    }

    // Insert payment record
    $stmt = $pdo->prepare("
        INSERT INTO payments (
            order_id, amount, payment_method, transaction_id, 
            status, payment_date
        ) VALUES (?, ?, ?, ?, 'completed', NOW())
    ");
    $stmt->execute([
        $order_id,
        $amount,
        $payment_method,
        $reference_number
    ]);

    // Update order payment status
    $stmt = $pdo->prepare("
        UPDATE orders 
        SET payment_status = 'paid'
        WHERE id = ?
    ");
    $stmt->execute([$order_id]);

    // Log payment update
    error_log("Payment Stored - Order ID: {$order_id}, Method: $payment_method, Reference: $reference_number");

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Payment information stored successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    error_log("Payment Storage Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error storing payment information: ' . $e->getMessage()
    ]);
} 