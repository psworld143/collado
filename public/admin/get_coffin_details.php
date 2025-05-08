<?php
require_once '../../config/db.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get coffin ID from URL
$coffin_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$coffin_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid coffin ID']);
    exit;
}

try {
    // Fetch coffin details
    $stmt = $pdo->prepare("
        SELECT c.*, 
               COUNT(DISTINCT oi.order_id) as order_count,
               SUM(oi.quantity) as total_ordered
        FROM coffins c
        LEFT JOIN order_items oi ON c.id = oi.coffin_id
        WHERE c.id = ?
        GROUP BY c.id
    ");
    $stmt->execute([$coffin_id]);
    $coffin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coffin) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Coffin not found']);
        exit;
    }

    // Return the data
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'coffin' => $coffin
    ]);

} catch (PDOException $e) {
    error_log("Error fetching coffin details: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
} 