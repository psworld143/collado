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
$role = isset($_GET['role']) ? htmlspecialchars($_GET['role'], ENT_QUOTES, 'UTF-8') : '';

// Build query
$sql = "SELECT u.*, 
               COUNT(DISTINCT o.id) as order_count,
               SUM(CASE WHEN o.payment_status = 'paid' THEN o.total_amount ELSE 0 END) as total_spent
        FROM users u
        LEFT JOIN orders o ON u.id = o.user_id
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($role)) {
    $sql .= " AND u.role = ?";
    $params[] = $role;
}

$sql .= " GROUP BY u.id ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

include 'includes/admin_nav.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0">
                    <i class="fas fa-users"></i> User Management
                </h2>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
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
                           placeholder="Search users..." value="<?= $search ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select">
                        <option value="">All Roles</option>
                        <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="user" <?= $role === 'user' ? 'selected' : '' ?>>User</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="manage_users.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($users)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No users found matching your criteria.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Role</th>
                                <th>Orders</th>
                                <th>Total Spent</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($user['name'] ?? '') ?><br>
                                        <small class="text-muted"><?= htmlspecialchars($user['email'] ?? '') ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($user['phone'] ?? '') ?></td>
                                    <td>
                                        <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : 'primary' ?>">
                                            <?= ucfirst($user['role']) ?>
                                        </span>
                                    </td>
                                    <td><?= number_format($user['order_count']) ?></td>
                                    <td>₱<?= number_format($user['total_spent'] ?? 0, 2) ?></td>
                                    <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" 
                                                    class="btn btn-sm btn-info" 
                                                    onclick="viewUser(<?= $user['id'] ?>)"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($user['role'] !== 'admin'): ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-warning" 
                                                        onclick="editUser(<?= $user['id'] ?>)"
                                                        title="Edit User">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" 
                                                        class="btn btn-sm btn-danger" 
                                                        onclick="deleteUser(<?= $user['id'] ?>)"
                                                        title="Delete User">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
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

<!-- Single User Action Modal -->
<div class="modal fade" id="userActionModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">User Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="userActionContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
// Function to show loading state
function showLoading() {
    document.getElementById('userActionContent').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading user details...</p>
        </div>
    `;
}

// Function to view user details
function viewUser(userId) {
    const modal = new bootstrap.Modal(document.getElementById('userActionModal'));
    showLoading();
    modal.show();

    // Fetch user details
    fetch(`get_user_details.php?id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const user = data.user;
                
                // Different content for admin users
                if (user.role === 'admin') {
                    document.getElementById('userActionContent').innerHTML = `
                        <div class="text-start">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Personal Information</h6>
                                    <p class="mb-1"><strong>Name:</strong> ${user.name}</p>
                                    <p class="mb-1"><strong>Email:</strong> ${user.email}</p>
                                    <p class="mb-1"><strong>Phone:</strong> ${user.phone}</p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Account Information</h6>
                                    <p class="mb-1">
                                        <strong>Role:</strong> 
                                        <span class="badge bg-danger">Admin</span>
                                    </p>
                                    <p class="mb-1"><strong>Joined:</strong> ${new Date(user.created_at).toLocaleDateString()}</p>
                                    <p class="mb-1"><strong>Last Login:</strong> ${user.last_login ? new Date(user.last_login).toLocaleString() : 'Never'}</p>
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    // Regular user view with orders
                    const orders = data.orders;
                    
                    // Create orders HTML
                    let ordersHtml = '';
                    orders.forEach(order => {
                        ordersHtml += `
                            <tr>
                                <td>#${order.id}</td>
                                <td>${new Date(order.created_at).toLocaleDateString()}</td>
                                <td>₱${parseFloat(order.total_amount).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                                <td>
                                    <span class="badge bg-${order.payment_status === 'paid' ? 'success' : 
                                        (order.payment_status === 'cancelled' ? 'danger' : 'warning')}">
                                        ${order.payment_status.charAt(0).toUpperCase() + order.payment_status.slice(1)}
                                    </span>
                                </td>
                            </tr>
                        `;
                    });

                    // Update modal content for regular users
                    document.getElementById('userActionContent').innerHTML = `
                        <div class="text-start">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6>Personal Information</h6>
                                    <p class="mb-1"><strong>Name:</strong> ${user.name}</p>
                                    <p class="mb-1"><strong>Email:</strong> ${user.email}</p>
                                    <p class="mb-1"><strong>Phone:</strong> ${user.phone}</p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Account Information</h6>
                                    <p class="mb-1">
                                        <strong>Role:</strong> 
                                        <span class="badge bg-primary">User</span>
                                    </p>
                                    <p class="mb-1"><strong>Joined:</strong> ${new Date(user.created_at).toLocaleDateString()}</p>
                                    <p class="mb-1"><strong>Last Login:</strong> ${user.last_login ? new Date(user.last_login).toLocaleString() : 'Never'}</p>
                                </div>
                            </div>
                            <h6>Order History</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${ordersHtml}
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-4 text-end">
                                <button type="button" class="btn btn-warning me-2" onclick="editUser(${user.id})">
                                    <i class="fas fa-edit"></i> Edit User
                                </button>
                                <button type="button" class="btn btn-danger" onclick="deleteUser(${user.id})">
                                    <i class="fas fa-trash"></i> Delete User
                                </button>
                            </div>
                        </div>
                    `;
                }
            } else {
                document.getElementById('userActionContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> Failed to load user details
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('userActionContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> Failed to load user details
                </div>
            `;
        });
}

