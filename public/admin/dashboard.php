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
    // Total sales
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) as total_sales,
            COUNT(DISTINCT user_id) as total_customers
        FROM orders
    ");
    $stats = $stmt->fetch();

    // Low stock items
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM coffins 
        WHERE stock_quantity <= low_stock_threshold
    ");
    $low_stock_count = $stmt->fetchColumn();

    // Recent orders
    $stmt = $pdo->query("
        SELECT o.*, u.name as customer_name
        FROM orders o
        JOIN users u ON o.user_id = u.id
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $recent_orders = $stmt->fetchAll();

    // Recent stock adjustments
    $stmt = $pdo->query("
        SELECT sa.*, c.name as coffin_name, u.name as adjusted_by_name
        FROM stock_adjustments sa
        JOIN coffins c ON sa.coffin_id = c.id
        JOIN users u ON sa.adjusted_by = u.id
        ORDER BY sa.created_at DESC
        LIMIT 5
    ");
    $recent_adjustments = $stmt->fetchAll();

    // Sales by category
    $stmt = $pdo->query("
        SELECT c.category,
               COUNT(DISTINCT o.id) as order_count,
               SUM(oi.quantity) as total_quantity,
               SUM(oi.quantity * oi.price) as total_sales
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN coffins c ON oi.coffin_id = c.id
        WHERE o.payment_status = 'paid'
        GROUP BY c.category
        ORDER BY total_sales DESC
    ");
    $sales_by_category = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while loading the dashboard.";
}

// Include admin-specific header
//include '../../includes/header.php';
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
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Sales</h6>
                            <h3 class="mt-2 mb-0">₱<?= number_format($stats['total_sales'] ?? 0, 2) ?></h3>
                        </div>
                        <i class="fas fa-money-bill-wave fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Orders</h6>
                            <h3 class="mt-2 mb-0"><?= number_format($stats['total_orders'] ?? 0) ?></h3>
                        </div>
                        <i class="fas fa-shopping-cart fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Total Customers</h6>
                            <h3 class="mt-2 mb-0"><?= number_format($stats['total_customers'] ?? 0) ?></h3>
                        </div>
                        <i class="fas fa-users fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title mb-0">Low Stock Items</h6>
                            <h3 class="mt-2 mb-0"><?= number_format($low_stock_count ?? 0) ?></h3>
                        </div>
                        <i class="fas fa-exclamation-triangle fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Orders -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-clock"></i> Recent Orders
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_orders)): ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle"></i> No recent orders.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_orders as $order): ?>
                                        <tr>
                                            <td>#<?= $order['id'] ?></td>
                                            <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                            <td>₱<?= number_format($order['total_amount'], 2) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $order['payment_status'] === 'paid' ? 'success' : 
                                                    ($order['payment_status'] === 'cancelled' ? 'danger' : 'warning') ?>">
                                                    <?= ucfirst($order['payment_status']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('M d, Y H:i', strtotime($order['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-end mt-3">
                            <a href="manage_orders.php" class="btn btn-primary btn-sm">
                                View All Orders
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Stock Adjustments -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-boxes"></i> Recent Stock Adjustments
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_adjustments)): ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle"></i> No recent stock adjustments.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Adjustment</th>
                                        <th>Notes</th>
                                        <th>By</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_adjustments as $adjustment): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($adjustment['coffin_name']) ?></td>
                                            <td class="<?= $adjustment['adjustment'] > 0 ? 'text-success' : 'text-danger' ?>">
                                                <?= $adjustment['adjustment'] > 0 ? '+' : '' ?><?= number_format($adjustment['adjustment']) ?>
                                            </td>
                                            <td><?= htmlspecialchars($adjustment['notes']) ?></td>
                                            <td><?= htmlspecialchars($adjustment['adjusted_by_name']) ?></td>
                                            <td><?= date('M d, Y H:i', strtotime($adjustment['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-end mt-3">
                            <a href="manage_inventory.php" class="btn btn-primary btn-sm">
                                View Inventory
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sales by Category -->
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-pie"></i> Sales by Category
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($sales_by_category)): ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle"></i> No sales data available.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Orders</th>
                                        <th>Quantity</th>
                                        <th>Total Sales</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sales_by_category as $category): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($category['category']) ?></td>
                                            <td><?= number_format($category['order_count']) ?></td>
                                            <td><?= number_format($category['total_quantity']) ?></td>
                                            <td>₱<?= number_format($category['total_sales'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?> 