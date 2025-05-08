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

<!-- Add SweetAlert2 CSS and JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>

<style>
    /* Fix for modal flickering */
    .modal {
        z-index: 1050 !important;
        background-color: rgba(0, 0, 0, 0.5);
    }
    
    .modal-backdrop {
        z-index: 1040 !important;
    }
    
    .modal-dialog {
        margin: 1.75rem auto;
        pointer-events: auto;
        transform: translate(0, 0) !important;
    }
    
    .modal-content {
        position: relative;
        pointer-events: auto;
        transform: none !important;
    }
    
    /* Ensure buttons don't cause flickering */
    .btn-group .btn {
        position: relative;
        z-index: 1;
    }
    
    /* Improve modal transitions */
    .modal.fade .modal-dialog {
        transition: transform .2s ease-out;
    }
    
    .modal.show .modal-dialog {
        transform: none !important;
    }
    
    /* Ensure proper stacking context */
    .table td {
        position: relative;
    }
    
    /* Prevent hover effects from interfering with modal */
    .btn-group:hover {
        z-index: 2;
    }

    /* Fix modal backdrop */
    .modal-backdrop.show {
        opacity: 0.5;
    }

    /* Prevent modal content from moving */
    .modal-open {
        overflow: hidden;
        padding-right: 0 !important;
    }

    /* Ensure modal stays in place */
    .modal.fade.show {
        display: block !important;
    }
</style>

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
                                                    class="btn btn-sm btn-info view-coffin-btn" 
                                                    data-coffin-id="<?= $coffin['id'] ?>"
                                                    data-coffin-name="<?= htmlspecialchars($coffin['name']) ?>"
                                                    data-coffin-description="<?= htmlspecialchars($coffin['description']) ?>"
                                                    data-coffin-category="<?= htmlspecialchars($coffin['category']) ?>"
                                                    data-coffin-price="<?= $coffin['price'] ?>"
                                                    data-coffin-stock="<?= $coffin['stock_quantity'] ?>"
                                                    data-coffin-threshold="<?= $coffin['low_stock_threshold'] ?>"
                                                    data-coffin-sold="<?= $coffin['total_sold'] ?>"
                                                    data-coffin-revenue="<?= $coffin['total_revenue'] ?>"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" 
                                                    class="btn btn-sm btn-primary edit-coffin-btn" 
                                                    data-coffin-id="<?= $coffin['id'] ?>"
                                                    data-coffin-name="<?= htmlspecialchars($coffin['name']) ?>"
                                                    data-coffin-description="<?= htmlspecialchars($coffin['description']) ?>"
                                                    data-coffin-category="<?= htmlspecialchars($coffin['category']) ?>"
                                                    data-coffin-price="<?= $coffin['price'] ?>"
                                                    data-coffin-threshold="<?= $coffin['low_stock_threshold'] ?>"
                                                    title="Edit Coffin">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" 
                                                    class="btn btn-sm btn-success update-stock-btn" 
                                                    data-coffin-id="<?= $coffin['id'] ?>"
                                                    data-coffin-name="<?= htmlspecialchars($coffin['name']) ?>"
                                                    data-coffin-stock="<?= $coffin['stock_quantity'] ?>"
                                                    title="Update Stock">
                                                <i class="fas fa-boxes"></i>
                                            </button>
                                            <button type="button" 
                                                    class="btn btn-sm btn-danger delete-coffin-btn" 
                                                    data-coffin-id="<?= $coffin['id'] ?>"
                                                    data-coffin-name="<?= htmlspecialchars($coffin['name']) ?>"
                                                    title="Delete Coffin">
                                                <i class="fas fa-trash"></i>
                                            </button>
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

<!-- Edit Coffin Modal -->
<div class="modal fade" id="editCoffinModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="update_coffin.php" method="POST">
                <input type="hidden" name="coffin_id" id="edit_coffin_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Coffin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="name" id="edit_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <input type="text" class="form-control" name="category" id="edit_category" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Price</label>
                        <input type="number" class="form-control" name="price" id="edit_price" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Low Stock Threshold</label>
                        <input type="number" class="form-control" name="low_stock_threshold" id="edit_threshold" required>
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

<!-- View Coffin Modal -->
<div class="modal fade" id="viewCoffinModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Coffin Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <p class="form-control-static" id="view_name"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <p class="form-control-static" id="view_description"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label">Category</label>
                    <p class="form-control-static" id="view_category"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label">Price</label>
                    <p class="form-control-static" id="view_price"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label">Stock Quantity</label>
                    <p class="form-control-static" id="view_stock"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label">Low Stock Threshold</label>
                    <p class="form-control-static" id="view_threshold"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label">Total Sold</label>
                    <p class="form-control-static" id="view_sold"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label">Total Revenue</label>
                    <p class="form-control-static" id="view_revenue"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Update Stock Modal -->
