<?php
require_once '../config/db.php';
session_start();
include '../includes/header.php';

// Get filter parameters
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? 'name_asc';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';

// Build query
$sql = "SELECT * FROM coffins WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category)) {
    $sql .= " AND category = ?";
    $params[] = $category;
}

if (!empty($min_price)) {
    $sql .= " AND price >= ?";
    $params[] = $min_price;
}

if (!empty($max_price)) {
    $sql .= " AND price <= ?";
    $params[] = $max_price;
}

// Add sorting
switch ($sort) {
    case 'price_asc':
        $sql .= " ORDER BY price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY price DESC";
        break;
    case 'name_desc':
        $sql .= " ORDER BY name DESC";
        break;
    default: // name_asc
        $sql .= " ORDER BY name ASC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="text-center mb-4">
                <i class="fas fa-box"></i> Coffin Designs
            </h2>
            
            <!-- Search and Filter Section -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3" id="filterForm">
                        <!-- Search Bar -->
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-search"></i>
                                </span>
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Search designs..." 
                                       value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </div>

                        <!-- Category Filter -->
                        <div class="col-md-3">
                            <select name="category" class="form-select" onchange="this.form.submit()">
                                <option value="">All Categories</option>
                                <option value="wood" <?= $category === 'wood' ? 'selected' : '' ?>>Wood</option>
                                <option value="metal" <?= $category === 'metal' ? 'selected' : '' ?>>Metal</option>
                                <option value="premium" <?= $category === 'premium' ? 'selected' : '' ?>>Premium</option>
                            </select>
                        </div>

                        <!-- Price Range -->
                        <div class="col-md-3">
                            <div class="input-group">
                                <input type="number" name="min_price" class="form-control" 
                                       placeholder="Min Price" value="<?= htmlspecialchars($min_price) ?>">
                                <input type="number" name="max_price" class="form-control" 
                                       placeholder="Max Price" value="<?= htmlspecialchars($max_price) ?>">
                            </div>
                        </div>

                        <!-- Sort Options -->
                        <div class="col-md-2">
                            <select name="sort" class="form-select" onchange="this.form.submit()">
                                <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                                <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
                                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price (Low-High)</option>
                                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price (High-Low)</option>
                            </select>
                        </div>

                        <!-- Filter Buttons -->
                        <div class="col-12 text-center">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                            <a href="coffin_catalog.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> Clear Filters
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Coffin Grid -->
    <div class="row">
        <?php while ($row = $stmt->fetch()): ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100 shadow-sm hover-shadow">
                    <?php if (!empty($row['image'])): ?>
                        <img src="../<?= htmlspecialchars($row['image']) ?>" 
                             class="card-img-top" 
                             alt="<?= htmlspecialchars($row['name']) ?>"
                             style="height: 200px; object-fit: cover;">
                    <?php else: ?>
                        <div class="card-img-top bg-light text-center py-5" style="height: 200px;">
                            <i class="fas fa-box fa-3x text-muted"></i>
                        </div>
                    <?php endif; ?>
                    
                    <div class="card-body">
                        <h5 class="card-title"><?= htmlspecialchars($row['name']) ?></h5>
                        <p class="card-text text-muted"><?= htmlspecialchars($row['description']) ?></p>
                        
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <div>
                                <span class="text-muted">Price:</span>
                                <h4 class="mb-0 text-primary">₱<?= number_format($row['price'], 2) ?></h4>
                            </div>
                            <a href="order_form.php?id=<?= $row['id'] ?>" 
                               class="btn btn-primary" 
                               data-bs-toggle="tooltip" 
                               title="Order this design">
                                <i class="fas fa-shopping-cart"></i> Order Now
                            </a>
                        </div>
                    </div>
                    
                    <div class="card-footer bg-transparent border-top-0">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <small class="text-muted me-2">
                                <i class="fas fa-info-circle"></i> Click to view details
                            </small>
                            <span class="badge bg-<?= $row['stock_quantity'] > 0 ? 'success' : 'danger' ?>">
                                <?= $row['stock_quantity'] > 0 ? 'In Stock' : 'Out of Stock' ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>

    <?php if ($stmt->rowCount() === 0): ?>
        <div class="alert alert-info text-center">
            <i class="fas fa-info-circle"></i> No coffin designs found matching your criteria.
            <a href="coffin_catalog.php" class="alert-link">View all designs</a>
        </div>
    <?php endif; ?>
</div>

<style>
.hover-shadow {
    transition: transform 0.2s, box-shadow 0.2s;
}
.hover-shadow:hover {
    transform: translateY(-5px);
    box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    // Add click event to cards
    document.querySelectorAll('.card').forEach(card => {
        card.addEventListener('click', function(e) {
            if (!e.target.closest('.btn')) {
                const orderLink = this.querySelector('.btn-primary').href;
                window.location.href = orderLink;
}
        });
    });

    // Auto-submit form when sort or category changes
    document.querySelector('select[name="sort"]').addEventListener('change', function() {
        this.form.submit();
    });
    document.querySelector('select[name="category"]').addEventListener('change', function() {
        this.form.submit();
    });
});
</script>

<?php include '../includes/footer.php'; ?>
