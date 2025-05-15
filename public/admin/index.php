<?php
require_once '../../config/db.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Get statistics
try {
    // Total orders
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders");
    $total_orders = $stmt->fetchColumn();

    // Total revenue
    $stmt = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE payment_status = 'paid'");
    $total_revenue = $stmt->fetchColumn() ?: 0;

    // Total coffins
    $stmt = $pdo->query("SELECT COUNT(*) FROM coffins");
    $total_coffins = $stmt->fetchColumn();

    // Recent orders
    $stmt = $pdo->query("
        SELECT o.*, u.name as customer_name, c.name as coffin_name, o.status as order_status
        FROM orders o
        JOIN users u ON o.user_id = u.id
        JOIN coffins c ON o.coffin_id = c.id
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $recent_orders = $stmt->fetchAll();

    // Low stock coffins
    $stmt = $pdo->query("
        SELECT * FROM coffins 
        WHERE in_stock = 0 
        ORDER BY name ASC
    ");
    $low_stock_coffins = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $errors[] = "An error occurred while fetching dashboard data.";
}

include 'includes/admin_nav.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-0">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </h2>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Orders</h6>
                            <h2 class="mt-2 mb-0"><?= number_format($total_orders) ?></h2>
                        </div>
                        <i class="fas fa-shopping-cart fa-2x opacity-50"></i>
                    </div>
                </div>
                <div class="card-footer bg-primary-dark">
                    <a href="manage_orders.php" class="text-white text-decoration-none">
                        View All Orders <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Revenue</h6>
                            <h2 class="mt-2 mb-0">₱<?= number_format($total_revenue, 2) ?></h2>
                        </div>
                        <i class="fas fa-money-bill-wave fa-2x opacity-50"></i>
                    </div>
                </div>
                <div class="card-footer bg-success-dark">
                    <a href="manage_orders.php" class="text-white text-decoration-none">
                        View Sales Report <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Coffins</h6>
                            <h2 class="mt-2 mb-0"><?= number_format($total_coffins) ?></h2>
                        </div>
                        <i class="fas fa-box fa-2x opacity-50"></i>
                    </div>
                </div>
                <div class="card-footer bg-info-dark">
                    <a href="manage_coffins.php" class="text-white text-decoration-none">
                        Manage Coffins <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Orders -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Recent Orders</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_orders)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No orders found.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Customer</th>
                                        <th>Product</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_orders as $order): ?>
                                        <tr>
                                            <td>#<?= $order['id'] ?></td>
                                            <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                            <td><?= htmlspecialchars($order['coffin_name']) ?></td>
                                            <td>₱<?= number_format($order['total_amount'], 2) ?></td>
                                            <td>
                                                <span class="badge bg-<?= getStatusColor($order['order_status'] ?? 'pending') ?>">
                                                    <?= ucfirst($order['order_status'] ?? 'pending') ?>
                                                </span>
                                            </td>
                                            <td><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
                                            <td>
                                                <a href="order_details.php?id=<?= $order['id'] ?>" 
                                                   class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
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

        <!-- Low Stock Alert -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Low Stock Alert</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($low_stock_coffins)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> All coffins are in stock.
                        </div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($low_stock_coffins as $coffin): ?>
                                <a href="edit_coffin.php?id=<?= $coffin['id'] ?>" 
                                   class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?= htmlspecialchars($coffin['name']) ?></h6>
                                        <small class="text-danger">Out of Stock</small>
                                    </div>
                                    <small class="text-muted">
                                        Category: <?= ucfirst($coffin['category']) ?>
                                    </small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
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

include '../../includes/footer.php';
?> 