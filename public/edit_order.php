<?php
include 'user_auth.php';
include '../config/db.php';

$orderId = $_GET['id'] ?? null;
$userId = $_SESSION['user_id'];

// Get current order
$stmt = $pdo->prepare("SELECT coffin_id FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch();

if (!$order) { echo "Invalid order."; exit; }

// On update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newDesign = $_POST['coffin_id'];
    $pdo->prepare("UPDATE orders SET coffin_id = ? WHERE id = ? AND user_id = ?")->execute([$newDesign, $orderId, $userId]);
    header("Location: my_orders.php");
    exit;
}

// Load all coffin designs
$designs = $pdo->query("SELECT id, name FROM coffin_designs")->fetchAll();
?>

<h2>Modify Order #<?= $orderId ?></h2>
<form method="POST">
    <div class="mb-3">
        <label>Select New Design</label>
        <select name="coffin_id" class="form-control">
            <?php foreach ($designs as $d): ?>
                <option value="<?= $d['id'] ?>" <?= $d['id'] == $order['coffin_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($d['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-primary">Update Design</button>
</form>
