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
               c.name as coffin_name,
               c.price as coffin_price,
               o.delivery_status,
               o.delivery_date,
               o.created_at,
               o.payment_status,
               o.total_amount,
               o.notes,
               p.payment_method,
               p.transaction_id as payment_reference,
               p.payment_date
        FROM orders o
        JOIN users u ON o.user_id = u.id
        JOIN coffins c ON o.coffin_id = c.id
        LEFT JOIN payments p ON o.id = p.order_id
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

// Add this function before the HTML
function getDeliveryStatusColor($status) {
    switch ($status) {
        case 'pending':
            return 'warning';
        case 'processing':
            return 'info';
        case 'shipped':
            return 'primary';
        case 'delivered':
            return 'success';
        case 'cancelled':
            return 'danger';
        default:
            return 'secondary';
    }
}

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
                                <th>Payment Status</th>
                                <th>Payment Method</th>
                                <th>Delivery Status</th>
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
                                    <td>
                                        <?= htmlspecialchars($order['coffin_name']) ?><br>
                                        <small class="text-muted">₱<?= number_format($order['coffin_price'], 2) ?></small>
                                    </td>
                                    <td>₱<?= number_format($order['total_amount'], 2) ?></td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="badge bg-<?= $order['payment_status'] === 'paid' ? 'success' : 
                                                ($order['payment_status'] === 'cancelled' ? 'danger' : 'warning') ?> mb-1">
                                                <?= ucfirst($order['payment_status']) ?>
                                            </span>
                                            <?php if ($order['payment_status'] === 'paid'): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar-alt me-1"></i>
                                                    <?= date('M d, Y', strtotime($order['payment_date'])) ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($order['payment_status'] === 'paid'): ?>
                                            <div class="d-flex flex-column">
                                                <span class="badge bg-info mb-1">
                                                    <i class="fas fa-<?= $order['payment_method'] === 'bank' ? 'university' : 'mobile-alt' ?> me-1"></i>
                                                    <?= ucfirst($order['payment_method'] ?? 'N/A') ?>
                                                </span>
                                                <?php if ($order['payment_reference']): ?>
                                                    <small class="text-monospace bg-light px-2 py-1 rounded">
                                                        <?= htmlspecialchars($order['payment_reference']) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= getDeliveryStatusColor($order['delivery_status'] ?? 'pending') ?>">
                                            <?= ucfirst($order['delivery_status'] ?? 'pending') ?>
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
                                                        onclick="verifyPayment(<?= $order['id'] ?>, <?= $order['payment_id'] ?? 'null' ?>)"
                                                        title="Verify Payment">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button type="button" 
                                                        class="btn btn-sm btn-danger" 
                                                        onclick="rejectPayment(<?= $order['id'] ?>, <?= $order['payment_id'] ?? 'null' ?>)"
                                                        title="Reject Payment">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($order['payment_status'] === 'paid'): ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-primary" 
                                                        onclick="updateDeliveryStatus(<?= $order['id'] ?>)"
                                                        title="Update Delivery Status">
                                                    <i class="fas fa-truck"></i>
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
        </div>
    `;
    modal.show();

    // Fetch order details
    fetch(`get_order_details.php?id=${orderId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('orderDetailsContent').innerHTML = html;
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('orderDetailsContent').innerHTML = `
                <div class="alert alert-danger">
                    Error loading order details. Please try again.
                </div>
            `;
        });
}

function updateOrderStatus(orderId, status) {
    Swal.fire({
        title: 'Update Order Status',
        text: `Are you sure you want to mark this order as ${status}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, update it!'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('order_id', orderId);
            formData.append('status', status);

            fetch('update_order_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Updated!',
                        text: 'Order status has been updated.',
                        icon: 'success'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: data.message || 'Failed to update order status.',
                        icon: 'error'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error!',
                    text: 'An error occurred while updating the order status.',
                    icon: 'error'
                });
            });
        }
    });
}

function updateDeliveryStatus(orderId) {
    const modal = new bootstrap.Modal(document.getElementById('statusUpdateModal'));
    document.getElementById('statusUpdateContent').innerHTML = `
        <form id="deliveryStatusForm" onsubmit="submitDeliveryStatus(event, ${orderId})">
            <div class="mb-3">
                <label class="form-label">Delivery Status</label>
                <select name="delivery_status" class="form-select" required>
                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="shipped">Shipped</option>
                    <option value="delivered">Delivered</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Delivery Date</label>
                <input type="date" name="delivery_date" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="3"></textarea>
            </div>
            <div class="text-end">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Status</button>
            </div>
        </form>
    `;
    modal.show();
}

function submitDeliveryStatus(event, orderId) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    formData.append('order_id', orderId);

    fetch('update_delivery_status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                title: 'Updated!',
                text: 'Delivery status has been updated.',
                icon: 'success'
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                title: 'Error!',
                text: data.message || 'Failed to update delivery status.',
                icon: 'error'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            title: 'Error!',
            text: 'An error occurred while updating the delivery status.',
            icon: 'error'
        });
    });
}

function verifyPayment(orderId, paymentId) {
    if (!paymentId) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Payment record not found'
        });
        return;
    }

    Swal.fire({
        title: 'Verify Payment',
        text: 'Are you sure you want to verify this payment?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, verify payment',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('order_id', orderId);
            formData.append('payment_id', paymentId);
            formData.append('action', 'verify');

            fetch('verify_payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Payment Verified',
                        text: 'The payment has been successfully verified.',
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Verification Failed',
                        text: data.message || 'Failed to verify payment. Please try again.'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred while verifying the payment.'
                });
            });
        }
    });
}

function rejectPayment(orderId, paymentId) {
    if (!paymentId) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Payment record not found'
        });
        return;
    }

    Swal.fire({
        title: 'Reject Payment',
        html: `
            <form id="rejectForm">
                <div class="mb-3">
                    <label class="form-label">Rejection Reason</label>
                    <textarea class="form-control" id="rejectionReason" rows="3" required
                              placeholder="Please provide a reason for rejecting this payment"></textarea>
                </div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Reject Payment',
        cancelButtonText: 'Cancel',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            const reason = document.getElementById('rejectionReason').value;
            if (!reason.trim()) {
                Swal.showValidationMessage('Please provide a rejection reason');
                return false;
            }
            return reason;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('order_id', orderId);
            formData.append('payment_id', paymentId);
            formData.append('action', 'reject');
            formData.append('rejection_reason', result.value);

            fetch('verify_payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Payment Rejected',
                        text: 'The payment has been successfully rejected.',
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Rejection Failed',
                        text: data.message || 'Failed to reject payment. Please try again.'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred while rejecting the payment.'
                });
            });
        }
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
<?php
// Close the PHP tag
?> 