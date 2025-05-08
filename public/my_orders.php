<?php
include 'user_auth.php';
include '../config/db.php';

$userId = $_SESSION['user_id'];
$sql = "
    SELECT o.id, d.name, d.price, o.payment_status,
           (SELECT status FROM delivery_status WHERE order_id = o.id ORDER BY updated_at DESC LIMIT 1) AS delivery_status
    FROM orders o
    JOIN coffins d ON o.coffin_id = d.id
    WHERE o.user_id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();
?>

<h2>My Orders</h2>
<table class="table table-bordered">
    <thead>
        <tr>
            <th>Order #</th>
            <th>Coffin Design</th>
            <th>Price</th>
            <th>Payment Status</th>
            <th>Delivery Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($orders as $order): ?>
        <tr>
            <td><?= $order['id'] ?></td>
            <td><?= htmlspecialchars($order['name']) ?></td>
            <td>₱<?= number_format($order['price'], 2) ?></td>
            <td><?= ucfirst($order['payment_status']) ?></td>
            <td><?= ucfirst($order['delivery_status'] ?? 'Not Yet Started') ?></td>
            <td>
                <?php if ($order['delivery_status'] !== 'delivered'): ?>
                    <a href="edit_order.php?id=<?= $order['id'] ?>" class="btn btn-warning btn-sm">Modify</a>
                    <a href="cancel_order.php?id=<?= $order['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Cancel this order?')">Cancel</a>
                <?php else: ?>
                    <em>N/A</em>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
