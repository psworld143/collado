<?php
require_once '../../config/db.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$success = false;
$errors = [];

// Get admin details
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Profile error: " . $e->getMessage());
    $errors[] = "An error occurred while fetching profile data.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate inputs
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }

    // Check if email is already taken by another user
    if ($email !== $admin['email']) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $_SESSION['user_id']]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Email is already taken";
        }
    }

    // Handle password change if requested
    if (!empty($new_password)) {
        if (empty($current_password)) {
            $errors[] = "Current password is required to change password";
        } elseif (!password_verify($current_password, $admin['password'])) {
            $errors[] = "Current password is incorrect";
        } elseif (strlen($new_password) < 8) {
            $errors[] = "New password must be at least 8 characters long";
        } elseif ($new_password !== $confirm_password) {
            $errors[] = "New passwords do not match";
        }
    }

    if (empty($errors)) {
        try {
            if (!empty($new_password)) {
                // Update with new password
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET name = ?, email = ?, password = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$name, $email, password_hash($new_password, PASSWORD_DEFAULT), $_SESSION['user_id']]);
            } else {
                // Update without changing password
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET name = ?, email = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$name, $email, $_SESSION['user_id']]);
            }

            // Update session data
            $_SESSION['name'] = $name;
            $_SESSION['email'] = $email;

            // Log the activity
            $stmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action_type, details, ip_address, created_at)
                VALUES (?, 'update', 'Updated profile information', ?, NOW())
            ");
            $stmt->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);

            $success = true;
        } catch (PDOException $e) {
            error_log("Profile update error: " . $e->getMessage());
            $errors[] = "An error occurred while updating profile.";
        }
    }
}

include 'includes/admin_nav.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0">
                    <i class="fas fa-user-circle"></i> My Profile
                </h2>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> Profile updated successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="fas fa-user-circle fa-5x text-primary"></i>
                    </div>
                    <h5 class="card-title"><?= htmlspecialchars($admin['name']) ?></h5>
                    <p class="text-muted"><?= htmlspecialchars($admin['email']) ?></p>
                    <p class="mb-0">
                        <span class="badge bg-primary">Administrator</span>
                    </p>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-body">
                    <h6 class="card-title">Account Information</h6>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="fas fa-calendar-alt text-muted me-2"></i>
                            Joined: <?= date('M d, Y', strtotime($admin['created_at'])) ?>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-clock text-muted me-2"></i>
                            Last Updated: <?= date('M d, Y', strtotime($admin['updated_at'])) ?>
                        </li>
                        <li>
                            <i class="fas fa-shield-alt text-muted me-2"></i>
                            Role: Administrator
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Edit Profile</h5>
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?= htmlspecialchars($admin['name']) ?>" required>
                            <div class="invalid-feedback">Please enter your name.</div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?= htmlspecialchars($admin['email']) ?>" required>
                            <div class="invalid-feedback">Please enter a valid email address.</div>
                        </div>

                        <hr>

                        <h6 class="mb-3">Change Password</h6>
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password">
                            <div class="form-text">Leave blank if you don't want to change the password.</div>
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" 
                                   minlength="8">
                            <div class="form-text">Must be at least 8 characters long.</div>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </form>
                </div>
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
</script>

<?php include '../../includes/footer.php'; ?> 