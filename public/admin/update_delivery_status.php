<?php
require_once '../../config/db.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if required fields are present
if (!isset($_POST['order_id']) || !isset($_POST['delivery_status']) || !isset($_POST['delivery_date'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$delivery_status = htmlspecialchars($_POST['delivery_status'], ENT_QUOTES, 'UTF-8');
$delivery_date = htmlspecialchars($_POST['delivery_date'], ENT_QUOTES, 'UTF-8');
$notes = isset($_POST['notes']) ? htmlspecialchars($_POST['notes'], ENT_QUOTES, 'UTF-8') : '';

try {
    // Start transaction
    $pdo->beginTransaction();

    // Update order delivery status
    $stmt = $pdo->prepare("
        UPDATE orders 
        SET delivery_status = ?, 
            delivery_date = ?,
            notes = CASE 
                WHEN notes IS NULL OR notes = '' THEN ? 
                ELSE CONCAT(notes, '\n', ?) 
            END,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([
        $delivery_status,
        $delivery_date,
        $notes,
        $notes,
        $order_id
    ]);

    // Commit transaction
    $pdo->commit();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Delivery status updated successfully']);
} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    error_log("Delivery Status Update Error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    error_log("Delivery Status Update Error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred']);
} 