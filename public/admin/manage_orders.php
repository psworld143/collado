<?php
require_once '../../config/db.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Get filter parameters
$search = isset($_GET['search']) ? htmlspecialchars($_GET['search'], ENT_QUOTES, 'UTF-8') : '';
$status = isset($_GET['status']) ? htmlspecialchars($_GET['status'], ENT_QUOTES, 'UTF-8') : '';
$date_from = isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from'], ENT_QUOTES, 'UTF-8') : '';
$date_to = isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to'], ENT_QUOTES, 'UTF-8') : '';

// Build query
$sql = "SELECT o.*, 
               u.name as customer_name, 
               u.phone as customer_phone,
               COUNT(oi.id) as item_count
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (o.id LIKE ? OR u.name LIKE ? OR u.phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($status)) {
    $sql .= " AND o.payment_status = ?";
    $params[] = $status;
}

if (!empty($date_from)) {
    $sql .= " AND DATE(o.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $sql .= " AND DATE(o.created_at) <= ?";
    $params[] = $date_to;
}

$sql .= " GROUP BY o.id ORDER BY o.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

include 'includes/admin_nav.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0">
                    <i class="fas fa-shopping-cart"></i> Order Management
                </h2>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search orders..." value="<?= $search ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="manage_orders.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($orders)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No orders found matching your criteria.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>#<?= $order['id'] ?></td>
                                    <td>
                                        <?= htmlspecialchars($order['customer_name']) ?><br>
                                        <small class="text-muted"><?= htmlspecialchars($order['customer_phone']) ?></small>
                                    </td>
                                    <td><?= number_format($order['item_count']) ?> items</td>
                                    <td>₱<?= number_format($order['total_amount'], 2) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $order['payment_status'] === 'paid' ? 'success' : 
                                            ($order['payment_status'] === 'cancelled' ? 'danger' : 'warning') ?>">
                                            <?= ucfirst($order['payment_status']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y H:i', strtotime($order['created_at'])) ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" 
                                                    class="btn btn-sm btn-info" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#viewOrderModal<?= $order['id'] ?>"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($order['payment_status'] === 'pending'): ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-success" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#markPaidModal<?= $order['id'] ?>"
                                                        title="Mark as Paid">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button type="button" 
                                                        class="btn btn-sm btn-danger" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#cancelOrderModal<?= $order['id'] ?>"
                                                        title="Cancel Order">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>

                                        <!-- View Order Modal -->
                                        <div class="modal fade" id="viewOrderModal<?= $order['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Order Details #<?= $order['id'] ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <!-- Customer Info -->
                                                        <div class="row mb-4">
                                                            <div class="col-md-6">
                                                                <h6>Customer Information</h6>
                                                                <p class="mb-1">
                                                                    <strong>Name:</strong> <?= htmlspecialchars($order['customer_name']) ?>
                                                                </p>
                                                                <p class="mb-1">
                                                                    <strong>Phone:</strong> <?= htmlspecialchars($order['customer_phone']) ?>
                                                                </p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <h6>Order Information</h6>
                                                                <p class="mb-1">
                                                                    <strong>Date:</strong> <?= date('F d, Y H:i', strtotime($order['created_at'])) ?>
                                                                </p>
                                                                <p class="mb-1">
                                                                    <strong>Status:</strong> 
                                                                    <span class="badge bg-<?= $order['payment_status'] === 'paid' ? 'success' : 
                                                                        ($order['payment_status'] === 'cancelled' ? 'danger' : 'warning') ?>">
                                                                        <?= ucfirst($order['payment_status']) ?>
                                                                    </span>
                                                                </p>
                                                            </div>
                                                        </div>

                                                        <!-- Order Items -->
                                                        <h6>Order Items</h6>
                                                        <div class="table-responsive">
                                                            <table class="table table-sm">
                                                                <thead>
                                                                    <tr>
                                                                        <th>Item</th>
                                                                        <th>Price</th>
                                                                        <th>Quantity</th>
                                                                        <th>Subtotal</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php
                                                                    $stmt = $pdo->prepare("
                                                                        SELECT oi.*, c.name as coffin_name
                                                                        FROM order_items oi
                                                                        JOIN coffins c ON oi.coffin_id = c.id
                                                                        WHERE oi.order_id = ?
                                                                    ");
                                                                    $stmt->execute([$order['id']]);
                                                                    $items = $stmt->fetchAll();
                                                                    foreach ($items as $item):
                                                                    ?>
                                                                        <tr>
                                                                            <td><?= htmlspecialchars($item['coffin_name']) ?></td>
                                                                            <td>₱<?= number_format($item['price'], 2) ?></td>
                                                                            <td><?= number_format($item['quantity']) ?></td>
                                                                            <td>₱<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                                <tfoot>
                                                                    <tr>
                                                                        <th colspan="3" class="text-end">Total:</th>
                                                                        <th>₱<?= number_format($order['total_amount'], 2) ?></th>
                                                                    </tr>
                                                                </tfoot>
                                                            </table>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Mark as Paid Modal -->
                                        <?php if ($order['payment_status'] === 'pending'): ?>
                                            <div class="modal fade" id="markPaidModal<?= $order['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Confirm Payment</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Are you sure you want to mark this order as paid?</p>
                                                            <p class="mb-0">
                                                                <strong>Order #<?= $order['id'] ?></strong><br>
                                                                <strong>Amount:</strong> ₱<?= number_format($order['total_amount'], 2) ?>
                                                            </p>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <form action="update_order_status.php" method="POST" class="d-inline">
                                                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                                <input type="hidden" name="status" value="paid">
                                                                <button type="submit" class="btn btn-success">Mark as Paid</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Cancel Order Modal -->
                                        <?php if ($order['payment_status'] === 'pending'): ?>
                                            <div class="modal fade" id="cancelOrderModal<?= $order['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Confirm Cancellation</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Are you sure you want to cancel this order?</p>
                                                            <p class="mb-0">
                                                                <strong>Order #<?= $order['id'] ?></strong><br>
                                                                <strong>Amount:</strong> ₱<?= number_format($order['total_amount'], 2) ?>
                                                            </p>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <form action="update_order_status.php" method="POST" class="d-inline">
                                                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                                <input type="hidden" name="status" value="cancelled">
                                                                <button type="submit" class="btn btn-danger">Cancel Order</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?> 