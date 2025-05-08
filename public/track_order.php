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

// Include header
include '../includes/header.php';
?>

<style>
    .track-card { 
        max-width: 800px; 
        margin: 40px auto;
    }

    .order-status-icon { 
        font-size: 1.5rem; 
        margin-right: 8px; 
    }

    .status-timeline {
        position: relative;
        padding-left: 30px;
    }

    .status-timeline::before {
        content: '';
        position: absolute;
        left: 15px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #e9ecef;
    }

    .status-item {
        position: relative;
        margin-bottom: 1.5rem;
    }

    .status-item::before {
        content: '';
        position: absolute;
        left: -30px;
        top: 50%;
        transform: translateY(-50%);
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: var(--bs-primary);
        border: 2px solid #fff;
        box-shadow: 0 0 0 2px var(--bs-primary);
    }

    .card {
        border: none;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }

    .card-header {
        border-bottom: none;
    }
</style>

<div class="container track-card">
    <div class="card shadow-lg">
        <div class="card-header bg-primary text-white text-center py-4">
            <h3 class="mb-0"><i class="fas fa-shipping-fast"></i> Track Your Order</h3>
        </div>
        <div class="card-body p-4">
            <form method="GET" class="row g-3 mb-4 justify-content-center">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="number" name="order_id" id="order_id" class="form-control form-control-lg" required placeholder="Enter Order ID" value="<?= isset($_GET['order_id']) ? htmlspecialchars($_GET['order_id']) : '' ?>">
                        <button type="submit" class="btn btn-primary btn-lg">Track</button>
                    </div>
                </div>
            </form>

            <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php elseif ($order): ?>
                <div class="card mb-4 border-info">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="fas fa-receipt order-status-icon text-info"></i>
                            Order #<?= $order['id'] ?>
                        </h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-2">
                                    <i class="fas fa-user text-primary me-2"></i>
                                    <strong>Customer:</strong> <?= htmlspecialchars($order['customer_name']) ?>
                                </p>
                                <p class="mb-2">
                                    <i class="fas fa-calendar-alt text-primary me-2"></i>
                                    <strong>Order Date:</strong> <?= date('M d, Y H:i', strtotime($order['created_at'])) ?>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-2">
                                    <i class="fas fa-info-circle text-primary me-2"></i>
                                    <strong>Status:</strong>
                                    <span class="badge bg-<?= ($order['payment_status'] ?? 'pending') === 'paid' ? 'success' : (($order['payment_status'] ?? 'pending') === 'cancelled' ? 'danger' : 'warning') ?>">
                                        <?= htmlspecialchars(ucfirst($order['payment_status'] ?? 'N/A')) ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <h5 class="mb-4"><i class="fas fa-truck-moving text-primary me-2"></i>Delivery Status History</h5>
                <?php if ($statuses): ?>
                    <div class="status-timeline">
                        <?php foreach ($statuses as $row): ?>
                            <div class="status-item">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><strong><?= htmlspecialchars($row['status']) ?></strong></h6>
                                                <small class="text-muted">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?= date('M d, Y H:i', strtotime($row['updated_at'])) ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info d-flex align-items-center">
                        <i class="fas fa-info-circle me-2"></i>
                        <div>No delivery updates yet.</div>
                    </div>
                <?php endif; ?>
            <?php elseif (isset($_GET['order_id'])): ?>
                <div class="alert alert-warning d-flex align-items-center">
                    <i class="fas fa-question-circle me-2"></i>
                    <div>No order found for that ID.</div>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer bg-light text-center py-3">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Home
            </a>
        </div>
    </div>
</div>

<?php
// Include footer
include '../includes/footer.php';
?>