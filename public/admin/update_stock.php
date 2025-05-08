<?php
// Prevent PHP errors from being displayed
error_reporting(0);
ini_set('display_errors', 0);

require_once '../../config/db.php';
session_start();

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get and validate input
$coffin_id = filter_input(INPUT_POST, 'coffin_id', FILTER_VALIDATE_INT);
$adjustment = filter_input(INPUT_POST, 'adjustment', FILTER_VALIDATE_INT);
$notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);

if (!$coffin_id || $adjustment === null) {
    echo json_encode(['success' => false, 'message' => 'Invalid input parameters']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Get current stock
    $stmt = $pdo->prepare("SELECT stock_quantity, name FROM coffins WHERE id = ?");
    $stmt->execute([$coffin_id]);
    $coffin = $stmt->fetch();

    if (!$coffin) {
        throw new Exception('Coffin not found');
    }

    // Calculate new stock quantity
    $new_quantity = $coffin['stock_quantity'] + $adjustment;

    // Validate new quantity
    if ($new_quantity < 0) {
        throw new Exception('Stock cannot go below 0');
    }

    // Update stock
    $stmt = $pdo->prepare("UPDATE coffins SET stock_quantity = ? WHERE id = ?");
    $stmt->execute([$new_quantity, $coffin_id]);

    // Log the stock adjustment
    $stmt = $pdo->prepare("INSERT INTO stock_adjustments (coffin_id, adjustment, previous_quantity, new_quantity, notes, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $coffin_id,
        $adjustment,
        $coffin['stock_quantity'],
        $new_quantity,
        $notes,
        $_SESSION['user_id']
    ]);

    // Commit transaction
    $pdo->commit();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Stock updated successfully',
        'data' => [
            'coffin_name' => $coffin['name'],
            'previous_quantity' => $coffin['stock_quantity'],
            'adjustment' => $adjustment,
            'new_quantity' => $new_quantity
        ]
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    // Handle database errors
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
} 