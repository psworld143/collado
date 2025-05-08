<?php
require_once '../../config/db.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

    if ($order_id && in_array($status, ['paid', 'cancelled'])) {
        try {
            // Start transaction
            $pdo->beginTransaction();

            // Update order status
            $stmt = $pdo->prepare("
                UPDATE orders 
                SET payment_status = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$status, $order_id]);

            // If order is cancelled, return items to inventory
            if ($status === 'cancelled') {
                $stmt = $pdo->prepare("
                    SELECT oi.coffin_id, oi.quantity
                    FROM order_items oi
                    WHERE oi.order_id = ?
                ");
                $stmt->execute([$order_id]);
                $items = $stmt->fetchAll();

                foreach ($items as $item) {
                    // Update coffin stock
                    $stmt = $pdo->prepare("
                        UPDATE coffins 
                        SET stock_quantity = stock_quantity + ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$item['quantity'], $item['coffin_id']]);

                    // Log stock adjustment
                    $stmt = $pdo->prepare("
                        INSERT INTO stock_adjustments (coffin_id, adjustment, notes, adjusted_by)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $item['coffin_id'],
                        $item['quantity'],
                        "Stock returned from cancelled order #" . $order_id,
                        $_SESSION['user_id']
                    ]);
                }
            }

            $pdo->commit();
            $_SESSION['success'] = "Order status updated successfully!";
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Update order status error: " . $e->getMessage());
            $_SESSION['error'] = "An error occurred while updating the order status.";
        }
    } else {
        $_SESSION['error'] = "Invalid input data.";
    }
}

// Redirect back to order management
header("Location: manage_orders.php");
exit; 