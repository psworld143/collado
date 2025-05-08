<?php
session_start();
require_once '../config/db.php';
include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="jumbotron bg-light p-5 rounded">
            <h1 class="display-4">
                <i class="fas fa-heart"></i> Welcome to Collado Coffin Services
            </h1>
            <p class="lead">Providing dignified and respectful funeral services with care and compassion.</p>
            <hr class="my-4">
            <p>Browse our selection of high-quality coffins and make arrangements with ease.</p>
            <a class="btn btn-primary btn-lg" href="coffin_catalog.php" role="button">
                <i class="fas fa-box"></i> View Catalog
            </a>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="fas fa-box fa-3x text-primary mb-3"></i>
                <h5 class="card-title">Quality Coffins</h5>
                <p class="card-text">Choose from our wide selection of premium quality coffins.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="fas fa-truck fa-3x text-primary mb-3"></i>
                <h5 class="card-title">Fast Delivery</h5>
                <p class="card-text">Reliable and timely delivery services to your location.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="fas fa-headset fa-3x text-primary mb-3"></i>
                <h5 class="card-title">24/7 Support</h5>
                <p class="card-text">Our compassionate team is always here to assist you.</p>
            </div>
        </div>
    </div>
</div>

<?php if (!isset($_SESSION['user_id'])): ?>
<div class="row">
    <div class="col-md-6 offset-md-3">
        <div class="card">
            <div class="card-body text-center">
                <h5 class="card-title">Get Started Today</h5>
                <p class="card-text">Create an account to place orders and track your deliveries.</p>
                <div class="d-grid gap-2">
                    <a href="register.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Register Now
                    </a>
                    <a href="login.php" class="btn btn-outline-primary">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
