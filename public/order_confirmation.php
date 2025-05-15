<?php
require_once '../config/db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Check if order ID is provided
if (!isset($_GET['id'])) {
    header("Location: coffin_catalog.php");
    exit;
}

$order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Get order details
$stmt = $pdo->prepare("
    SELECT o.*, c.name as coffin_name, c.price, c.image
    FROM orders o
    JOIN coffins c ON o.coffin_id = c.id
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    header("Location: coffin_catalog.php");
    exit;
}

include '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-lg">
                <div class="card-header bg-success text-white">
                    <h2 class="card-title text-center mb-0">
                        <i class="fas fa-check-circle"></i> Order Confirmed
                    </h2>
                </div>
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <i class="fas fa-check-circle text-success fa-4x mb-3"></i>
                        <h3 class="text-success">Thank You for Your Order!</h3>
                        <p class="text-muted">Your order has been successfully placed.</p>
                    </div>

                    <div class="order-details">
                        <h4 class="mb-3">Order Details</h4>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <tr>
                                    <th>Order Number:</th>
                                    <td><?= htmlspecialchars($order['order_number']) ?></td>
                                </tr>
                                <tr>
                                    <th>Order Date:</th>
                                    <td><?= date('F j, Y', strtotime($order['created_at'])) ?></td>
                                </tr>
                                <tr>
                                    <th>Coffin Design:</th>
                                    <td><?= htmlspecialchars($order['coffin_name']) ?></td>
                                </tr>
                                <tr>
                                    <th>Quantity:</th>
                                    <td><?= htmlspecialchars($order['quantity']) ?></td>
                                </tr>
                                <tr>
                                    <th>Total Amount:</th>
                                    <td>₱<?= number_format($order['total_amount'], 2) ?></td>
                                </tr>
                                <tr>
                                    <th>Delivery Date:</th>
                                    <td><?= date('F j, Y', strtotime($order['delivery_date'])) ?></td>
                                </tr>
                                <tr>
                                    <th>Payment Status:</th>
                                    <td>
                                        <span class="badge bg-<?= $order['payment_status'] === 'pending' ? 'warning' : 'success' ?>">
                                            <?= ucfirst(htmlspecialchars($order['payment_status'])) ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <?php if (!empty($order['notes'])): ?>
                    <div class="mt-4">
                        <h4>Special Instructions</h4>
                        <p class="text-muted"><?= nl2br(htmlspecialchars($order['notes'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <div class="mt-4">
                        <div class="alert alert-info">
                            <h5><i class="fas fa-info-circle"></i> What's Next?</h5>
                            <ul class="mb-0">
                                <li>You will receive a confirmation email with your order details.</li>
                                <li>Our team will process your order and contact you for payment details.</li>
                                <li>You can track your order status in your account dashboard.</li>
                            </ul>
                        </div>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <a href="coffin_catalog.php" class="btn btn-primary">
                            <i class="fas fa-shopping-cart"></i> Continue Shopping
                        </a>
                        <a href="order_history.php" class="btn btn-outline-secondary">
                            <i class="fas fa-list"></i> View My Orders
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?> 