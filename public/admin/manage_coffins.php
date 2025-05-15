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
$sql = "SELECT c.*, 
               COALESCE(COUNT(DISTINCT oi.order_id), 0) as order_count,
               COALESCE(SUM(oi.quantity), 0) as total_ordered
        FROM coffins c
        LEFT JOIN order_items oi ON c.id = oi.coffin_id
        WHERE 1=1";
$params = [];

if (!empty($category)) {
    $sql .= " AND c.category = ?";
    $params[] = $category;
}

if (!empty($search)) {
    $sql .= " AND (c.name LIKE ? OR c.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$sql .= " GROUP BY c.id";

// Add sorting
switch ($sort) {
    case 'price_asc':
        $sql .= " ORDER BY c.price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY c.price DESC";
        break;
    case 'name_desc':
        $sql .= " ORDER BY c.name DESC";
        break;
    default: // name_asc
        $sql .= " ORDER BY c.name ASC";
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
                                            <img src="../../<?= htmlspecialchars($coffin['image']) ?>" 
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
                                        <?php if ($coffin['stock_quantity'] > 0): ?>
                                            <span class="badge bg-success">In Stock (<?= $coffin['stock_quantity'] ?>)</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Out of Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" 
                                                    class="btn btn-sm btn-primary" 
                                                    onclick="viewCoffin(<?= $coffin['id'] ?>)"
                                                    title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" 
                                                    class="btn btn-sm btn-warning" 
                                                    onclick="editCoffin(<?= $coffin['id'] ?>)"
                                                    title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" 
                                                    class="btn btn-sm btn-danger" 
                                                    onclick="deleteCoffin(<?= $coffin['id'] ?>)"
                                                    title="Delete">
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

<!-- Single Coffin Action Modal -->
<div class="modal fade" id="coffinActionModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Coffin Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="coffinActionContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
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

<script>
// Function to get category color
function getCategoryColor(category) {
    switch(category) {
        case 'wood':
            return 'success';
        case 'metal':
            return 'primary';
        case 'premium':
            return 'warning';
        default:
            return 'secondary';
    }
}

// Function to show loading state
function showLoading() {
    document.getElementById('coffinActionContent').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading coffin details...</p>
        </div>
    `;
}

// Function to view coffin details
function viewCoffin(coffinId) {
    const modal = new bootstrap.Modal(document.getElementById('coffinActionModal'));
    showLoading();
    modal.show();

    // Fetch coffin details
    fetch(`get_coffin_details.php?id=${coffinId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const coffin = data.coffin;
                document.getElementById('coffinActionContent').innerHTML = `
                    <div class="text-start">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Basic Information</h6>
                                <p class="mb-1"><strong>Name:</strong> ${coffin.name}</p>
                                <p class="mb-1"><strong>Category:</strong> 
                                    <span class="badge bg-${getCategoryColor(coffin.category)}">
                                        ${coffin.category.charAt(0).toUpperCase() + coffin.category.slice(1)}
                                    </span>
                                </p>
                                <p class="mb-1"><strong>Price:</strong> ₱${parseFloat(coffin.price).toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                                <p class="mb-1"><strong>Stock:</strong> ${coffin.stock}</p>
                            </div>
                            <div class="col-md-6">
                                <h6>Description</h6>
                                <p>${coffin.description}</p>
                                ${coffin.image ? `
                                    <div class="mt-3">
                                        <h6>Image</h6>
                                        <img src="../../${coffin.image}" class="img-fluid rounded" alt="${coffin.name}">
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                        <div class="mt-4 text-end">
                            <button type="button" class="btn btn-warning me-2" onclick="editCoffin(${coffin.id})">
                                <i class="fas fa-edit"></i> Edit Coffin
                            </button>
                            <button type="button" class="btn btn-danger" onclick="deleteCoffin(${coffin.id})">
                                <i class="fas fa-trash"></i> Delete Coffin
                            </button>
                        </div>
                    </div>
                `;
            } else {
                document.getElementById('coffinActionContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> Failed to load coffin details
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('coffinActionContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> Failed to load coffin details
                </div>
            `;
        });
}

// Function to edit coffin
function editCoffin(coffinId) {
    const modal = new bootstrap.Modal(document.getElementById('coffinActionModal'));
    showLoading();
    modal.show();

    // Fetch coffin details
    fetch(`get_coffin_details.php?id=${coffinId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const coffin = data.coffin;
                document.getElementById('coffinActionContent').innerHTML = `
                    <form action="update_coffin.php" method="POST" enctype="multipart/form-data" id="editCoffinForm">
                        <input type="hidden" name="coffin_id" value="${coffin.id}">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Name</label>
                                    <input type="text" name="name" class="form-control" value="${coffin.name}" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control" rows="3" required>${coffin.description}</textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Price (₱)</label>
                                    <input type="number" name="price" class="form-control" step="0.01" min="0" value="${coffin.price}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Stock</label>
                                    <input type="number" name="stock" class="form-control" min="0" value="${coffin.stock}" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Category</label>
                                    <select name="category" class="form-select" required>
                                        <option value="wood" ${coffin.category === 'wood' ? 'selected' : ''}>Wood</option>
                                        <option value="metal" ${coffin.category === 'metal' ? 'selected' : ''}>Metal</option>
                                        <option value="premium" ${coffin.category === 'premium' ? 'selected' : ''}>Premium</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Image</label>
                                    ${coffin.image ? `
                                        <div class="mb-2">
                                            <img src="../../${coffin.image}" class="img-thumbnail" style="max-height: 100px;">
                                        </div>
                                    ` : ''}
                                    <input type="file" name="image" class="form-control" accept="image/*">
                                    <small class="text-muted">Leave empty to keep current image</small>
                                </div>
                            </div>
                        </div>
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary me-2" onclick="viewCoffin(${coffin.id})">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                `;

                // Add form submit handler
                document.getElementById('editCoffinForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    
                    fetch('update_coffin.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.reload();
                        } else {
                            alert(data.message || 'Failed to update coffin');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Failed to update coffin');
                    });
                });
            } else {
                document.getElementById('coffinActionContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> Failed to load coffin details
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('coffinActionContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> Failed to load coffin details
                </div>
            `;
        });
}

// Function to delete coffin
function deleteCoffin(coffinId) {
    const modal = new bootstrap.Modal(document.getElementById('coffinActionModal'));
    showLoading();
    modal.show();

    // Fetch coffin details
    fetch(`get_coffin_details.php?id=${coffinId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const coffin = data.coffin;
                document.getElementById('coffinActionContent').innerHTML = `
                    <div class="text-center">
                        <div class="mb-4">
                            <i class="fas fa-exclamation-triangle fa-3x text-danger"></i>
                        </div>
                        <h5>Are you sure you want to delete this coffin?</h5>
                        <div class="text-start mt-4">
                            <p class="mb-1"><strong>Name:</strong> ${coffin.name}</p>
                            <p class="mb-1"><strong>Category:</strong> ${coffin.category}</p>
                            <p class="mb-1"><strong>Price:</strong> ₱${parseFloat(coffin.price).toLocaleString('en-US', {minimumFractionDigits: 2})}</p>
                            <p class="mb-1"><strong>Stock:</strong> ${coffin.stock}</p>
                        </div>
                        <div class="mt-4">
                            <button type="button" class="btn btn-secondary me-2" onclick="viewCoffin(${coffin.id})">Cancel</button>
                            <button type="button" class="btn btn-danger" onclick="confirmDelete(${coffin.id})">
                                Delete Coffin
                            </button>
                        </div>
                    </div>
                `;
            } else {
                document.getElementById('coffinActionContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> Failed to load coffin details
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('coffinActionContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> Failed to load coffin details
                </div>
            `;
        });
}

// Function to confirm deletion
function confirmDelete(coffinId) {
    const formData = new FormData();
    formData.append('coffin_id', coffinId);

    fetch('delete_coffin.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Failed to delete coffin');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to delete coffin');
    });
}
</script>

<style>
#coffinActionModal .modal-dialog {
    max-width: 800px;
}
#coffinActionModal .modal-body {
    padding: 1.5rem;
}
</style> 