// Function to edit user
function editUser(userId) {
    const modal = new bootstrap.Modal(document.getElementById('userActionModal'));
    showLoading();
    modal.show();

    // Fetch user details
    fetch(`get_user_details.php?id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const user = data.user;
                
                // Update modal content
                document.getElementById('userActionContent').innerHTML = `
                    <form action="update_user.php" method="POST" id="editUserForm">
                        <input type="hidden" name="user_id" value="${user.id}">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" value="${user.name}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="${user.email}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-control" value="${user.phone}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" required>
                                <option value="user" ${user.role === 'user' ? 'selected' : ''}>User</option>
                                <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Admin</option>
                            </select>
                        </div>
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary me-2" onclick="viewUser(${user.id})">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                `;

                // Add form submit handler
                document.getElementById('editUserForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    
                    fetch('update_user.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.reload();
                        } else {
                            alert(data.message || 'Failed to update user');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Failed to update user');
                    });
                });
            } else {
                document.getElementById('userActionContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> Failed to load user details
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('userActionContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> Failed to load user details
                </div>
            `;
        });
}

// Function to delete user
function deleteUser(userId) {
    const modal = new bootstrap.Modal(document.getElementById('userActionModal'));
    showLoading();
    modal.show();

    // Fetch user details
    fetch(`get_user_details.php?id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const user = data.user;
                
                // Update modal content
                document.getElementById('userActionContent').innerHTML = `
                    <div class="text-center">
                        <div class="mb-4">
                            <i class="fas fa-exclamation-triangle fa-3x text-danger"></i>
                        </div>
                        <h5>Are you sure you want to delete this user?</h5>
                        <div class="text-start mt-4">
                            <p class="mb-1"><strong>Name:</strong> ${user.name}</p>
                            <p class="mb-1"><strong>Email:</strong> ${user.email}</p>
                            <p class="mb-1"><strong>Orders:</strong> ${user.order_count}</p>
                        </div>
                        <div class="mt-4">
                            <button type="button" class="btn btn-secondary me-2" onclick="viewUser(${user.id})">Cancel</button>
                            <button type="button" class="btn btn-danger" onclick="confirmDelete(${user.id})">
                                Delete User
                            </button>
                        </div>
                    </div>
                `;
            } else {
                document.getElementById('userActionContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> Failed to load user details
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('userActionContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> Failed to load user details
                </div>
            `;
        });
}

// Function to confirm user deletion
function confirmDelete(userId) {
    const formData = new FormData();
    formData.append('user_id', userId);

    fetch('delete_user.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Failed to delete user');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to delete user');
    });
}
</script>

<style>
#userActionModal .modal-dialog {
    max-width: 800px;
}
#userActionModal .modal-body {
    padding: 1.5rem;
}
</style> 