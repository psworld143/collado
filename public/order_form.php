<?php
require_once '../config/db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: login.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // First check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        if ($is_ajax) {
            echo json_encode(['success' => false, 'message' => 'You must be logged in to place an order.']);
            exit;
        }
        $error = "You must be logged in to place an order.";
        error_log("Order Error - User not logged in");
        header("Location: login.php");
        exit;
    }

    $name = htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8');
    $coffin_id = filter_input(INPUT_POST, 'coffin_id', FILTER_VALIDATE_INT);
    $delivery_date = $_POST['delivery_date'] ?? '';
    $delivery_address = htmlspecialchars($_POST['delivery_address'] ?? '', ENT_QUOTES, 'UTF-8');
    $contact_number = htmlspecialchars($_POST['contact_number'] ?? '', ENT_QUOTES, 'UTF-8');
    $special_instructions = htmlspecialchars($_POST['special_instructions'] ?? '', ENT_QUOTES, 'UTF-8');
    $payment_method = htmlspecialchars($_POST['payment_method'] ?? '', ENT_QUOTES, 'UTF-8');
    $reference_number = htmlspecialchars($_POST['reference_number'] ?? '', ENT_QUOTES, 'UTF-8');
    
    $error = '';
    $success = '';
    $order_id = null;

    // Check if this is an AJAX request
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

    // Validation
    if (empty($name) || empty($coffin_id) || empty($delivery_date) || empty($delivery_address) || empty($contact_number) || empty($payment_method) || empty($reference_number)) {
        $error = "Please fill in all required fields";
        error_log("Order Form Validation Failed - Missing Fields");
        if ($is_ajax) {
            echo json_encode(['success' => false, 'message' => $error]);
            exit;
        }
    } else {
        try {
            // Check if coffin exists and is in stock
            $stmt = $pdo->prepare("SELECT * FROM coffins WHERE id = ?");
            $stmt->execute([$coffin_id]);
            $coffin = $stmt->fetch();

            if (!$coffin) {
                $error = "Selected coffin does not exist";
                if ($is_ajax) {
                    echo json_encode(['success' => false, 'message' => $error]);
                    exit;
                }
            } elseif ($coffin['stock_quantity'] <= 0) {
                $error = "Selected coffin is out of stock";
                if ($is_ajax) {
                    echo json_encode(['success' => false, 'message' => $error]);
                    exit;
                }
            } else {
                // Start transaction
                $pdo->beginTransaction();

                try {
                    // Create order
                    $stmt = $pdo->prepare("
                        INSERT INTO orders (
                            user_id, coffin_id, order_number, quantity,
                            total_amount, delivery_date, delivery_address, contact_number, notes, 
                            payment_status, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending')
                    ");
                    
                    // Generate order number
                    $order_number = 'ORD-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    $params = [
                        $_SESSION['user_id'],
                        $coffin_id,
                        $order_number,
                        1, // quantity
                        $coffin['price'],
                        $delivery_date,
                        $delivery_address,
                        $contact_number,
                        $special_instructions
                    ];

                    $stmt->execute($params);
                    $order_id = $pdo->lastInsertId();

                    // Create payment record with pending status
                    $stmt = $pdo->prepare("
                        INSERT INTO payments (
                            order_id, payment_method, transaction_id, amount, status
                        ) VALUES (?, ?, ?, ?, 'pending')
                    ");
                    
                    $stmt->execute([
                        $order_id,
                        $payment_method,
                        $reference_number,
                        $coffin['price']
                    ]);

                    // Update stock quantity
                    $stmt = $pdo->prepare("
                        UPDATE coffins 
                        SET stock_quantity = stock_quantity - 1 
                        WHERE id = ? AND stock_quantity > 0
                    ");
                    $stmt->execute([$coffin_id]);

                    // Commit transaction
                    $pdo->commit();

                    $success = "Order placed successfully! Please wait for admin verification of your payment.";
                    
                    if ($is_ajax) {
                        echo json_encode([
                            'success' => true,
                            'order_id' => $order_id,
                            'message' => $success
                        ]);
                        exit;
                    } else {
                        header("Location: order_confirmation.php?id=" . $order_id);
                        exit;
                    }
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = "Database error: " . $e->getMessage();
                    error_log("Order Error - Database Exception: " . $e->getMessage());
                    if ($is_ajax) {
                        echo json_encode(['success' => false, 'message' => $error]);
                        exit;
                    }
                }
            }
        } catch (Exception $e) {
            $error = "An unexpected error occurred: " . $e->getMessage();
            error_log("Order Error - General Exception: " . $e->getMessage());
            if ($is_ajax) {
                echo json_encode(['success' => false, 'message' => $error]);
                exit;
            }
        }
    }

    if ($is_ajax) {
        echo json_encode([
            'success' => false,
            'message' => $error
        ]);
        exit;
    }
}

// Get coffin details
$coffin_id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM coffins WHERE id = ?");
$stmt->execute([$coffin_id]);
$coffin = $stmt->fetch();

if (!$coffin) {
    header("Location: coffin_catalog.php");
    exit;
}

// Now include the header after all potential redirects
include '../includes/header.php';

// Add SweetAlert2 CSS and JS
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">';
echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-lg">
            <div class="card-header bg-primary text-white">
                <h2 class="card-title text-center mb-0">
                    <i class="fas fa-shopping-cart"></i> Place Your Order
                </h2>
            </div>
            <div class="card-body p-4">
                <?php if (isset($error) && !empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($success) && !empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="coffin-details mb-4 p-4 bg-light rounded">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4 class="mb-2"><?= htmlspecialchars($coffin['name']) ?></h4>
                            <p class="text-muted mb-2"><?= htmlspecialchars($coffin['description']) ?></p>
                            <h5 class="text-primary mb-0">₱<?= number_format($coffin['price'], 2) ?></h5>
                        </div>
                        <div class="col-md-4 text-center">
                            <?php if (!empty($coffin['image'])): ?>
                                <img src="<?= htmlspecialchars($coffin['image']) ?>" 
                                     class="img-fluid rounded" 
                                     alt="<?= htmlspecialchars($coffin['name']) ?>"
                                     style="max-height: 150px; object-fit: contain;">
                            <?php else: ?>
                                <div class="bg-white p-3 rounded">
                                    <i class="fas fa-box fa-3x text-muted"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="coffin_id" value="<?= $coffin_id ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fas fa-user"></i> Full Name
                            </label>
                            <input type="text" name="name" class="form-control" required
                                   value="<?= htmlspecialchars($_SESSION['username'] ?? '') ?>"
                                   placeholder="Enter your full name">
                            <div class="invalid-feedback">
                                Please enter your full name.
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fas fa-phone"></i> Contact Number
                            </label>
                            <input type="tel" name="contact_number" class="form-control" required
                                   placeholder="Enter your contact number">
                            <div class="invalid-feedback">
                                Please enter your contact number.
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-map-marker-alt"></i> Delivery Address
                        </label>
                        <textarea name="delivery_address" class="form-control" required
                                  rows="3" placeholder="Enter complete delivery address"></textarea>
                        <div class="invalid-feedback">
                            Please enter the delivery address.
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fas fa-calendar"></i> Delivery Date
                            </label>
                            <input type="date" name="delivery_date" class="form-control" required
                                   min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                                   placeholder="Select delivery date">
                            <div class="invalid-feedback">
                                Please select a valid delivery date.
                            </div>
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle"></i> Delivery date must be at least 1 day from today.
                            </small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fas fa-clock"></i> Preferred Time
                            </label>
                            <select name="delivery_time" class="form-select">
                                <option value="morning">Morning (8AM - 12PM)</option>
                                <option value="afternoon">Afternoon (1PM - 5PM)</option>
                                <option value="evening">Evening (6PM - 8PM)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-comment"></i> Special Instructions
                        </label>
                        <textarea name="special_instructions" class="form-control"
                                  rows="2" placeholder="Any special instructions or requests"></textarea>
                    </div>

                    <div class="mb-4">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Order Summary:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Coffin Design: <?= htmlspecialchars($coffin['name']) ?></li>
                                <li>Price: ₱<?= number_format($coffin['price'], 2) ?></li>
                                <li>Payment Status: <span class="badge bg-warning">Pending</span></li>
                            </ul>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">
                            <i class="fas fa-credit-card"></i> Payment Method
                        </label>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="bankPayment" value="bank_transfer" checked>
                                    <label class="form-check-label" for="bankPayment">
                                        <i class="fas fa-university"></i> Bank Transfer
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="gcashPayment" value="cash">
                                    <label class="form-check-label" for="gcashPayment">
                                        <i class="fas fa-mobile-alt"></i> GCash
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-check"></i> Proceed to Payment
                        </button>
                        <a href="coffin_catalog.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Catalog
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Payment Simulation Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Payment Simulation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="bankPaymentDetails" class="payment-details">
                    <h6 class="mb-3">Bank Transfer Details</h6>
                    <div class="alert alert-info">
                        <p class="mb-1"><strong>Bank:</strong> Sample Bank</p>
                        <p class="mb-1"><strong>Account Number:</strong> 1234-5678-9012-3456</p>
                        <p class="mb-1"><strong>Account Name:</strong> Sample Company</p>
                        <p class="mb-0"><strong>Amount:</strong> ₱<?= number_format($coffin['price'], 2) ?></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reference Number</label>
                        <input type="text" class="form-control" id="bankReference" placeholder="Enter reference number">
                        <div class="form-text">
                            <i class="fas fa-info-circle"></i> How to get your reference number:
                            <ol class="mt-2 mb-0">
                                <li>Log in to your bank's mobile app or website</li>
                                <li>Go to "Send Money" or "Transfer"</li>
                                <li>Enter the bank details above</li>
                                <li>After successful transfer, you'll receive a reference number</li>
                                <li>Enter that reference number here</li>
                            </ol>
                        </div>
                    </div>
                </div>
                <div id="gcashPaymentDetails" class="payment-details d-none">
                    <h6 class="mb-3">GCash Payment</h6>
                    <div class="alert alert-info">
                        <p class="mb-1"><strong>GCash Number:</strong> 0912-345-6789</p>
                        <p class="mb-1"><strong>Account Name:</strong> Sample Company</p>
                        <p class="mb-0"><strong>Amount:</strong> ₱<?= number_format($coffin['price'], 2) ?></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">GCash Reference Number</label>
                        <input type="text" class="form-control" id="gcashReference" placeholder="Enter GCash reference number">
                        <div class="form-text">
                            <i class="fas fa-info-circle"></i> How to get your GCash reference number:
                            <ol class="mt-2 mb-0">
                                <li>Open your GCash app</li>
                                <li>Tap "Send Money"</li>
                                <li>Enter the GCash number above</li>
                                <li>Enter the exact amount shown</li>
                                <li>After sending, you'll see a reference number in the receipt</li>
                                <li>Enter that reference number here</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmPayment">Confirm Payment</button>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
                console.log('Form validation failed:', {
                    form: form.id,
                    elements: Array.from(form.elements).map(el => ({
                        name: el.name,
                        value: el.value,
                        validity: el.validity
                    }))
                });
            }
            form.classList.add('was-validated')
        }, false)
    })
})()

