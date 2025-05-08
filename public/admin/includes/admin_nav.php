<?php
// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Get counts for notifications
$low_stock_count = 0;
$pending_orders_count = 0;

try {
    // Get low stock items count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM coffins 
        WHERE stock_quantity <= low_stock_threshold
    ");
    $stmt->execute();
    $low_stock_count = $stmt->fetchColumn();

    // Get pending orders count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM orders 
        WHERE payment_status = 'pending'
    ");
    $stmt->execute();
    $pending_orders_count = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Navigation error: " . $e->getMessage());
}

// Get admin name from database if not in session
if (!isset($_SESSION['name']) && isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $admin = $stmt->fetch();
        if ($admin) {
            $_SESSION['name'] = $admin['name'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching admin name: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coffin Ordering System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="/collado/public/css/style.css" rel="stylesheet">
</head>


<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
            <i class="fas fa-store"></i> Admin Panel
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="adminNavbar">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                
                <!-- Inventory Management -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-boxes"></i> Inventory
                    </a>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item" href="manage_inventory.php">
                                <i class="fas fa-list"></i> Manage Inventory
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="add_coffin.php">
                                <i class="fas fa-plus"></i> Add New Coffin
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="manage_coffins.php">
                                <i class="fas fa-edit"></i> Manage Coffins
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Order Management -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-shopping-cart"></i> Orders
                        <?php if ($pending_orders_count > 0): ?>
                            <span class="badge bg-danger"><?= $pending_orders_count ?></span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item" href="manage_orders.php">
                                <i class="fas fa-list"></i> All Orders
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="manage_orders.php?status=pending">
                                <i class="fas fa-clock"></i> Pending Orders
                                <?php if ($pending_orders_count > 0): ?>
                                    <span class="badge bg-danger float-end"><?= $pending_orders_count ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Reports -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-chart-bar"></i> Reports
                    </a>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item" href="reports.php">
                                <i class="fas fa-chart-line"></i> Sales Overview
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="reports.php?type=sales">
                                <i class="fas fa-file-invoice-dollar"></i> Sales Report
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="reports.php?type=inventory">
                                <i class="fas fa-boxes"></i> Inventory Report
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- User Management -->
                <li class="nav-item">
                    <a class="nav-link" href="manage_users.php">
                        <i class="fas fa-users"></i> Users
                    </a>
                </li>
            </ul>

            <!-- Right Side Navigation -->
            <ul class="navbar-nav">
                <!-- Notifications -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-bell"></i>
                        <?php if ($low_stock_count > 0 || $pending_orders_count > 0): ?>
                            <span class="badge bg-danger"><?= $low_stock_count + $pending_orders_count ?></span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php if ($low_stock_count > 0): ?>
                            <li>
                                <a class="dropdown-item" href="manage_inventory.php?stock_status=low">
                                    <i class="fas fa-exclamation-triangle text-warning"></i>
                                    Low Stock Items
                                    <span class="badge bg-warning float-end"><?= $low_stock_count ?></span>
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if ($pending_orders_count > 0): ?>
                            <li>
                                <a class="dropdown-item" href="manage_orders.php?status=pending">
                                    <i class="fas fa-clock text-info"></i>
                                    Pending Orders
                                    <span class="badge bg-info float-end"><?= $pending_orders_count ?></span>
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if ($low_stock_count === 0 && $pending_orders_count === 0): ?>
                            <li><span class="dropdown-item-text">No new notifications</span></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="notifications.php">
                                <i class="fas fa-list"></i> View All Notifications
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- User Menu -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i>
                        <?= isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'Admin' ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="profile.php">
                                <i class="fas fa-user"></i> Profile
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="activity_log.php">
                                <i class="fas fa-history"></i> Activity Log
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="../logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<style>
.navbar {
    box-shadow: 0 2px 4px rgba(0,0,0,.1);
}

.navbar-brand {
    font-weight: 600;
}

.nav-link {
    padding: 0.5rem 1rem;
    transition: all 0.3s ease;
}

.nav-link:hover {
    background-color: rgba(255,255,255,.1);
}

.dropdown-menu {
    border: none;
    box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15);
}

.dropdown-item {
    padding: 0.5rem 1rem;
    transition: all 0.2s ease;
}

.dropdown-item:hover {
    background-color: #f8f9fa;
}

.badge {
    font-size: 0.75rem;
    padding: 0.25em 0.6em;
}

@media (max-width: 991.98px) {
    .navbar-collapse {
        padding: 1rem 0;
    }
    
    .nav-link {
        padding: 0.5rem 0;
    }
    
    .dropdown-menu {
        border: none;
        box-shadow: none;
        padding-left: 1rem;
    }
}
</style> 