<?php
include '../config/db.php';
$order = null;
$statuses = [];
$error = '';

if (isset($_GET['order_id'])) {
    $id = (int)$_GET['order_id'];
    // Fetch order details
    $stmt = $pdo->prepare("SELECT o.*, u.name as customer_name FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
    $stmt->execute([$id]);
    $order = $stmt->fetch();

    if ($order) {
        // Fetch all delivery statuses for this order
        $stmt = $pdo->prepare("SELECT status, updated_at FROM delivery_status WHERE order_id = ? ORDER BY updated_at DESC");
        $stmt->execute([$id]);
        $statuses = $stmt->fetchAll();
    } else {
        $error = "Order not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Your Order</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .track-card { max-width: 600px; margin: 40px auto; }
        .order-status-icon { font-size: 1.5rem; margin-right: 8px; }
    </style>
</head>
<body>
<div class="container track-card">
    <div class="card shadow-lg border-0">
        <div class="card-header bg-primary text-white text-center">
            <h3 class="mb-0"><i class="fas fa-shipping-fast"></i> Track Your Order</h3>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-2 mb-4 justify-content-center">
                <div class="col-auto">
                    <label for="order_id" class="visually-hidden">Order ID</label>
                    <input type="number" name="order_id" id="order_id" class="form-control" required placeholder="Enter Order ID" value="<?= isset($_GET['order_id']) ? htmlspecialchars($_GET['order_id']) : '' ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Track</button>
                </div>
            </form>

            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php elseif ($order): ?>
                <div class="card mb-3 border-info">
                    <div class="card-body">
                        <h5 class="card-title mb-2"><i class="fas fa-receipt order-status-icon text-info"></i>Order #<?= $order['id'] ?></h5>
                        <p class="mb-1"><strong><i class="fas fa-user"></i> Customer:</strong> <?= htmlspecialchars($order['customer_name']) ?></p>
                        <p class="mb-1"><strong><i class="fas fa-calendar-alt"></i> Order Date:</strong> <?= date('M d, Y H:i', strtotime($order['created_at'])) ?></p>
                        <p class="mb-1"><strong><i class="fas fa-info-circle"></i> Status:</strong> <span class="badge bg-<?= ($order['payment_status'] ?? 'pending') === 'paid' ? 'success' : (($order['payment_status'] ?? 'pending') === 'cancelled' ? 'danger' : 'warning') ?>">
                            <?= htmlspecialchars(ucfirst($order['payment_status'] ?? 'N/A')) ?></span></p>
                    </div>
                </div>
                <h5 class="mb-3"><i class="fas fa-truck-moving"></i> Delivery Status History</h5>
                <?php if ($statuses): ?>
                    <ul class="list-group mb-3">
                        <?php foreach ($statuses as $row): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-circle-notch text-primary"></i> <strong><?= htmlspecialchars($row['status']) ?></strong></span>
                                <span class="text-muted small"><i class="fas fa-clock"></i> <?= date('M d, Y H:i', strtotime($row['updated_at'])) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="alert alert-info"><i class="fas fa-info-circle"></i> No delivery updates yet.</div>
                <?php endif; ?>
            <?php elseif (isset($_GET['order_id'])): ?>
                <div class="alert alert-warning"><i class="fas fa-question-circle"></i> No order found for that ID.</div>
            <?php endif; ?>
        </div>
        <div class="card-footer text-center bg-light">
            <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to Home</a>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>