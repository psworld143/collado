<?php
require_once '../../config/db.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$errors = [];
$order = null;

// Get order ID from URL
$order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$order_id) {
    header("Location: manage_orders.php");
    exit;
}

// Fetch order details with customer and coffin information
try {
    $stmt = $pdo->prepare("
        SELECT o.*, 
               u.name as customer_name, 
               u.email as customer_email,
               u.phone as customer_phone,
               c.name as coffin_name,
               c.image as coffin_image,
               c.price as coffin_price
        FROM orders o
        JOIN users u ON o.user_id = u.id
        JOIN coffin_designs c ON o.coffin_id = c.id
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if (!$order) {
        header("Location: manage_orders.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("Fetch order error: " . $e->getMessage());
    $errors[] = "An error occurred while fetching the order details.";
}

include 'includes/admin_nav.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0">
                    <i class="fas fa-file-invoice"></i> Order Details #<?= $order_id ?>
                </h2>
                <div>
                    <button type="button" 
                            class="btn btn-primary" 
                            data-bs-toggle="modal" 
                            data-bs-target="#updateStatusModal">
                        <i class="fas fa-edit"></i> Update Status
                    </button>
                    <a href="manage_orders.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Orders
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Order Information -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Order Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Order Date:</strong></p>
                            <p class="mb-3"><?= date('F d, Y h:i A', strtotime($order['created_at'])) ?></p>

                            <p class="mb-1"><strong>Order Status:</strong></p>
                            <p class="mb-3">
                                <span class="badge bg-<?= getStatusColor($order['order_status']) ?>">
                                    <?= ucfirst($order['order_status']) ?>
                                </span>
                            </p>

                            <p class="mb-1"><strong>Payment Status:</strong></p>
                            <p class="mb-3">
                                <span class="badge bg-<?= getPaymentStatusColor($order['payment_status']) ?>">
                                    <?= ucfirst($order['payment_status']) ?>
                                </span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Total Amount:</strong></p>
                            <p class="mb-3">₱<?= number_format($order['total_amount'], 2) ?></p>

                            <p class="mb-1"><strong>Payment Method:</strong></p>
                            <p class="mb-3"><?= ucfirst($order['payment_method']) ?></p>

                            <?php if ($order['payment_method'] === 'gcash'): ?>
                                <p class="mb-1"><strong>GCash Reference:</strong></p>
                                <p class="mb-3"><?= htmlspecialchars($order['gcash_reference']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customer Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Customer Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Name:</strong></p>
                            <p class="mb-3"><?= htmlspecialchars($order['customer_name']) ?></p>

                            <p class="mb-1"><strong>Email:</strong></p>
                            <p class="mb-3"><?= htmlspecialchars($order['customer_email']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Phone:</strong></p>
                            <p class="mb-3"><?= htmlspecialchars($order['customer_phone']) ?></p>

                            <p class="mb-1"><strong>Address:</strong></p>
                            <p class="mb-3"><?= htmlspecialchars($order['delivery_address']) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Product Information -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Product Information</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <?php if (!empty($order['coffin_image'])): ?>
                            <img src="<?= htmlspecialchars($order['coffin_image']) ?>" 
                                 class="img-fluid rounded" 
                                 alt="<?= htmlspecialchars($order['coffin_name']) ?>">
                        <?php else: ?>
                            <div class="bg-light p-3 rounded">
                                <i class="fas fa-box fa-3x text-muted"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <h6 class="mb-2"><?= htmlspecialchars($order['coffin_name']) ?></h6>
                    <p class="text-muted mb-3"><?= htmlspecialchars($order['description']) ?></p>

                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">Price:</span>
                        <span class="fw-bold">₱<?= number_format($order['coffin_price'], 2) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="update_order_status.php" method="POST">
                <input type="hidden" name="order_id" value="<?= $order_id ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Update Order Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Order Status</label>
                        <select name="order_status" class="form-select">
                            <option value="pending" <?= $order['order_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="processing" <?= $order['order_status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
                            <option value="shipped" <?= $order['order_status'] === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                            <option value="delivered" <?= $order['order_status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                            <option value="cancelled" <?= $order['order_status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Status</label>
                        <select name="payment_status" class="form-select">
                            <option value="pending" <?= $order['payment_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="paid" <?= $order['payment_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                            <option value="cancelled" <?= $order['payment_status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
function getStatusColor($status) {
    return match($status) {
        'pending' => 'warning',
        'processing' => 'info',
        'shipped' => 'primary',
        'delivered' => 'success',
        'cancelled' => 'danger',
        default => 'secondary'
    };
}

function getPaymentStatusColor($status) {
    return match($status) {
        'paid' => 'success',
        'pending' => 'warning',
        'cancelled' => 'danger',
        default => 'secondary'
    };
}

include '../includes/footer.php';
?> 