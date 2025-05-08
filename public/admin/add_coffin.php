<?php
require_once '../../config/db.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_STRING);
    $in_stock = isset($_POST['in_stock']) ? 1 : 0;
    $image = '';

    // Validate inputs
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    if (empty($description)) {
        $errors[] = "Description is required";
    }
    if ($price === false || $price <= 0) {
        $errors[] = "Valid price is required";
    }
    if (empty($category)) {
        $errors[] = "Category is required";
    }

    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB

        if (!in_array($_FILES['image']['type'], $allowed_types)) {
            $errors[] = "Invalid image type. Only JPG, PNG and GIF are allowed.";
        } elseif ($_FILES['image']['size'] > $max_size) {
            $errors[] = "Image size should be less than 5MB.";
        } else {
            $upload_dir = '../../uploads/coffins/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '.' . $file_extension;
            $target_path = $upload_dir . $file_name;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                $image = 'uploads/coffins/' . $file_name;
            } else {
                $errors[] = "Failed to upload image.";
            }
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO coffin_designs (name, description, price, category, in_stock, image, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$name, $description, $price, $category, $in_stock, $image]);

            $_SESSION['success'] = "Coffin design added successfully!";
            header("Location: manage_coffins.php");
            exit;
        } catch (PDOException $e) {
            error_log("Add coffin error: " . $e->getMessage());
            $errors[] = "An error occurred while adding the coffin design.";
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
                    <i class="fas fa-plus"></i> Add New Coffin Design
                </h2>
                <a href="manage_coffins.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Manage Coffins
                </a>
            </div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                <div class="row">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>" 
                                   required>
                            <div class="invalid-feedback">Please enter a name.</div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" 
                                      rows="4" required><?= isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '' ?></textarea>
                            <div class="invalid-feedback">Please enter a description.</div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="price" class="form-label">Price (₱)</label>
                                    <input type="number" class="form-control" id="price" name="price" 
                                           step="0.01" min="0" 
                                           value="<?= isset($_POST['price']) ? htmlspecialchars($_POST['price']) : '' ?>" 
                                           required>
                                    <div class="invalid-feedback">Please enter a valid price.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="category" class="form-label">Category</label>
                                    <select class="form-select" id="category" name="category" required>
                                        <option value="">Select Category</option>
                                        <option value="wood" <?= (isset($_POST['category']) && $_POST['category'] === 'wood') ? 'selected' : '' ?>>Wood</option>
                                        <option value="metal" <?= (isset($_POST['category']) && $_POST['category'] === 'metal') ? 'selected' : '' ?>>Metal</option>
                                        <option value="premium" <?= (isset($_POST['category']) && $_POST['category'] === 'premium') ? 'selected' : '' ?>>Premium</option>
                                    </select>
                                    <div class="invalid-feedback">Please select a category.</div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="in_stock" name="in_stock" 
                                       <?= (!isset($_POST['in_stock']) || $_POST['in_stock']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="in_stock">In Stock</label>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="image" class="form-label">Image</label>
                            <input type="file" class="form-control" id="image" name="image" 
                                   accept="image/jpeg,image/png,image/gif">
                            <div class="form-text">Max file size: 5MB. Allowed types: JPG, PNG, GIF</div>
                        </div>

                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">Preview</h6>
                                <div id="imagePreview" class="text-center p-3 bg-light rounded">
                                    <i class="fas fa-image fa-3x text-muted"></i>
                                    <p class="mt-2 mb-0 text-muted">No image selected</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Coffin Design
                    </button>
                    <a href="manage_coffins.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Form validation
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            form.classList.add('was-validated')
        }, false)
    })
})()

// Image preview
document.getElementById('image').addEventListener('change', function(e) {
    const preview = document.getElementById('imagePreview');
    const file = e.target.files[0];
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" class="img-fluid" alt="Preview">`;
        }
        reader.readAsDataURL(file);
    } else {
        preview.innerHTML = `
            <i class="fas fa-image fa-3x text-muted"></i>
            <p class="mt-2 mb-0 text-muted">No image selected</p>
        `;
    }
});
</script>

<?php include '../../includes/footer.php'; ?> 