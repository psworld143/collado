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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $coffin_id = filter_input(INPUT_POST, 'coffin_id', FILTER_VALIDATE_INT);
    $delivery_date = $_POST['delivery_date'];
    $delivery_address = filter_input(INPUT_POST, 'delivery_address', FILTER_SANITIZE_STRING);
    $contact_number = filter_input(INPUT_POST, 'contact_number', FILTER_SANITIZE_STRING);
    $special_instructions = filter_input(INPUT_POST, 'special_instructions', FILTER_SANITIZE_STRING);
    $error = '';
    $success = '';

    // Validation
    if (empty($name) || empty($coffin_id) || empty($delivery_date) || empty($delivery_address) || empty($contact_number)) {
        $error = "Please fill in all required fields";
    } else {
        try {
            // Check if coffin exists and is in stock
            $stmt = $pdo->prepare("SELECT * FROM coffin_designs WHERE id = ? AND in_stock = 1");
            $stmt->execute([$coffin_id]);
            $coffin = $stmt->fetch();

            if (!$coffin) {
                $error = "Selected coffin is not available";
            } else {
                // Create order
                $stmt = $pdo->prepare("
                    INSERT INTO orders (
                        user_id, coffin_id, customer_name, delivery_date, 
                        delivery_address, contact_number, special_instructions, 
                        total_amount, payment_status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $coffin_id,
                    $name,
                    $delivery_date,
                    $delivery_address,
                    $contact_number,
                    $special_instructions,
                    $coffin['price']
                ]);

                $success = "Order placed successfully!";
                
                // Redirect to order confirmation page
                header("refresh:2;url=order_confirmation.php?id=" . $pdo->lastInsertId());
            }
        } catch (PDOException $e) {
            $error = "An error occurred. Please try again later.";
            error_log("Order error: " . $e->getMessage());
}
    }
}

// Get coffin details
$coffin_id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM coffin_designs WHERE id = ?");
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
            }
            form.classList.add('was-validated')
        }, false)
    })
})()

// Date validation
document.querySelector('input[type="date"]').addEventListener('change', function(e) {
    const selectedDate = new Date(e.target.value);
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    
    if (selectedDate < tomorrow) {
        e.target.setCustomValidity('Please select a date at least 1 day from today');
    } else {
        e.target.setCustomValidity('');
    }
});

// Phone number validation
document.querySelector('input[name="contact_number"]').addEventListener('input', function(e) {
    const phone = e.target.value.replace(/\D/g, '');
    if (phone.length < 10) {
        e.target.setCustomValidity('Please enter a valid phone number');
    } else {
        e.target.setCustomValidity('');
    }
});
</script>

<?php include '../includes/footer.php'; ?>
