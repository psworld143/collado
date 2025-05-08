<?php
require_once '../../config/db.php';
session_start();

// Set header to JSON
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if coffin_id is provided
if (!isset($_POST['coffin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Coffin ID is required']);
    exit;
}

$coffin_id = filter_input(INPUT_POST, 'coffin_id', FILTER_VALIDATE_INT);
if (!$coffin_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid coffin ID']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Check if coffin exists and get its image path
    $stmt = $pdo->prepare("SELECT image FROM coffins WHERE id = ?");
    $stmt->execute([$coffin_id]);
    $coffin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coffin) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Coffin not found']);
        exit;
    }

    // Delete order items first (due to foreign key constraint)
    $stmt = $pdo->prepare("DELETE FROM order_items WHERE coffin_id = ?");
    $stmt->execute([$coffin_id]);

    // Delete the coffin
    $stmt = $pdo->prepare("DELETE FROM coffins WHERE id = ?");
    $stmt->execute([$coffin_id]);

    // If there was an image, delete it
    if (!empty($coffin['image'])) {
        $image_path = '../../' . $coffin['image'];
        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }

    // Commit transaction
    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Coffin deleted successfully']);

} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    error_log("Error deleting coffin: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    exit;
} 