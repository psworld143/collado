<?php
require_once '../../config/db.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $coffin_id = filter_input(INPUT_POST, 'coffin_id', FILTER_VALIDATE_INT);

    if ($coffin_id) {
        try {
            // Check if coffin has any orders
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE coffin_id = ?");
            $stmt->execute([$coffin_id]);
            $order_count = $stmt->fetchColumn();

            if ($order_count > 0) {
                $_SESSION['error'] = "Cannot delete coffin with existing orders.";
                header("Location: manage_inventory.php");
                exit;
            }

            // Delete coffin
            $stmt = $pdo->prepare("DELETE FROM coffins WHERE id = ?");
            $stmt->execute([$coffin_id]);

            $_SESSION['success'] = "Coffin deleted successfully!";
        } catch (PDOException $e) {
            error_log("Delete coffin error: " . $e->getMessage());
            $_SESSION['error'] = "An error occurred while deleting the coffin.";
        }
    } else {
        $_SESSION['error'] = "Invalid coffin ID.";
    }
}

// Redirect back to inventory management
header("Location: manage_inventory.php");
exit; 