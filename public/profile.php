<?php
include '../config/db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$error = '';
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    try {
        // Start transaction
        $pdo->beginTransaction();

        // Check if email is already taken by another user
        if ($email !== $_SESSION['user_email']) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                throw new Exception("Email already exists");
            }
        }

        // Update basic info
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
        $stmt->execute([$name, $email, $phone, $_SESSION['user_id']]);

        // Update password if provided
        if (!empty($current_password)) {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();

            if (!password_verify($current_password, $user['password'])) {
                throw new Exception("Current password is incorrect");
            }

            if (empty($new_password)) {
                throw new Exception("New password is required");
            }

            if (strlen($new_password) < 8) {
                throw new Exception("New password must be at least 8 characters long");
            }

            if ($new_password !== $confirm_password) {
                throw new Exception("New passwords do not match");
            }

            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $_SESSION['user_id']]);
        }

        // Commit transaction
        $pdo->commit();

        // Update session
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;

        $success = "Profile updated successfully";
    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Get user data
try {
    $stmt = $pdo->prepare("
        SELECT u.*, 
               COUNT(DISTINCT o.id) as total_orders,
               SUM(CASE WHEN o.status = 'pending' THEN 1 ELSE 0 END) as pending_orders
        FROM users u
        LEFT JOIN orders o ON u.id = o.user_id
        WHERE u.id = ?
        GROUP BY u.id
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    $error = "An error occurred while fetching user data";
    error_log("Profile error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Coffin Ordering System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="/collado/public/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include '../includes/header.php'; ?>

    <div class="container py-5">
        <div class="row">
            <!-- Profile Sidebar -->
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm border-0 rounded-lg">
                    <div class="card-body text-center">
                        <div class="mb-4">
                            <div class="avatar-circle mx-auto mb-3">
                                <i class="fas fa-user fa-3x text-white"></i>
                            </div>
                            <h4 class="mb-1"><?php echo htmlspecialchars($user['name']); ?></h4>
                            <p class="text-muted mb-0"><?php echo htmlspecialchars($user['email']); ?></p>
                        </div>
                        <hr>
                        <div class="row text-center">
                            <div class="col-6">
                                <h5 class="mb-1"><?php echo $user['total_orders']; ?></h5>
                                <small class="text-muted">Total Orders</small>
                            </div>
                            <div class="col-6">
                                <h5 class="mb-1"><?php echo $user['pending_orders']; ?></h5>
                                <small class="text-muted">Pending Orders</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Content -->
            <div class="col-md-8">
                <div class="card shadow-sm border-0 rounded-lg">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            <i class="fas fa-user-cog"></i> Account Settings
                        </h5>
                    </div>
                    <div class="card-body">
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

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="fas fa-user"></i> Full Name
                                </label>
                                <input type="text" name="name" class="form-control" required
                                       value="<?php echo htmlspecialchars($user['name']); ?>">
                                <div class="invalid-feedback">
                                    Please enter your full name.
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="fas fa-envelope"></i> Email Address
                                </label>
                                <input type="email" name="email" class="form-control" required
                                       value="<?php echo htmlspecialchars($user['email']); ?>">
                                <div class="invalid-feedback">
                                    Please enter a valid email address.
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="fas fa-phone"></i> Phone Number
                                </label>
                                <input type="tel" name="phone" class="form-control" required
                                       value="<?php echo htmlspecialchars($user['phone']); ?>">
                                <div class="invalid-feedback">
                                    Please enter your phone number.
                                </div>
                            </div>

                            <hr class="my-4">

                            <h6 class="mb-3">Change Password</h6>
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-lock"></i> Current Password
                                </label>
                                <div class="input-group">
                                    <input type="password" name="current_password" class="form-control"
                                           placeholder="Enter current password">
                                    <button class="btn btn-outline-secondary toggle-password" type="button">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-lock"></i> New Password
                                </label>
                                <div class="input-group">
                                    <input type="password" name="new_password" class="form-control"
                                           minlength="8" placeholder="Enter new password">
                                    <button class="btn btn-outline-secondary toggle-password" type="button">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">
                                    Leave blank to keep current password. Must be at least 8 characters long.
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="fas fa-lock"></i> Confirm New Password
                                </label>
                                <div class="input-group">
                                    <input type="password" name="confirm_password" class="form-control"
                                           minlength="8" placeholder="Confirm new password">
                                    <button class="btn btn-outline-secondary toggle-password" type="button">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
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

        // Password visibility toggle
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function() {
                const input = this.previousElementSibling;
                const icon = this.querySelector('i');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });

        // Password match validation
        const newPassword = document.querySelector('input[name="new_password"]');
        const confirmPassword = document.querySelector('input[name="confirm_password"]');

        function validatePassword() {
            if (newPassword.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity("Passwords don't match");
            } else {
                confirmPassword.setCustomValidity('');
            }
        }

        newPassword.addEventListener('change', validatePassword);
        confirmPassword.addEventListener('keyup', validatePassword);
    </script>
</body>
</html> 