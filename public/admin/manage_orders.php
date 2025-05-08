<?php
require_once '../../config/db.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Add SweetAlert2 CSS and JS
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">';
echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>';

// Get filter parameters
$search = isset($_GET['search']) ? htmlspecialchars($_GET['search'], ENT_QUOTES, 'UTF-8') : '';
$status = isset($_GET['status']) ? htmlspecialchars($_GET['status'], ENT_QUOTES, 'UTF-8') : '';
$date_from = isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from'], ENT_QUOTES, 'UTF-8') : '';
$date_to = isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to'], ENT_QUOTES, 'UTF-8') : '';

// Build query
$sql = "SELECT o.*, 
               COALESCE(u.name, 'N/A') as customer_name, 
               COALESCE(u.phone, 'N/A') as customer_phone,
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
                                        <?= htmlspecialchars($order['customer_name'] ?? 'N/A') ?><br>
                                        <small class="text-muted"><?= htmlspecialchars($order['customer_phone'] ?? 'N/A') ?></small>
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
                                                    onclick="viewOrderDetails(<?= $order['id'] ?>)"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($order['payment_status'] === 'pending'): ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-success" 
                                                        onclick="updateOrderStatus(<?= $order['id'] ?>, 'paid')"
                                                        title="Mark as Paid">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button type="button" 
                                                        class="btn btn-sm btn-danger" 
                                                        onclick="updateOrderStatus(<?= $order['id'] ?>, 'cancelled')"
                                                        title="Cancel Order">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
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

<!-- Single Order Details Modal -->
<div class="modal fade" id="orderDetailsModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Order Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="orderDetailsContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Status Update Modal -->
<div class="modal fade" id="statusUpdateModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Order Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="statusUpdateContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
function viewOrderDetails(orderId) {
    // Show loading state
    const modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
    document.getElementById('orderDetailsContent').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading order details...</p>
        </div>
    `;
    modal.show();

    // Fetch order details
    fetch(`get_order_details.php?id=${orderId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const order = data.order;
                const items = data.items;
                
                // Create HTML content for the order details
                let itemsHtml = '';
                items.forEach(item => {
                    itemsHtml += `
                        <tr>
                            <td>${item.coffin_name}</td>
                            <td>₱${parseFloat(item.price).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                            <td>${parseInt(item.quantity).toLocaleString()}</td>
                            <td>₱${(parseFloat(item.price) * parseInt(item.quantity)).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                        </tr>
                    `;
                });

                // Update modal content
                document.getElementById('orderDetailsContent').innerHTML = `
                    <div class="text-start">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6 class="border-bottom pb-2">Customer Information</h6>
                                <p class="mb-1"><strong>Name:</strong> ${order.customer_name}</p>
                                <p class="mb-1"><strong>Phone:</strong> ${order.customer_phone}</p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="border-bottom pb-2">Order Information</h6>
                                <p class="mb-1"><strong>Date:</strong> ${new Date(order.created_at).toLocaleString()}</p>
                                <p class="mb-1">
                                    <strong>Status:</strong> 
                                    <span class="badge bg-${order.payment_status === 'paid' ? 'success' : 
                                        (order.payment_status === 'cancelled' ? 'danger' : 'warning')}">
                                        ${order.payment_status.charAt(0).toUpperCase() + order.payment_status.slice(1)}
                                    </span>
                                </p>
                            </div>
                        </div>
                        <h6 class="border-bottom pb-2">Order Items</h6>
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
                                    ${itemsHtml}
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="3" class="text-end">Total:</th>
                                        <th>₱${parseFloat(order.total_amount).toLocaleString('en-US', {minimumFractionDigits: 2})}</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                `;
            } else {
                document.getElementById('orderDetailsContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> Failed to load order details
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('orderDetailsContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> Failed to load order details
                </div>
            `;
        });
}

function updateOrderStatus(orderId, newStatus) {
    const statusText = newStatus === 'paid' ? 'Mark as Paid' : 'Cancel Order';
    const statusClass = newStatus === 'paid' ? 'success' : 'danger';
    
    // Show confirmation modal
    const modal = new bootstrap.Modal(document.getElementById('statusUpdateModal'));
    document.getElementById('statusUpdateContent').innerHTML = `
        <div class="text-center">
            <div class="mb-4">
                <i class="fas fa-question-circle fa-3x text-${statusClass}"></i>
            </div>
            <h5>Are you sure you want to ${statusText.toLowerCase()} this order?</h5>
            <p class="text-muted">This action cannot be undone.</p>
            <div class="mt-4">
                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-${statusClass}" onclick="confirmStatusUpdate(${orderId}, '${newStatus}')">
                    Yes, ${statusText}
                </button>
            </div>
        </div>
    `;
    modal.show();
}

function confirmStatusUpdate(orderId, newStatus) {
    // Show loading state
    document.getElementById('statusUpdateContent').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Updating order status...</p>
        </div>
    `;

    // Send update request
    fetch('update_order_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            order_id: orderId,
            status: newStatus
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            document.getElementById('statusUpdateContent').innerHTML = `
                <div class="text-center">
                    <div class="mb-4">
                        <i class="fas fa-check-circle fa-3x text-success"></i>
                    </div>
                    <h5>Order status updated successfully!</h5>
                    <p class="text-muted">The page will refresh in a moment...</p>
                </div>
            `;
            
            // Close modal and refresh page after delay
            setTimeout(() => {
                bootstrap.Modal.getInstance(document.getElementById('statusUpdateModal')).hide();
                window.location.reload();
            }, 1500);
        } else {
            // Show error message
            document.getElementById('statusUpdateContent').innerHTML = `
                <div class="text-center">
                    <div class="mb-4">
                        <i class="fas fa-exclamation-circle fa-3x text-danger"></i>
                    </div>
                    <h5>Failed to update order status</h5>
                    <p class="text-danger">${data.message || 'An error occurred'}</p>
                    <button type="button" class="btn btn-secondary mt-3" data-bs-dismiss="modal">Close</button>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('statusUpdateContent').innerHTML = `
            <div class="text-center">
                <div class="mb-4">
                    <i class="fas fa-exclamation-circle fa-3x text-danger"></i>
                </div>
                <h5>Failed to update order status</h5>
                <p class="text-danger">An unexpected error occurred</p>
                <button type="button" class="btn btn-secondary mt-3" data-bs-dismiss="modal">Close</button>
            </div>
        `;
    });
}
</script>

<style>
#orderDetailsModal .modal-dialog {
    max-width: 800px;
}
#orderDetailsModal .modal-body {
    padding: 1.5rem;
}
#statusUpdateModal .modal-dialog {
    max-width: 400px;
}
</style> 