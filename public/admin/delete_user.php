<?php
require_once '../../config/db.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

    if ($user_id) {
        try {
            // Start transaction
            $pdo->beginTransaction();

            // Check if user has any orders
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $order_count = $stmt->fetchColumn();

            if ($order_count > 0) {
                $_SESSION['error'] = "Cannot delete user with existing orders.";
                header("Location: manage_users.php");
                exit;
            }

            // Delete user
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);

            $pdo->commit();
            $_SESSION['success'] = "User deleted successfully!";
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Delete user error: " . $e->getMessage());
            $_SESSION['error'] = "An error occurred while deleting the user.";
        }
    } else {
        $_SESSION['error'] = "Invalid user ID.";
    }
}

// Redirect back to user management
header("Location: manage_users.php");
exit; 