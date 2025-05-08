<?php 
require_once '../config/db.php';
session_start(); 

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Handle login attempt
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $error = '';

    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Debug information
            error_log("Login attempt - Email: " . $email);
            error_log("User found: " . ($user ? "Yes" : "No"));
            if ($user) {
                error_log("Password verification: " . (password_verify($password, $user['password']) ? "Success" : "Failed"));
                error_log("Stored hash: " . $user['password']);
            }

            if ($user && password_verify($password, $user['password'])) {
                // Set session variables
$_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['name'];
$_SESSION['user_email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                
                // Update last login
                $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);

                // Redirect to intended page or dashboard
                $redirect = isset($_SESSION['redirect_after_login']) 
                    ? $_SESSION['redirect_after_login'] 
                    : ($user['role'] === 'admin' ? 'admin/dashboard.php' : 'index.php');
                unset($_SESSION['redirect_after_login']);
                
                header("Location: " . $redirect);
                exit;
            } else {
                $error = "Invalid email or password";
            }
        } catch (PDOException $e) {
            $error = "An error occurred. Please try again later.";
            error_log("Login error: " . $e->getMessage());
        }
    }
}

include '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-lg border-0 rounded-lg fade-in">
            <div class="card-header bg-primary text-white text-center py-4">
                <h3 class="mb-0">
                    <i class="fas fa-sign-in-alt"></i> Login
                </h3>
            </div>
            <div class="card-body p-4">
                <?php if (isset($error) && !empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" class="needs-validation" novalidate id="loginForm">
    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-envelope"></i> Email Address
                        </label>
                        <input type="email" name="email" class="form-control" required
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                               placeholder="Enter your email">
                        <div class="invalid-feedback">
                            Please enter a valid email address.
                        </div>
    </div>

    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-lock"></i> Password
                        </label>
                        <div class="input-group">
                            <input type="password" name="password" class="form-control" required
                                   placeholder="Enter your password" id="password">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">
                            Please enter your password.
                        </div>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="rememberMe" name="remember">
                        <label class="form-check-label" for="rememberMe">Remember me</label>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </button>
                    </div>
                </form>
            </div>
            <div class="card-footer text-center py-3">
                <div class="small">
                    <a href="forgot_password.php" class="text-decoration-none">
                        <i class="fas fa-key"></i> Forgot Password?
                    </a>
                </div>
                <div class="small mt-2">
                    Don't have an account? 
                    <a href="register.php" class="text-decoration-none">
                        <i class="fas fa-user-plus"></i> Register Now
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Form validation and logging
    (function () {
        'use strict'
        var forms = document.querySelectorAll('.needs-validation')
        Array.prototype.slice.call(forms).forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                    console.log('Form validation failed');
                } else {
                    console.log('Form submitted with:', {
                        email: form.querySelector('input[name="email"]').value,
                        password: '********' // Don't log actual password
                    });
                }
                form.classList.add('was-validated')
            }, false)
        })
    })()

    // Password visibility toggle
    document.getElementById('togglePassword').addEventListener('click', function() {
        const password = document.getElementById('password');
        const icon = this.querySelector('i');
        
        if (password.type === 'password') {
            password.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
            console.log('Password visibility toggled: visible');
    } else {
            password.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
            console.log('Password visibility toggled: hidden');
        }
    });

    // Remember me functionality
    const rememberMe = document.getElementById('rememberMe');
    const emailInput = document.querySelector('input[name="email"]');
    
    // Check if there's a saved email
    const savedEmail = localStorage.getItem('rememberedEmail');
    if (savedEmail) {
        emailInput.value = savedEmail;
        rememberMe.checked = true;
        console.log('Loaded remembered email:', savedEmail);
    }

    // Save email when remember me is checked
    rememberMe.addEventListener('change', function() {
        if (this.checked) {
            localStorage.setItem('rememberedEmail', emailInput.value);
            console.log('Email saved for remember me:', emailInput.value);
        } else {
            localStorage.removeItem('rememberedEmail');
            console.log('Remembered email removed');
        }
    });

    // Add error logging
    <?php if (isset($error) && !empty($error)): ?>
    console.error('Login error:', <?php echo json_encode($error); ?>);
    <?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>
