<?php
require_once '../../config/db.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Get filter parameters
$category = isset($_GET['category']) ? htmlspecialchars($_GET['category'], ENT_QUOTES, 'UTF-8') : '';
$search = isset($_GET['search']) ? htmlspecialchars($_GET['search'], ENT_QUOTES, 'UTF-8') : '';
$sort = isset($_GET['sort']) ? htmlspecialchars($_GET['sort'], ENT_QUOTES, 'UTF-8') : 'name_asc';

// Build query
$sql = "SELECT * FROM coffin_designs WHERE 1=1";
$params = [];

if (!empty($category)) {
    $sql .= " AND category = ?";
    $params[] = $category;
}

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
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
$coffins = $stmt->fetchAll();

include 'includes/admin_nav.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0">
                    <i class="fas fa-box"></i> Manage Coffins
                </h2>
                <div>
                    <a href="add_coffin.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New Coffin
                    </a>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search coffins..." value="<?= $search ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <option value="wood" <?= $category === 'wood' ? 'selected' : '' ?>>Wood</option>
                        <option value="metal" <?= $category === 'metal' ? 'selected' : '' ?>>Metal</option>
                        <option value="premium" <?= $category === 'premium' ? 'selected' : '' ?>>Premium</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sort By</label>
                    <select name="sort" class="form-select">
                        <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                        <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
                        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price (Low-High)</option>
                        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price (High-Low)</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="manage_coffins.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Coffins Table -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($coffins)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No coffins found matching your criteria.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($coffins as $coffin): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($coffin['image'])): ?>
                                            <img src="<?= htmlspecialchars($coffin['image']) ?>" 
                                                 class="img-thumbnail" 
                                                 alt="<?= htmlspecialchars($coffin['name']) ?>"
                                                 style="max-width: 100px;">
                                        <?php else: ?>
                                            <div class="bg-light p-2 rounded text-center" style="width: 100px;">
                                                <i class="fas fa-box fa-2x text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <h6 class="mb-1"><?= htmlspecialchars($coffin['name']) ?></h6>
                                        <small class="text-muted"><?= htmlspecialchars($coffin['description']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= getCategoryColor($coffin['category']) ?>">
                                            <?= ucfirst($coffin['category']) ?>
                                        </span>
                                    </td>
                                    <td>₱<?= number_format($coffin['price'], 2) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $coffin['in_stock'] ? 'success' : 'danger' ?>">
                                            <?= $coffin['in_stock'] ? 'In Stock' : 'Out of Stock' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="edit_coffin.php?id=<?= $coffin['id'] ?>" 
                                               class="btn btn-sm btn-primary" 
                                               title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" 
                                                    class="btn btn-sm btn-danger" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteModal<?= $coffin['id'] ?>"
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>

                                        <!-- Delete Confirmation Modal -->
                                        <div class="modal fade" id="deleteModal<?= $coffin['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Confirm Delete</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to delete this coffin design?</p>
                                                        <p class="mb-0"><strong><?= htmlspecialchars($coffin['name']) ?></strong></p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <form action="delete_coffin.php" method="POST" class="d-inline">
                                                            <input type="hidden" name="coffin_id" value="<?= $coffin['id'] ?>">
                                                            <button type="submit" class="btn btn-danger">Delete</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
function getCategoryColor($category) {
    return match($category) {
        'wood' => 'success',
        'metal' => 'primary',
        'premium' => 'warning',
        default => 'secondary'
    };
}

include '../../includes/footer.php';
?> 