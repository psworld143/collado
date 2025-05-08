<?php
require_once '../../config/db.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input using htmlspecialchars instead of FILTER_SANITIZE_STRING
    $name = htmlspecialchars(trim($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $description = htmlspecialchars(trim($_POST['description'] ?? ''), ENT_QUOTES, 'UTF-8');
    $price = filter_var($_POST['price'] ?? 0, FILTER_VALIDATE_FLOAT);
    $stock = filter_var($_POST['stock'] ?? 0, FILTER_VALIDATE_INT);
    $category = htmlspecialchars(trim($_POST['category'] ?? ''), ENT_QUOTES, 'UTF-8');

    // Validate required fields
    if (empty($name) || empty($description) || $price === false || $stock === false || empty($category)) {
        $error = "All fields are required and must be valid.";
    } else {
        try {
            // Start transaction
            $pdo->beginTransaction();

            // Insert coffin data
            $stmt = $pdo->prepare("
                INSERT INTO coffins (name, description, price, stock, category) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $description, $price, $stock, $category]);
            $coffin_id = $pdo->lastInsertId();

            // Handle image upload
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../../uploads/coffins/';
                
                // Create directory if it doesn't exist
                if (!file_exists($upload_dir)) {
                    // Try to create the directory with full permissions
                    if (!@mkdir($upload_dir, 0777, true)) {
                        // If mkdir fails, try to create parent directories first
                        $parent_dir = dirname($upload_dir);
                        if (!file_exists($parent_dir)) {
                            if (!@mkdir($parent_dir, 0777, true)) {
                                throw new Exception("Failed to create parent directory. Please check directory permissions.");
                            }
                        }
                        // Try creating the uploads directory again
                        if (!@mkdir($upload_dir, 0777, true)) {
                            throw new Exception("Failed to create upload directory. Please check directory permissions.");
                        }
                    }
                }

                // Ensure directory is writable
                if (!is_writable($upload_dir)) {
                    if (!@chmod($upload_dir, 0777)) {
                        throw new Exception("Upload directory is not writable. Please check directory permissions.");
                    }
                }

                $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

                if (!in_array($file_extension, $allowed_extensions)) {
                    throw new Exception("Invalid file type. Only JPG, JPEG, PNG & GIF files are allowed.");
                }

                $new_filename = uniqid() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;

                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    // Update coffin with image path
                    $image_path = 'uploads/coffins/' . $new_filename;
                    $stmt = $pdo->prepare("UPDATE coffins SET image = ? WHERE id = ?");
                    $stmt->execute([$image_path, $coffin_id]);
                } else {
                    throw new Exception("Failed to upload image");
                }
            }

            $pdo->commit();
            $success = "Coffin added successfully!";
            
            // Clear form data
            $name = $description = $category = '';
            $price = $stock = 0;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

include 'includes/admin_nav.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0">
                    <i class="fas fa-plus"></i> Add New Coffin
                </h2>
                <a href="manage_coffins.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Coffins
                </a>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?= $success ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($name ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" required><?= htmlspecialchars($description ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Price (₱)</label>
                            <input type="number" name="price" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($price ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Stock</label>
                            <input type="number" name="stock" class="form-control" min="0" value="<?= htmlspecialchars($stock ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select" required>
                                <option value="">Select Category</option>
                                <option value="wooden" <?= ($category ?? '') === 'wooden' ? 'selected' : '' ?>>Wooden</option>
                                <option value="metal" <?= ($category ?? '') === 'metal' ? 'selected' : '' ?>>Metal</option>
                                <option value="fiberglass" <?= ($category ?? '') === 'fiberglass' ? 'selected' : '' ?>>Fiberglass</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Image</label>
                            <input type="file" name="image" class="form-control" accept="image/*" required>
                            <small class="text-muted">Allowed formats: JPG, JPEG, PNG, GIF</small>
                        </div>
                    </div>
                </div>
                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Add Coffin
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?> 