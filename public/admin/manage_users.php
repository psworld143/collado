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
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#viewUserModal<?= $user['id'] ?>"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($user['role'] !== 'admin'): ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-warning" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editUserModal<?= $user['id'] ?>"
                                                        title="Edit User">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" 
                                                        class="btn btn-sm btn-danger" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deleteUserModal<?= $user['id'] ?>"
                                                        title="Delete User">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>

                                        <!-- View User Modal -->
                                        <div class="modal fade" id="viewUserModal<?= $user['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">User Details</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <!-- User Info -->
                                                        <div class="row mb-4">
                                                            <div class="col-md-6">
                                                                <h6>Personal Information</h6>
                                                                <p class="mb-1">
                                                                    <strong>Name:</strong> <?= htmlspecialchars($user['name'] ?? '') ?>
                                                                </p>
                                                                <p class="mb-1">
                                                                    <strong>Email:</strong> <?= htmlspecialchars($user['email'] ?? '') ?>
                                                                </p>
                                                                <p class="mb-1">
                                                                    <strong>Phone:</strong> <?= htmlspecialchars($user['phone'] ?? '') ?>
                                                                </p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <h6>Account Information</h6>
                                                                <p class="mb-1">
                                                                    <strong>Role:</strong> 
                                                                    <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : 'primary' ?>">
                                                                        <?= ucfirst($user['role']) ?>
                                                                    </span>
                                                                </p>
                                                                <p class="mb-1">
                                                                    <strong>Joined:</strong> <?= date('F d, Y', strtotime($user['created_at'])) ?>
                                                                </p>
                                                                <p class="mb-1">
                                                                    <strong>Last Login:</strong> <?= $user['last_login'] ? date('F d, Y H:i', strtotime($user['last_login'])) : 'Never' ?>
                                                                </p>
                                                            </div>
                                                        </div>

                                                        <!-- Order History -->
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
                                                                    <?php
                                                                    $stmt = $pdo->prepare("
                                                                        SELECT * FROM orders 
                                                                        WHERE user_id = ? 
                                                                        ORDER BY created_at DESC
                                                                    ");
                                                                    $stmt->execute([$user['id']]);
                                                                    $orders = $stmt->fetchAll();
                                                                    foreach ($orders as $order):
                                                                    ?>
                                                                        <tr>
                                                                            <td>#<?= $order['id'] ?></td>
                                                                            <td><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
                                                                            <td>₱<?= number_format($order['total_amount'], 2) ?></td>
                                                                            <td>
                                                                                <span class="badge bg-<?= $order['payment_status'] === 'paid' ? 'success' : 
                                                                                    ($order['payment_status'] === 'cancelled' ? 'danger' : 'warning') ?>">
                                                                                    <?= ucfirst($order['payment_status']) ?>
                                                                                </span>
                                                                            </td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Edit User Modal -->
                                        <?php if ($user['role'] !== 'admin'): ?>
                                            <div class="modal fade" id="editUserModal<?= $user['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form action="update_user.php" method="POST">
                                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Edit User</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Name</label>
                                                                    <input type="text" name="name" class="form-control" 
                                                                           value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label">Email</label>
                                                                    <input type="email" name="email" class="form-control" 
                                                                           value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label">Phone</label>
                                                                    <input type="tel" name="phone" class="form-control" 
                                                                           value="<?= htmlspecialchars($user['phone'] ?? '') ?>" required>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label">Role</label>
                                                                    <select name="role" class="form-select" required>
                                                                        <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                                                        <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Delete User Modal -->
                                        <?php if ($user['role'] !== 'admin'): ?>
                                            <div class="modal fade" id="deleteUserModal<?= $user['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Confirm Deletion</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Are you sure you want to delete this user?</p>
                                                            <p class="mb-0">
                                                                <strong>Name:</strong> <?= htmlspecialchars($user['name'] ?? '') ?><br>
                                                                <strong>Email:</strong> <?= htmlspecialchars($user['email'] ?? '') ?><br>
                                                                <strong>Orders:</strong> <?= number_format($user['order_count']) ?>
                                                            </p>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <form action="delete_user.php" method="POST" class="d-inline">
                                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                                <button type="submit" class="btn btn-danger">Delete User</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
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

<?php include '../../includes/footer.php'; ?> 