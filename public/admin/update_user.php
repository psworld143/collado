<?php
require_once '../../config/db.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);

    if ($user_id && $name && $email && $phone && in_array($role, ['user', 'admin'])) {
        try {
            // Check if email is already taken by another user
            $stmt = $pdo->prepare("
                SELECT id FROM users 
                WHERE email = ? AND id != ?
            ");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                $_SESSION['error'] = "Email is already taken by another user.";
                header("Location: manage_users.php");
                exit;
            }

            // Update user
            $stmt = $pdo->prepare("
                UPDATE users 
                SET name = ?,
                    email = ?,
                    phone = ?,
                    role = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$name, $email, $phone, $role, $user_id]);

            $_SESSION['success'] = "User updated successfully!";
        } catch (PDOException $e) {
            error_log("Update user error: " . $e->getMessage());
            $_SESSION['error'] = "An error occurred while updating the user.";
        }
    } else {
        $_SESSION['error'] = "Invalid input data.";
    }
}

// Redirect back to user management
header("Location: manage_users.php");
exit; 