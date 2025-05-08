<?php
require_once '../../config/db.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Get filter parameters
$action_type = isset($_GET['action_type']) ? htmlspecialchars($_GET['action_type'], ENT_QUOTES, 'UTF-8') : '';
$date_from = isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from'], ENT_QUOTES, 'UTF-8') : '';
$date_to = isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to'], ENT_QUOTES, 'UTF-8') : '';

// Build query
$sql = "SELECT al.*, u.name as admin_name 
        FROM activity_logs al
        JOIN users u ON al.user_id = u.id
        WHERE 1=1";
$params = [];

if (!empty($action_type)) {
    $sql .= " AND al.action_type = ?";
    $params[] = $action_type;
}

if (!empty($date_from)) {
    $sql .= " AND DATE(al.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $sql .= " AND DATE(al.created_at) <= ?";
    $params[] = $date_to;
}

$sql .= " ORDER BY al.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

include 'includes/admin_nav.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0">
                    <i class="fas fa-history"></i> Activity Log
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
                <div class="col-md-3">
                    <label class="form-label">Action Type</label>
                    <select name="action_type" class="form-select">
                        <option value="">All Actions</option>
                        <option value="login" <?= $action_type === 'login' ? 'selected' : '' ?>>Login</option>
                        <option value="create" <?= $action_type === 'create' ? 'selected' : '' ?>>Create</option>
                        <option value="update" <?= $action_type === 'update' ? 'selected' : '' ?>>Update</option>
                        <option value="delete" <?= $action_type === 'delete' ? 'selected' : '' ?>>Delete</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="activity_log.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Activity Log Table -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($logs)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No activity logs found.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Admin</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?= date('M d, Y H:i:s', strtotime($log['created_at'])) ?></td>
                                    <td><?= htmlspecialchars($log['admin_name']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= getActionColor($log['action_type']) ?>">
                                            <?= ucfirst($log['action_type']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($log['details']) ?></td>
                                    <td><?= htmlspecialchars($log['ip_address']) ?></td>
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
function getActionColor($action) {
    return match($action) {
        'login' => 'info',
        'create' => 'success',
        'update' => 'primary',
        'delete' => 'danger',
        default => 'secondary'
    };
}

include '../../includes/footer.php';
?> 