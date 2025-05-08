<?php
require_once '../../config/db.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Get filter parameters
$type = isset($_GET['type']) ? htmlspecialchars($_GET['type'], ENT_QUOTES, 'UTF-8') : '';
$status = isset($_GET['status']) ? htmlspecialchars($_GET['status'], ENT_QUOTES, 'UTF-8') : '';

// Build query
$sql = "SELECT * FROM notifications WHERE 1=1";
$params = [];

if (!empty($type)) {
    $sql .= " AND type = ?";
    $params[] = $type;
}

if (!empty($status)) {
    $sql .= " AND status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notifications = $stmt->fetchAll();

// Mark notifications as read
if (isset($_POST['mark_read']) && isset($_POST['notification_ids'])) {
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET status = 'read', updated_at = NOW()
            WHERE id IN (" . implode(',', array_fill(0, count($_POST['notification_ids']), '?')) . ")
        ");
        $stmt->execute($_POST['notification_ids']);
        
        // Log the activity
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action_type, details, ip_address, created_at)
            VALUES (?, 'update', 'Marked notifications as read', ?, NOW())
        ");
        $stmt->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);
        
        header("Location: notifications.php");
        exit;
    } catch (PDOException $e) {
        error_log("Notification update error: " . $e->getMessage());
        $errors[] = "An error occurred while updating notifications.";
    }
}

include 'includes/admin_nav.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0">
                    <i class="fas fa-bell"></i> Notifications
                </h2>
                <div>
                    <form method="POST" class="d-inline" id="markReadForm">
                        <input type="hidden" name="notification_ids" id="notificationIds">
                        <button type="submit" name="mark_read" class="btn btn-primary" id="markReadBtn" disabled>
                            <i class="fas fa-check-double"></i> Mark Selected as Read
                        </button>
                    </form>
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
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="order" <?= $type === 'order' ? 'selected' : '' ?>>Order</option>
                        <option value="inventory" <?= $type === 'inventory' ? 'selected' : '' ?>>Inventory</option>
                        <option value="system" <?= $type === 'system' ? 'selected' : '' ?>>System</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="unread" <?= $status === 'unread' ? 'selected' : '' ?>>Unread</option>
                        <option value="read" <?= $status === 'read' ? 'selected' : '' ?>>Read</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="notifications.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Notifications List -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($notifications)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No notifications found.
                </div>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input notification-checkbox" 
                                           value="<?= $notification['id'] ?>" 
                                           <?= $notification['status'] === 'read' ? 'disabled' : '' ?>>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">
                                            <?php if ($notification['status'] === 'unread'): ?>
                                                <span class="badge bg-danger me-2">New</span>
                                            <?php endif; ?>
                                            <?= htmlspecialchars($notification['title']) ?>
                                        </h6>
                                        <small class="text-muted">
                                            <?= date('M d, Y H:i', strtotime($notification['created_at'])) ?>
                                        </small>
                                    </div>
                                    <p class="mb-1"><?= htmlspecialchars($notification['message']) ?></p>
                                    <?php if (!empty($notification['link'])): ?>
                                        <a href="<?= htmlspecialchars($notification['link']) ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-external-link-alt"></i> View Details
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.notification-checkbox');
    const markReadBtn = document.getElementById('markReadBtn');
    const notificationIds = document.getElementById('notificationIds');
    
    function updateMarkReadButton() {
        const selectedIds = Array.from(checkboxes)
            .filter(cb => cb.checked)
            .map(cb => cb.value);
        
        markReadBtn.disabled = selectedIds.length === 0;
        notificationIds.value = selectedIds.join(',');
    }
    
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateMarkReadButton);
    });
});
</script>

<?php include '../../includes/footer.php'; ?> 