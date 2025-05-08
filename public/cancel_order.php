<?php
include 'user_auth.php';
include '../config/db.php';

$orderId = $_GET['id'] ?? null;
$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("DELETE FROM orders WHERE id = ? AND user_id = ? AND id NOT IN (SELECT order_id FROM delivery_status WHERE status = 'delivered')");
$stmt->execute([$orderId, $userId]);

header("Location: my_orders.php");
exit;
