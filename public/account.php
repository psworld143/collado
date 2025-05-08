<?php include 'user_auth.php'; include '../config/db.php'; ?>

<h2>My Account</h2>
<p><strong>Name:</strong> <?= $_SESSION['user'] ?></p>
<p><strong>Email:</strong> <?= $_SESSION['user_email'] ?></p>

<a href="my_orders.php" class="btn btn-primary">View My Orders</a>
<a href="logout.php" class="btn btn-secondary">Logout</a>