// Show SweetAlert for PHP messages
<?php if (isset($error) && !empty($error)): ?>
Swal.fire({
    icon: 'error',
    title: 'Order Failed',
    text: <?= json_encode($error) ?>,
    confirmButtonColor: '#dc3545'
});
<?php endif; ?>

<?php if (isset($success) && !empty($success)): ?>
Swal.fire({
    icon: 'success',
    title: 'Success!',
    text: <?= json_encode($success) ?>,
    showConfirmButton: false,
    timer: 2000
}).then(() => {
    window.location.href = 'order_confirmation.php?id=<?= $pdo->lastInsertId() ?>';
});
<?php endif; ?>

// Date validation
document.querySelector('input[type="date"]').addEventListener('change', function(e) {
    const selectedDate = new Date(e.target.value);
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    
    if (selectedDate < tomorrow) {
        e.target.setCustomValidity('Please select a date at least 1 day from today');
        Swal.fire({
            icon: 'warning',
            title: 'Invalid Date',
            text: 'Please select a date at least 1 day from today',
            confirmButtonColor: '#ffc107'
        });
    } else {
        e.target.setCustomValidity('');
    }
});

// Phone number validation
document.querySelector('input[name="contact_number"]').addEventListener('input', function(e) {
    const phone = e.target.value.replace(/\D/g, '');
    if (phone.length < 10) {
        e.target.setCustomValidity('Please enter a valid phone number (at least 10 digits)');
    } else {
        e.target.setCustomValidity('');
    }
});

