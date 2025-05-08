<?php
require_once '../../config/db.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Get date range from request or default to current month
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Get sales data
$stmt = $pdo->prepare("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as order_count,
        SUM(total_amount) as total_sales
    FROM orders
    WHERE created_at BETWEEN ? AND ?
    AND payment_status = 'paid'
    GROUP BY DATE(created_at)
    ORDER BY date
");
$stmt->execute([$start_date, $end_date]);
$sales_data = $stmt->fetchAll();

// Get top selling coffins
try {
    $stmt = $pdo->prepare("
        SELECT 
            c.name,
            COUNT(DISTINCT o.id) as order_count,
            SUM(oi.quantity) as total_quantity,
            SUM(oi.quantity * oi.price) as total_revenue
        FROM coffins c
        LEFT JOIN order_items oi ON c.id = oi.coffin_id
        LEFT JOIN orders o ON oi.order_id = o.id
        WHERE (o.created_at BETWEEN ? AND ? OR o.created_at IS NULL)
        AND (o.payment_status = 'paid' OR o.payment_status IS NULL)
        GROUP BY c.id, c.name
        HAVING order_count > 0
        ORDER BY total_revenue DESC
        LIMIT 10
    ");
    $stmt->execute([$start_date, $end_date]);
    $top_coffins = $stmt->fetchAll();

    // Log the results for debugging
    error_log("Top selling coffins query results: " . print_r($top_coffins, true));
} catch (PDOException $e) {
    error_log("Error fetching top selling coffins: " . $e->getMessage());
    $top_coffins = [];
}

// Get customer statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT user_id) as total_customers,
        AVG(total_amount) as average_order_value,
        MAX(total_amount) as highest_order
    FROM orders
    WHERE created_at BETWEEN ? AND ?
    AND payment_status = 'paid'
");
$stmt->execute([$start_date, $end_date]);
$customer_stats = $stmt->fetch();

include 'includes/admin_nav.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0">
                    <i class="fas fa-chart-bar"></i> Sales Reports
                </h2>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" 
                           value="<?= $start_date ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" 
                           value="<?= $end_date ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Customer Statistics -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Total Customers</h6>
                    <h2 class="mb-0"><?= number_format($customer_stats['total_customers'] ?? 0) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Average Order Value</h6>
                    <h2 class="mb-0">₱<?= number_format($customer_stats['average_order_value'] ?? 0, 2) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Highest Order Value</h6>
                    <h2 class="mb-0">₱<?= number_format($customer_stats['highest_order'] ?? 0, 2) ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales Chart -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Daily Sales</h5>
        </div>
        <div class="card-body">
            <canvas id="salesChart" height="100"></canvas>
        </div>
    </div>

    <!-- Top Selling Coffins -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Top Selling Coffins</h5>
        </div>
        <div class="card-body">
            <?php if (empty($top_coffins)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No sales data available for the selected period.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Coffin Name</th>
                                <th>Orders</th>
                                <th>Quantity Sold</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_coffins as $coffin): ?>
                                <tr>
                                    <td><?= htmlspecialchars($coffin['name']) ?></td>
                                    <td><?= number_format($coffin['order_count']) ?></td>
                                    <td><?= number_format($coffin['total_quantity']) ?></td>
                                    <td>₱<?= number_format($coffin['total_revenue'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-primary">
                                <th colspan="3" class="text-end">Total Revenue:</th>
                                <th>₱<?= number_format(array_sum(array_column($top_coffins, 'total_revenue')), 2) ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Prepare data for the chart
const salesData = <?= json_encode($sales_data) ?>;
const dates = salesData.map(item => item.date);
const sales = salesData.map(item => item.total_sales);
const orders = salesData.map(item => item.order_count);

// Create the chart
const ctx = document.getElementById('salesChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: dates,
        datasets: [{
            label: 'Daily Sales (₱)',
            data: sales,
            borderColor: 'rgb(75, 192, 192)',
            tension: 0.1,
            yAxisID: 'y'
        }, {
            label: 'Number of Orders',
            data: orders,
            borderColor: 'rgb(255, 99, 132)',
            tension: 0.1,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Sales (₱)'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Number of Orders'
                },
                grid: {
                    drawOnChartArea: false
                }
            }
        }
    }
});
</script>

<?php include '../../includes/footer.php'; ?> 