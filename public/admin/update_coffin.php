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
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_STRING);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $low_stock_threshold = filter_input(INPUT_POST, 'low_stock_threshold', FILTER_VALIDATE_INT);

    if ($coffin_id && $name && $category && $price !== false && $low_stock_threshold !== false) {
        try {
            // Update coffin
            $stmt = $pdo->prepare("
                UPDATE coffins 
                SET name = ?,
                    description = ?,
                    category = ?,
                    price = ?,
                    low_stock_threshold = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$name, $description, $category, $price, $low_stock_threshold, $coffin_id]);

            $_SESSION['success'] = "Coffin updated successfully!";
        } catch (PDOException $e) {
            error_log("Update coffin error: " . $e->getMessage());
            $_SESSION['error'] = "An error occurred while updating the coffin.";
        }
    } else {
        $_SESSION['error'] = "Invalid input data.";
    }
}

// Redirect back to inventory management
header("Location: manage_inventory.php");
exit; 