// Payment method selection
document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const bankDetails = document.getElementById('bankPaymentDetails');
        const gcashDetails = document.getElementById('gcashPaymentDetails');
        
        if (this.value === 'bank_transfer') {
            bankDetails.classList.remove('d-none');
            gcashDetails.classList.add('d-none');
        } else {
            bankDetails.classList.add('d-none');
            gcashDetails.classList.remove('d-none');
        }
    });
});

// Form submission with payment simulation
document.querySelector('form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (this.checkValidity()) {
        const phone = this.querySelector('input[name="contact_number"]').value.replace(/\D/g, '');
        if (phone.length < 10) {
            Swal.fire({
                icon: 'warning',
                title: 'Invalid Phone Number',
                text: 'Please enter a valid phone number (at least 10 digits)',
                confirmButtonColor: '#ffc107'
            });
            return;
        }

        // Show payment modal
        const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
        paymentModal.show();
    } else {
        this.classList.add('was-validated');
    }
});

// Handle payment confirmation
document.getElementById('confirmPayment').addEventListener('click', function() {
    const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
    const referenceInput = paymentMethod === 'bank_transfer' ? 
        document.getElementById('bankReference') : 
        document.getElementById('gcashReference');
    
    if (!referenceInput.value.trim()) {
        Swal.fire({
            icon: 'warning',
            title: 'Reference Required',
            text: 'Please enter the payment reference number',
            confirmButtonColor: '#ffc107'
        });
        return;
    }

    // Simulate payment processing
    Swal.fire({
        title: 'Processing Payment',
        html: 'Please wait while we process your payment...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // First submit the form to create the order
    const form = document.querySelector('form');
    const formData = new FormData(form);
    formData.append('payment_method', paymentMethod);
    formData.append('reference_number', referenceInput.value.trim());
    
    fetch('order_form.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            throw new Error(data.message || 'Failed to create order');
        }

        Swal.fire({
            icon: 'success',
            title: 'Order Placed Successfully!',
            html: `
                <p>Your order has been placed and is pending payment verification.</p>
                <p>Please keep your payment reference number for verification.</p>
                <p>Reference Number: <strong>${referenceInput.value.trim()}</strong></p>
            `,
            confirmButtonText: 'View Order Status',
            showCancelButton: true,
            cancelButtonText: 'Close'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'order_history.php';
            } else {
                window.location.href = 'coffin_catalog.php';
            }
        });
    })
    .catch(error => {
        console.error('Payment Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Payment Error',
            text: error.message || 'There was an error processing your payment. Please try again.',
            confirmButtonColor: '#dc3545'
        });
    });
});

