<?php
require_once '../../config/db.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Get filter parameters
$search = isset($_GET['search']) ? htmlspecialchars($_GET['search'], ENT_QUOTES, 'UTF-8') : '';
$category = isset($_GET['category']) ? htmlspecialchars($_GET['category'], ENT_QUOTES, 'UTF-8') : '';
$stock_status = isset($_GET['stock_status']) ? htmlspecialchars($_GET['stock_status'], ENT_QUOTES, 'UTF-8') : '';

// Build query
$sql = "SELECT c.*, 
               COALESCE(SUM(oi.quantity), 0) as total_sold,
               COALESCE(SUM(oi.quantity * oi.price), 0) as total_revenue
        FROM coffins c
        LEFT JOIN order_items oi ON c.id = oi.coffin_id
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (c.name LIKE ? OR c.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($category)) {
    $sql .= " AND c.category = ?";
    $params[] = $category;
}

if (!empty($stock_status)) {
    if ($stock_status === 'low') {
        $sql .= " AND c.stock_quantity <= c.low_stock_threshold";
    } elseif ($stock_status === 'out') {
        $sql .= " AND c.stock_quantity = 0";
    }
}

$sql .= " GROUP BY c.id ORDER BY c.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$coffins = $stmt->fetchAll();

// Get categories for filter
$stmt = $pdo->query("SELECT DISTINCT category FROM coffins ORDER BY category");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

include 'includes/admin_nav.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0">
                    <i class="fas fa-boxes"></i> Inventory Management
                </h2>
                <div>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addCoffinModal">
                        <i class="fas fa-plus"></i> Add New Coffin
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary">
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
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat ?>" <?= $category === $cat ? 'selected' : '' ?>>
                                <?= ucfirst($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Stock Status</label>
                    <select name="stock_status" class="form-select">
                        <option value="">All Stock</option>
                        <option value="low" <?= $stock_status === 'low' ? 'selected' : '' ?>>Low Stock</option>
                        <option value="out" <?= $stock_status === 'out' ? 'selected' : '' ?>>Out of Stock</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="manage_inventory.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Inventory Table -->
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
                                <th>Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Sold</th>
                                <th>Revenue</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($coffins as $coffin): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($coffin['name']) ?>
                                        <?php if (!empty($coffin['description'])): ?>
                                            <br>
                                            <small class="text-muted"><?= htmlspecialchars($coffin['description']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= ucfirst($coffin['category']) ?></td>
                                    <td>₱<?= number_format($coffin['price'], 2) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $coffin['stock_quantity'] <= $coffin['low_stock_threshold'] ? 'warning' : 'success' ?>">
                                            <?= number_format($coffin['stock_quantity']) ?>
                                        </span>
                                    </td>
                                    <td><?= number_format($coffin['total_sold']) ?></td>
                                    <td>₱<?= number_format($coffin['total_revenue'], 2) ?></td>
                                    <td>
                                        <?php if ($coffin['stock_quantity'] == 0): ?>
                                            <span class="badge bg-danger">Out of Stock</span>
                                        <?php elseif ($coffin['stock_quantity'] <= $coffin['low_stock_threshold']): ?>
                                            <span class="badge bg-warning">Low Stock</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">In Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" 
                                                    class="btn btn-sm btn-info" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#viewCoffinModal<?= $coffin['id'] ?>"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" 
                                                    class="btn btn-sm btn-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editCoffinModal<?= $coffin['id'] ?>"
                                                    title="Edit Coffin">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" 
                                                    class="btn btn-sm btn-success" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#updateStockModal<?= $coffin['id'] ?>"
                                                    title="Update Stock">
                                                <i class="fas fa-boxes"></i>
                                            </button>
                                            <button type="button" 
                                                    class="btn btn-sm btn-danger" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteCoffinModal<?= $coffin['id'] ?>"
                                                    title="Delete Coffin">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>

                                        <!-- View Coffin Modal -->
                                        <div class="modal fade" id="viewCoffinModal<?= $coffin['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Coffin Details</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label">Name</label>
                                                            <p class="form-control-static"><?= htmlspecialchars($coffin['name']) ?></p>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Description</label>
                                                            <p class="form-control-static"><?= htmlspecialchars($coffin['description']) ?></p>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Category</label>
                                                            <p class="form-control-static"><?= ucfirst($coffin['category']) ?></p>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Price</label>
                                                            <p class="form-control-static">₱<?= number_format($coffin['price'], 2) ?></p>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Stock Quantity</label>
                                                            <p class="form-control-static"><?= number_format($coffin['stock_quantity']) ?></p>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Low Stock Threshold</label>
                                                            <p class="form-control-static"><?= number_format($coffin['low_stock_threshold']) ?></p>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Total Sold</label>
                                                            <p class="form-control-static"><?= number_format($coffin['total_sold']) ?></p>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Total Revenue</label>
                                                            <p class="form-control-static">₱<?= number_format($coffin['total_revenue'], 2) ?></p>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Edit Coffin Modal -->
                                        <div class="modal fade" id="editCoffinModal<?= $coffin['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form action="update_coffin.php" method="POST">
                                                        <input type="hidden" name="coffin_id" value="<?= $coffin['id'] ?>">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Edit Coffin</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label class="form-label">Name</label>
                                                                <input type="text" class="form-control" name="name" 
                                                                       value="<?= htmlspecialchars($coffin['name']) ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Description</label>
                                                                <textarea class="form-control" name="description" rows="3"><?= htmlspecialchars($coffin['description']) ?></textarea>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Category</label>
                                                                <input type="text" class="form-control" name="category" 
                                                                       value="<?= htmlspecialchars($coffin['category']) ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Price</label>
                                                                <input type="number" class="form-control" name="price" 
                                                                       value="<?= $coffin['price'] ?>" step="0.01" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Low Stock Threshold</label>
                                                                <input type="number" class="form-control" name="low_stock_threshold" 
                                                                       value="<?= $coffin['low_stock_threshold'] ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-primary">Update Coffin</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Update Stock Modal -->
                                        <div class="modal fade" id="updateStockModal<?= $coffin['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form action="update_stock.php" method="POST">
                                                        <input type="hidden" name="coffin_id" value="<?= $coffin['id'] ?>">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Update Stock</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label class="form-label">Current Stock</label>
                                                                <p class="form-control-static"><?= number_format($coffin['stock_quantity']) ?></p>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Adjustment</label>
                                                                <input type="number" class="form-control" name="adjustment" 
                                                                       placeholder="Enter positive number to add, negative to remove" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Notes</label>
                                                                <textarea class="form-control" name="notes" rows="2" 
                                                                          placeholder="Optional notes about this stock adjustment"></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-success">Update Stock</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Delete Coffin Modal -->
                                        <div class="modal fade" id="deleteCoffinModal<?= $coffin['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Confirm Delete</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to delete this coffin?</p>
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

<!-- Add Coffin Modal -->
<div class="modal fade" id="addCoffinModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="add_coffin.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Coffin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <input type="text" class="form-control" name="category" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Price</label>
                        <input type="number" class="form-control" name="price" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Initial Stock</label>
                        <input type="number" class="form-control" name="stock_quantity" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Low Stock Threshold</label>
                        <input type="number" class="form-control" name="low_stock_threshold" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Coffin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?> 