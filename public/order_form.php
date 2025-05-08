<?php
require_once '../config/db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: login.php");
    exit;
}

include '../includes/header.php';

// Add SweetAlert2 CSS and JS
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">';
echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // First check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        $error = "You must be logged in to place an order.";
        error_log("Order Error - User not logged in");
        header("Location: login.php");
        exit;
    }

    $name = htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8');
    $coffin_id = filter_input(INPUT_POST, 'coffin_id', FILTER_VALIDATE_INT);
    $delivery_date = $_POST['delivery_date'];
    $delivery_address = htmlspecialchars($_POST['delivery_address'] ?? '', ENT_QUOTES, 'UTF-8');
    $contact_number = htmlspecialchars($_POST['contact_number'] ?? '', ENT_QUOTES, 'UTF-8');
    $special_instructions = htmlspecialchars($_POST['special_instructions'] ?? '', ENT_QUOTES, 'UTF-8');
    $error = '';
    $success = '';

    // Log form submission data
    error_log("Order Form Submission - Data: " . json_encode([
        'name' => $name,
        'coffin_id' => $coffin_id,
        'delivery_date' => $delivery_date,
        'delivery_address' => $delivery_address,
        'contact_number' => $contact_number,
        'user_id' => $_SESSION['user_id'] ?? null
    ]));

    // Validation
    if (empty($name) || empty($coffin_id) || empty($delivery_date) || empty($delivery_address) || empty($contact_number)) {
        $error = "Please fill in all required fields";
        error_log("Order Form Validation Failed - Missing Fields: " . json_encode([
            'name' => empty($name),
            'coffin_id' => empty($coffin_id),
            'delivery_date' => empty($delivery_date),
            'delivery_address' => empty($delivery_address),
            'contact_number' => empty($contact_number)
        ]));
    } else {
        try {
            // Check if coffin exists and is in stock
            $stmt = $pdo->prepare("SELECT * FROM coffins WHERE id = ?");
            $stmt->execute([$coffin_id]);
            $coffin = $stmt->fetch();

            if (!$coffin) {
                $error = "Selected coffin does not exist";
                error_log("Order Failed - Coffin not found: " . $coffin_id);
            } elseif ($coffin['stock_quantity'] <= 0) {
                $error = "Selected coffin is out of stock";
                error_log("Order Failed - Coffin out of stock: " . $coffin_id);
            } else {
                // Start transaction
                $pdo->beginTransaction();

                try {
                    // Create order
                    $stmt = $pdo->prepare("
                        INSERT INTO orders (
                            user_id, coffin_id, order_number, quantity,
                            total_amount, delivery_date, notes, 
                            payment_status, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                    ");
                    
                    // Generate order number (you can customize this format)
                    $order_number = 'ORD-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    $params = [
                        $_SESSION['user_id'],
                        $coffin_id,
                        $order_number,
                        1, // quantity
                        $coffin['price'],
                        $delivery_date,
                        $special_instructions
                    ];

                    // Log the SQL parameters
                    error_log("Order Insert Parameters: " . json_encode($params));

                    $stmt->execute($params);
                    $order_id = $pdo->lastInsertId();

                    // Update stock quantity
                    $stmt = $pdo->prepare("
                        UPDATE coffins 
                        SET stock_quantity = stock_quantity - 1 
                        WHERE id = ? AND stock_quantity > 0
                    ");
                    $stmt->execute([$coffin_id]);

                    // Commit transaction
                    $pdo->commit();

                    $success = "Order placed successfully!";
                    error_log("Order Success - Order ID: " . $order_id);
                    
                    // Redirect to order confirmation page
                    header("refresh:2;url=order_confirmation.php?id=" . $order_id);
                    exit;
                } catch (PDOException $e) {
                    // Rollback transaction on error
                    $pdo->rollBack();
                    
                    // Log detailed error information
                    error_log("Order Error - Database Exception: " . $e->getMessage());
                    error_log("Order Error - SQL State: " . $e->getCode());
                    error_log("Order Error - Error Info: " . json_encode($e->errorInfo));
                    
                    // Show more specific error message
                    if ($e->getCode() == '23000') {
                        $error = "Database constraint error. Please check your input data.";
                    } else {
                        $error = "Database error: " . $e->getMessage();
                    }
                }
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
            error_log("Order Error - Database Exception: " . $e->getMessage());
            error_log("Order Error - Stack Trace: " . $e->getTraceAsString());
        } catch (Exception $e) {
            $error = "An unexpected error occurred: " . $e->getMessage();
            error_log("Order Error - General Exception: " . $e->getMessage());
            error_log("Order Error - Stack Trace: " . $e->getTraceAsString());
        }
    }
}

// Get coffin details
$coffin_id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM coffins WHERE id = ?");
$stmt->execute([$coffin_id]);
$coffin = $stmt->fetch();

if (!$coffin) {
    echo '<div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> Invalid coffin selection.
            <a href="coffin_catalog.php" class="alert-link">Return to catalog</a>
          </div>';
    exit;
}
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

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-check"></i> Confirm Order
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
        e.target.setCustomValidity('Please enter a valid phone number');
        Swal.fire({
            icon: 'warning',
            title: 'Invalid Phone Number',
            text: 'Please enter a valid phone number (at least 10 digits)',
            confirmButtonColor: '#ffc107'
        });
    } else {
        e.target.setCustomValidity('');
    }
});

// Form submission confirmation
document.querySelector('form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (this.checkValidity()) {
        Swal.fire({
            title: 'Confirm Order',
            text: 'Are you sure you want to place this order?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#dc3545',
            confirmButtonText: 'Yes, place order',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                this.submit();
            }
        });
    } else {
        this.classList.add('was-validated');
    }
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

<?php include '../includes/footer.php'; ?>