// Log any PHP errors
<?php if (isset($error) && !empty($error)): ?>
console.error('PHP Error:', {
    message: <?= json_encode($error) ?>,
    timestamp: new Date().toISOString(),
    formData: {
        name: <?= json_encode($name ?? '') ?>,
        coffin_id: <?= json_encode($coffin_id ?? '') ?>,
        delivery_date: <?= json_encode($delivery_date ?? '') ?>,
        user_id: <?= json_encode($_SESSION['user_id'] ?? '') ?>
    },
    requestInfo: {
        method: <?= json_encode($_SERVER['REQUEST_METHOD']) ?>,
        uri: <?= json_encode($_SERVER['REQUEST_URI']) ?>,
        query: <?= json_encode($_GET) ?>,
        post: <?= json_encode($_POST) ?>
    }
});
<?php endif; ?>

// Log any PHP success messages
<?php if (isset($success) && !empty($success)): ?>
console.log('PHP Success:', {
    message: <?= json_encode($success) ?>,
    timestamp: new Date().toISOString(),
    orderDetails: {
        coffin_id: <?= json_encode($coffin_id ?? '') ?>,
        delivery_date: <?= json_encode($delivery_date ?? '') ?>
    }
});
<?php endif; ?>
</script>

<style>
.payment-details {
    transition: all 0.3s ease;
}
</style>

<?php include '../includes/footer.php'; ?>
