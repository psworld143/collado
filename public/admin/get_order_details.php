<?php
require_once '../../config/db.php';
session_start();

// Add the getDeliveryStatusColor function
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

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo '<div class="alert alert-danger">Unauthorized access</div>';
    exit;
}

// Check if order ID is provided
if (!isset($_GET['id'])) {
    echo '<div class="alert alert-danger">Order ID is required</div>';
    exit;
}

$order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

try {
    // Get order details
    $stmt = $pdo->prepare("
        SELECT o.*, 
               u.name as customer_name,
               u.phone as customer_phone,
               c.name as coffin_name,
               c.price as coffin_price,
               c.image as coffin_image,
               p.id as payment_id,
               p.payment_method,
               p.transaction_id as payment_reference,
               p.payment_date,
               p.status as payment_status
        FROM orders o
        JOIN users u ON o.user_id = u.id
        JOIN coffins c ON o.coffin_id = c.id
        LEFT JOIN payments p ON o.id = p.order_id
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if (!$order) {
        header("Location: manage_orders.php");
        exit;
    }
?>

<div class="order-details">
    <div class="row mb-4">
        <div class="col-md-6">
            <h6 class="border-bottom pb-2">Customer Information</h6>
            <p class="mb-1"><strong>Name:</strong> <?= htmlspecialchars($order['customer_name'] ?? 'N/A') ?></p>
            <p class="mb-1"><strong>Phone:</strong> <?= htmlspecialchars($order['contact_number'] ?? 'N/A') ?></p>
            <p class="mb-1"><strong>Address:</strong> <?= htmlspecialchars($order['delivery_address'] ?? 'N/A') ?></p>
        </div>
        <div class="col-md-6">
            <h6 class="border-bottom pb-2">Order Information</h6>
            <p class="mb-1"><strong>Order Number:</strong> <?= htmlspecialchars($order['order_number'] ?? 'N/A') ?></p>
            <p class="mb-1"><strong>Order Date:</strong> <?= isset($order['created_at']) ? date('F j, Y H:i', strtotime($order['created_at'])) : 'N/A' ?></p>
        </div>
    </div>

    <!-- Payment Information Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0">
                        <i class="fas fa-credit-card me-2"></i>Payment Information
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-2">
                                <strong>Payment Status:</strong><br>
                                <span class="badge bg-<?= ($order['payment_status'] ?? 'pending') === 'paid' ? 'success' : 
                                    (($order['payment_status'] ?? 'pending') === 'cancelled' ? 'danger' : 'warning') ?> fs-6">
                                    <?= ucfirst($order['payment_status'] ?? 'pending') ?>
                                </span>
                            </p>
                            <p class="mb-2">
                                <strong>Payment Method:</strong><br>
                                <span class="badge bg-info fs-6">
                                    <i class="fas fa-<?= ($order['payment_method'] ?? '') === 'bank_transfer' ? 'university' : 
                                        (($order['payment_method'] ?? '') === 'credit_card' ? 'credit-card' : 'money-bill') ?> me-1"></i>
                                    <?= ucfirst(str_replace('_', ' ', $order['payment_method'] ?? 'N/A')) ?>
                                </span>
                            </p>
                            <p class="mb-2">
                                <strong>Payment Date:</strong><br>
                                <span class="text-muted">
                                    <i class="fas fa-calendar-alt me-1"></i>
                                    <?= isset($order['payment_date']) ? date('F j, Y H:i', strtotime($order['payment_date'])) : 'N/A' ?>
                                </span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-2">
                                <strong>Payment Reference:</strong><br>
                                <span class="text-monospace bg-light px-2 py-1 rounded">
                                    <?= htmlspecialchars($order['payment_reference'] ?? 'N/A') ?>
                                </span>
                            </p>
                            <p class="mb-2">
                                <strong>Amount Paid:</strong><br>
                                <span class="text-success fw-bold">
                                    ₱<?= number_format($order['total_amount'] ?? 0, 2) ?>
                                </span>
                            </p>
                            <?php if (!empty($order['notes'])): ?>
                            <p class="mb-2">
                                <strong>Payment Notes:</strong><br>
                                <span class="text-muted">
                                    <?= nl2br(htmlspecialchars($order['notes'])) ?>
                                </span>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($order['payment_status'] === 'pending'): ?>
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card border-warning">
                                <div class="card-header bg-warning text-dark">
                                    <h6 class="mb-0">
                                        <i class="fas fa-check-circle me-2"></i>Payment Verification
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Payment Reference</label>
                                            <input type="text" class="form-control" id="paymentReference" 
                                                   value="<?= htmlspecialchars($order['payment_reference'] ?? '') ?>"
                                                   readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">&nbsp;</label>
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-success" id="verifyPaymentBtn" onclick="
                                                    console.log('Verify clicked');
                                                    Swal.fire({
                                                        title: 'Verify Payment?',
                                                        text: 'Are you sure you want to verify this payment?',
                                                        icon: 'question',
                                                        showCancelButton: true,
                                                        confirmButtonColor: '#28a745',
                                                        cancelButtonColor: '#6c757d',
                                                        confirmButtonText: 'Yes, verify payment',
                                                        cancelButtonText: 'Cancel'
                                                    }).then((result) => {
                                                        if (result.isConfirmed) {
                                                            const form = document.getElementById('verifyPaymentForm');
                                                            const formData = new FormData(form);
                                                            formData.append('action', 'verify');
                                                            
                                                            Swal.fire({
                                                                title: 'Verifying Payment',
                                                                text: 'Please wait...',
                                                                allowOutsideClick: false,
                                                                didOpen: () => {
                                                                    Swal.showLoading();
                                                                }
                                                            });
                                                            
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
                                                                        window.location.reload();
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
                                                ">
                                                    <i class="fas fa-check me-1"></i> Verify Payment
                                                </button>
                                                <button type="button" class="btn btn-danger rejectPaymentBtn" data-order-id="<?= $order['id'] ?>" data-payment-id="<?= $order['payment_id'] ?>">
                                                    <i class="fas fa-times me-1"></i> Reject Payment
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <h6 class="border-bottom pb-2">Order Items</h6>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <?php if (!empty($order['coffin_image'])): ?>
                                        <img src="../../<?= htmlspecialchars($order['coffin_image']) ?>" 
                                             class="me-2" style="width: 50px; height: 50px; object-fit: cover;">
                                    <?php endif; ?>
                                    <?= htmlspecialchars($order['coffin_name'] ?? 'N/A') ?>
                                </div>
                            </td>
                            <td>₱<?= number_format($order['coffin_price'] ?? 0, 2) ?></td>
                            <td>1</td>
                            <td>₱<?= number_format($order['total_amount'] ?? 0, 2) ?></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3" class="text-end">Total:</th>
                            <th>₱<?= number_format($order['total_amount'] ?? 0, 2) ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <?php if (!empty($order['notes'])): ?>
    <div class="row mb-4">
        <div class="col-12">
            <h6 class="border-bottom pb-2">Notes</h6>
            <p class="mb-0"><?= nl2br(htmlspecialchars($order['notes'])) ?></p>
        </div>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-12">
            <h6 class="border-bottom pb-2">Delivery Information</h6>
            <p class="mb-1"><strong>Delivery Date:</strong> <?= isset($order['delivery_date']) ? date('F j, Y', strtotime($order['delivery_date'])) : 'N/A' ?></p>
            <p class="mb-1"><strong>Delivery Address:</strong> <?= htmlspecialchars($order['delivery_address'] ?? 'N/A') ?></p>
        </div>
    </div>
</div>

<!-- Payment Verification Modal -->
<div class="modal fade" id="verifyPaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Verify Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="verifyPaymentForm">
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                    <input type="hidden" name="payment_id" value="<?= $order['payment_id'] ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Payment Reference</label>
                        <input type="text" class="form-control" name="reference_number" 
                               value="<?= htmlspecialchars($order['payment_reference'] ?? '') ?>" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Verification Notes</label>
                        <textarea class="form-control" name="verification_notes" rows="3" 
                                  placeholder="Add any notes about the payment verification"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirmVerifyPayment">
                    <i class="fas fa-check"></i> Verify Payment
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Payment Rejection Modal -->
<div class="modal fade" id="rejectPaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="rejectPaymentForm">
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                    <input type="hidden" name="payment_id" value="<?= $order['payment_id'] ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Rejection Reason</label>
                        <textarea class="form-control" name="rejection_reason" rows="3" required
                                  placeholder="Please provide a reason for rejecting this payment"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmRejectPayment">
                    <i class="fas fa-times"></i> Reject Payment
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Wait for the document to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Get the buttons
    const verifyBtn = document.getElementById('verifyPaymentBtn');
    const rejectBtn = document.getElementById('rejectPaymentBtn');
    const confirmVerifyBtn = document.getElementById('confirmVerifyPayment');
    const confirmRejectBtn = document.getElementById('confirmRejectPayment');

    // Add click event for verify button
    if (verifyBtn) {
        verifyBtn.addEventListener('click', function() {
            console.log('Verify button clicked');
            const verifyModal = new bootstrap.Modal(document.getElementById('verifyPaymentModal'));
            verifyModal.show();
        });
    }

    // Add click event for reject button
    if (rejectBtn) {
        rejectBtn.addEventListener('click', function() {
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
                    const form = document.getElementById('rejectPaymentForm');
                    const formData = new FormData(form);
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
                                window.location.reload();
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
        });
    }

    // Add click event for confirm verify button
    if (confirmVerifyBtn) {
        confirmVerifyBtn.addEventListener('click', function() {
            console.log('Confirm verify clicked');
            const form = document.getElementById('verifyPaymentForm');
            const formData = new FormData(form);
            formData.append('action', 'verify');
            
            // Show loading state
            Swal.fire({
                title: 'Verifying Payment',
                text: 'Please wait...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
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
                        window.location.reload();
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
        });
    }

    // Add click event for confirm reject button
    if (confirmRejectBtn) {
        confirmRejectBtn.addEventListener('click', function() {
            console.log('Confirm reject clicked');
            const form = document.getElementById('rejectPaymentForm');
            const formData = new FormData(form);
            formData.append('action', 'reject');
            
            if (!formData.get('rejection_reason').trim()) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Reason Required',
                    text: 'Please provide a reason for rejecting the payment.'
                });
                return;
            }
            
            // Show confirmation dialog
            Swal.fire({
                title: 'Reject Payment?',
                text: 'Are you sure you want to reject this payment? This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, reject payment',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading state
                    Swal.fire({
                        title: 'Rejecting Payment',
                        text: 'Please wait...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
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
                                window.location.reload();
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
        });
    }

    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    document.querySelectorAll('.rejectPaymentBtn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const orderId = btn.getAttribute('data-order-id');
            const paymentId = btn.getAttribute('data-payment-id');
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
                                window.location.reload();
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
        });
    });
});
</script>

<?php
} catch (PDOException $e) {
    error_log("Error fetching order details: " . $e->getMessage());
    echo '<div class="alert alert-danger">Error loading order details</div>';
} catch (Exception $e) {
    error_log("Error fetching order details: " . $e->getMessage());
    echo '<div class="alert alert-danger">An unexpected error occurred</div>';
}
?> 