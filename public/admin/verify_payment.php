<?php
require_once '../../config/db.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if required data is provided
if (!isset($_POST['order_id']) || !isset($_POST['action'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

$order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$action = $_POST['action'];
$payment_id = filter_input(INPUT_POST, 'payment_id', FILTER_VALIDATE_INT);

try {
    $pdo->beginTransaction();

    if ($action === 'verify') {
        // First update the payments table
        $stmt = $pdo->prepare("
            UPDATE payments 
            SET status = 'completed', 
                verification_notes = ?,
                verified_at = NOW()
            WHERE id = ? AND order_id = ?
        ");
        $stmt->execute([
            $_POST['verification_notes'] ?? null,
            $payment_id,
            $order_id
        ]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Payment record not found');
        }

        // Then update the orders table
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET payment_status = 'paid'
            WHERE id = ?
        ");
        $stmt->execute([$order_id]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Order record not found');
        }

        $message = 'Payment has been verified successfully';
    } else if ($action === 'reject') {
        if (empty($_POST['rejection_reason'])) {
            throw new Exception('Rejection reason is required');
        }

        // First update the payments table
        $stmt = $pdo->prepare("
            UPDATE payments 
            SET status = 'rejected',
                notes = ?
            WHERE id = ? AND order_id = ?
        ");
        $stmt->execute([
            $_POST['rejection_reason'],
            $payment_id,
            $order_id
        ]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Payment record not found');
        }

        // Then update the orders table
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET payment_status = 'rejected'
            WHERE id = ?
        ");
        $stmt->execute([$order_id]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Order record not found');
        }

        $message = 'Payment has been rejected';
    } else {
        throw new Exception('Invalid action');
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => $message]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Payment verification error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 