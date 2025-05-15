<?php
require_once '../config/db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Get order ID from URL
$order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$order_id) {
    error_log("Invalid order ID provided: " . $_GET['id']);
    header("Location: order_history.php");
    exit;
}

// Fetch order details with customer information
$stmt = $pdo->prepare("
    SELECT o.*, 
           u.name as customer_name,
           u.email as customer_email,
           u.phone as customer_phone,
           c.name as coffin_name,
           c.description as coffin_description,
           c.image as coffin_image,
           c.price as coffin_price
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN coffins c ON o.coffin_id = c.id
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: orders.php");
    exit;
}

// Format customer name
$customer_name = !empty($order['customer_name']) ? $order['customer_name'] : 'Guest Customer';

// Log successful access
error_log("Order details accessed successfully - Order ID: " . $order_id . ", User ID: " . $_SESSION['user_id']);

include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-file-alt"></i> Order Details #<?= $order['id'] ?>
                    </h5>
                    <a href="order_history.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Back to Orders
                    </a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Order Information -->
                        <div class="col-md-6 mb-4">
                            <h6 class="border-bottom pb-2">Order Information</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <tr>
                                        <th>Order Number:</th>
                                        <td><?= htmlspecialchars($order['order_number']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Order Date:</th>
                                        <td><?= date('F d, Y h:i A', strtotime($order['created_at'])) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Status:</th>
                                        <td>
                                            <span class="badge bg-<?= getStatusColor($order['status'] ?? 'pending') ?>">
                                                <?= ucfirst($order['status'] ?? 'pending') ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Payment Status:</th>
                                        <td>
                                            <span class="badge bg-<?= getPaymentStatusColor($order['payment_status'] ?? 'pending') ?>">
                                                <?= ucfirst($order['payment_status'] ?? 'pending') ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Delivery Status:</th>
                                        <td>
                                            <span class="badge bg-<?= getDeliveryStatusColor($order['delivery_status'] ?? 'pending') ?>">
                                                <?= ucfirst($order['delivery_status'] ?? 'pending') ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Delivery Date:</th>
                                        <td><?= date('F d, Y', strtotime($order['delivery_date'])) ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Customer Information -->
                        <div class="col-md-6 mb-4">
                            <h6 class="border-bottom pb-2">Customer Information</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <tr>
                                        <th>Name:</th>
                                        <td><?= htmlspecialchars($customer_name) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Email:</th>
                                        <td><?= htmlspecialchars($order['customer_email'] ?? 'N/A') ?></td>
                                    </tr>
                                    <tr>
                                        <th>Phone:</th>
                                        <td><?= htmlspecialchars($order['customer_phone'] ?? 'N/A') ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Coffin Details -->
                        <div class="col-md-12 mb-4">
                            <h6 class="border-bottom pb-2">Coffin Details</h6>
                            <div class="row">
                                <div class="col-md-3">
                                    <?php if (!empty($order['coffin_image'])): ?>
                                        <img src="../<?= htmlspecialchars($order['coffin_image']) ?>" 
                                             class="img-fluid rounded" 
                                             alt="<?= htmlspecialchars($order['coffin_name']) ?>">
                                    <?php else: ?>
                                        <div class="bg-light p-3 rounded text-center">
                                            <i class="fas fa-box fa-3x text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-9">
                                    <h5><?= htmlspecialchars($order['coffin_name']) ?></h5>
                                    <p class="text-muted"><?= htmlspecialchars($order['coffin_description']) ?></p>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <tr>
                                                <th>Quantity:</th>
                                                <td><?= $order['quantity'] ?></td>
                                            </tr>
                                            <tr>
                                                <th>Price:</th>
                                                <td>₱<?= number_format($order['coffin_price'], 2) ?></td>
                                            </tr>
                                            <tr>
                                                <th>Total Amount:</th>
                                                <td>₱<?= number_format($order['total_amount'], 2) ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <?php if (!empty($order['notes'])): ?>
                        <div class="col-md-12">
                            <h6 class="border-bottom pb-2">Additional Notes</h6>
                            <div class="alert alert-info">
                                <?= nl2br(htmlspecialchars($order['notes'])) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Helper function to get status color
function getStatusColor($status) {
    return match($status) {
        'pending' => 'warning',
        'processing' => 'info',
        'delivered' => 'success',
        'cancelled' => 'danger',
        default => 'secondary'
    };
}

// Helper function to get payment status color
function getPaymentStatusColor($status) {
    return match($status) {
        'paid' => 'success',
        'pending' => 'warning',
        'cancelled' => 'danger',
        default => 'secondary'
    };
}

// Helper function to get delivery status color
function getDeliveryStatusColor($status) {
    return match($status) {
        'pending' => 'warning',
        'processing' => 'info',
        'shipped' => 'primary',
        'delivered' => 'success',
        'cancelled' => 'danger',
        default => 'secondary'
    };
}

include '../includes/footer.php';
?> 