<div class="modal fade" id="updateStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="updateStockForm" onsubmit="return handleUpdateStock(event)">
                <input type="hidden" name="coffin_id" id="update_stock_coffin_id">
                <div class="modal-header">
                    <h5 class="modal-title">Update Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Coffin Name</label>
                        <p class="form-control-static" id="update_stock_name"></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Current Stock</label>
                        <p class="form-control-static" id="update_stock_current"></p>
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
<div class="modal fade" id="deleteCoffinModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this coffin?</p>
                <p class="mb-0"><strong id="delete_coffin_name"></strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form action="delete_coffin.php" method="POST" class="d-inline">
                    <input type="hidden" name="coffin_id" id="delete_coffin_id">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle view coffin button clicks
    const viewButtons = document.querySelectorAll('.view-coffin-btn');
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const coffinName = this.getAttribute('data-coffin-name');
            const coffinDescription = this.getAttribute('data-coffin-description');
            const coffinCategory = this.getAttribute('data-coffin-category');
            const coffinPrice = this.getAttribute('data-coffin-price');
            const coffinStock = this.getAttribute('data-coffin-stock');
            const coffinThreshold = this.getAttribute('data-coffin-threshold');
            const coffinSold = this.getAttribute('data-coffin-sold');
            const coffinRevenue = this.getAttribute('data-coffin-revenue');

            // Set values in the view modal
            document.getElementById('view_name').textContent = coffinName;
            document.getElementById('view_description').textContent = coffinDescription;
            document.getElementById('view_category').textContent = coffinCategory;
            document.getElementById('view_price').textContent = '₱' + parseFloat(coffinPrice).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('view_stock').textContent = parseInt(coffinStock).toLocaleString('en-US');
            document.getElementById('view_threshold').textContent = parseInt(coffinThreshold).toLocaleString('en-US');
            document.getElementById('view_sold').textContent = parseInt(coffinSold).toLocaleString('en-US');
            document.getElementById('view_revenue').textContent = '₱' + parseFloat(coffinRevenue).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

            // Show the modal
            const viewModal = new bootstrap.Modal(document.getElementById('viewCoffinModal'));
            viewModal.show();
        });
    });

    // Handle edit coffin button clicks
    const editButtons = document.querySelectorAll('.edit-coffin-btn');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const coffinId = this.getAttribute('data-coffin-id');
            const coffinName = this.getAttribute('data-coffin-name');
            const coffinDescription = this.getAttribute('data-coffin-description');
            const coffinCategory = this.getAttribute('data-coffin-category');
            const coffinPrice = this.getAttribute('data-coffin-price');
            const coffinThreshold = this.getAttribute('data-coffin-threshold');

            // Set values in the edit modal
            document.getElementById('edit_coffin_id').value = coffinId;
            document.getElementById('edit_name').value = coffinName;
            document.getElementById('edit_description').value = coffinDescription;
            document.getElementById('edit_category').value = coffinCategory;
            document.getElementById('edit_price').value = coffinPrice;
            document.getElementById('edit_threshold').value = coffinThreshold;

            // Show the modal
            const editModal = new bootstrap.Modal(document.getElementById('editCoffinModal'));
            editModal.show();
        });
    });

    // Handle update stock button clicks
    const updateStockButtons = document.querySelectorAll('.update-stock-btn');
    updateStockButtons.forEach(button => {
        button.addEventListener('click', function() {
            const coffinId = this.getAttribute('data-coffin-id');
            const coffinName = this.getAttribute('data-coffin-name');
            const coffinStock = this.getAttribute('data-coffin-stock');

            // Set values in the update stock modal
            document.getElementById('update_stock_coffin_id').value = coffinId;
            document.getElementById('update_stock_name').textContent = coffinName;
            document.getElementById('update_stock_current').textContent = parseInt(coffinStock).toLocaleString('en-US');

            // Show the modal
            const updateStockModal = new bootstrap.Modal(document.getElementById('updateStockModal'));
            updateStockModal.show();
        });
    });

    // Handle delete coffin button clicks
    const deleteButtons = document.querySelectorAll('.delete-coffin-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const coffinId = this.getAttribute('data-coffin-id');
            const coffinName = this.getAttribute('data-coffin-name');

            // Set values in the delete modal
            document.getElementById('delete_coffin_id').value = coffinId;
            document.getElementById('delete_coffin_name').textContent = coffinName;

            // Show the modal
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteCoffinModal'));
            deleteModal.show();
        });
    });

    // Prevent modal flickering
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.addEventListener('show.bs.modal', function (event) {
            event.stopPropagation();
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            document.body.appendChild(backdrop);
        });

        modal.addEventListener('hidden.bs.modal', function (event) {
            event.stopPropagation();
            const backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) {
                backdrop.remove();
            }
        });
    });

    // Prevent multiple backdrops
    document.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal-backdrop')) {
            event.stopPropagation();
        }
    });
});

// Handle update stock form submission
async function handleUpdateStock(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    try {
        console.log('Sending stock update request...');
        const response = await fetch('update_stock.php', {
            method: 'POST',
            body: formData
        });
        
        console.log('Response status:', response.status);
        const result = await response.json();
        console.log('Response data:', result);
        
        if (result.success) {
            // Show success message
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: result.message,
                html: `
                    <div class="text-start">
                        <p><strong>Coffin:</strong> ${result.data.coffin_name}</p>
                        <p><strong>Previous Stock:</strong> ${result.data.previous_quantity}</p>
                        <p><strong>Adjustment:</strong> ${result.data.adjustment > 0 ? '+' : ''}${result.data.adjustment}</p>
                        <p><strong>New Stock:</strong> ${result.data.new_quantity}</p>
                    </div>
                `,
                confirmButtonText: 'OK'
            }).then(() => {
                // Close the modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('updateStockModal'));
                modal.hide();
                
                // Reload the page to show updated data
                window.location.reload();
            });
        } else {
            // Show error message
            console.error('Update failed:', result.message);
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: result.message || 'Failed to update stock',
                confirmButtonText: 'OK'
            });
        }
    } catch (error) {
        // Log detailed error information
        console.error('Error details:', {
            message: error.message,
            stack: error.stack,
            formData: Object.fromEntries(formData)
        });
        
        // Show error message for network/server errors
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'An error occurred while updating the stock. Please check the console for details.',
            confirmButtonText: 'OK'
        });
    }
    
    return false;
}
</script>

<?php include '../../includes/footer.php'; ?> 