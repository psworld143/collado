<?php
// Prevent any output before headers
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../../config/db.php';
session_start();

// Set JSON header
header('Content-Type: application/json');

// Function to send JSON response
function sendJsonResponse($success, $message) {
    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);
    exit;
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    sendJsonResponse(false, 'Unauthorized access');
}

// Get JSON data from request
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate JSON data
if (json_last_error() !== JSON_ERROR_NONE) {
    sendJsonResponse(false, 'Invalid JSON data');
}

// Validate input
if (!isset($data['order_id']) || !isset($data['status'])) {
    sendJsonResponse(false, 'Missing required parameters');
}

$order_id = filter_var($data['order_id'], FILTER_VALIDATE_INT);
$status = filter_var($data['status'], FILTER_SANITIZE_STRING);

if (!$order_id || !in_array($status, ['paid', 'cancelled'])) {
    sendJsonResponse(false, 'Invalid parameters');
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Update order status
    $stmt = $pdo->prepare("
        UPDATE orders 
        SET payment_status = ?, 
            updated_at = CURRENT_TIMESTAMP 
        WHERE id = ? AND payment_status = 'pending'
    ");
    $stmt->execute([$status, $order_id]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Order not found or status cannot be updated');
    }

    // Commit transaction
    $pdo->commit();

    // Return success response
    sendJsonResponse(true, 'Order status updated successfully');

} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error updating order status: " . $e->getMessage());
    sendJsonResponse(false, 'Failed to update order status');
} 