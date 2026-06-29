<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$conn    = getDBConnection();
$user_id = $_SESSION['user_id'];
$success = '';
$errors  = [];

// Load current user data
$stmt = $conn->prepare("SELECT full_name, email, phone, user_type, created_at FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle profile update
if (isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $phone     = trim($_POST['phone']);

    if (empty($full_name)) {
        $errors[] = "Full name is required.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ? WHERE user_id = ?");
        $stmt->bind_param("ssi", $full_name, $phone, $user_id);
        $stmt->execute();
        $_SESSION['full_name'] = $full_name;
        $user['full_name']     = $full_name;
        $user['phone']         = $phone;
        $success = "Profile updated successfully.";
    }
}

// Handle password change
if (isset($_POST['change_password'])) {
    $current  = $_POST['current_password'];
    $new_pw   = $_POST['new_password'];
    $confirm  = $_POST['confirm_password'];

    // Verify current password
    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!password_verify($current, $row['password_hash'])) {
        $errors[] = "Current password is incorrect.";
    } elseif (strlen($new_pw) < 6) {
        $errors[] = "New password must be at least 6 characters.";
    } elseif ($new_pw !== $confirm) {
        $errors[] = "New passwords do not match.";
    } else {
        $hashed = password_hash($new_pw, PASSWORD_DEFAULT);
        $stmt   = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
        $stmt->bind_param("si", $hashed, $user_id);
        $stmt->execute();
        $success = "Password changed successfully.";
    }
}

// Stats for the user
$order_count     = $conn->prepare("SELECT COUNT(*) AS c FROM orders WHERE buyer_id = ?");
$order_count->bind_param("i", $user_id);
$order_count->execute();
$total_orders    = $order_count->get_result()->fetch_assoc()['c'];

$product_count   = 0;
$total_sales     = 0;
if (isSeller()) {
    $pc = $conn->prepare("SELECT COUNT(*) AS c FROM products WHERE seller_id = ?");
    $pc->bind_param("i", $user_id);
    $pc->execute();
    $product_count = $pc->get_result()->fetch_assoc()['c'];

    $ps = $conn->prepare(
        "SELECT COALESCE(SUM(oi.price_at_time * oi.quantity), 0) AS total
         FROM order_items oi
         JOIN products p ON oi.product_id = p.product_id
         WHERE p.seller_id = ?"
    );
    $ps->bind_param("i", $user_id);
    $ps->execute();
    $total_sales = $ps->get_result()->fetch_assoc()['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - TownSquare Market</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-store me-2"></i>TownSquare Market
            </a>
            <a href="index.php" class="btn btn-outline-light btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Back to Home
            </a>
        </div>
    </nav>

    <div class="container my-5">

        <!-- Profile Header -->
        <div class="card mb-4" style="background:linear-gradient(135deg,#667eea,#764ba2); border:none; color:#fff;">
            <div class="card-body p-4 d-flex align-items-center gap-4 flex-wrap">
                <div class="rounded-circle d-flex align-items-center justify-content-center bg-white text-primary fw-bold"
                     style="width:72px; height:72px; font-size:1.8rem; flex-shrink:0;">
                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                </div>
                <div>
                    <h3 class="mb-0 fw-bold"><?php echo htmlspecialchars($user['full_name']); ?></h3>
                    <div class="opacity-90"><?php echo htmlspecialchars($user['email']); ?></div>
                    <span class="badge bg-white text-dark mt-1"><?php echo ucfirst($user['user_type']); ?></span>
                    <span class="ms-2 opacity-75" style="font-size:0.85rem;">
                        Member since <?php echo date('M Y', strtotime($user['created_at'])); ?>
                    </span>
                </div>
                <div class="ms-auto d-flex gap-4 flex-wrap text-center">
                    <div>
                        <div class="fw-bold fs-4"><?php echo $total_orders; ?></div>
                        <small class="opacity-75">Orders Placed</small>
                    </div>
                    <?php if (isSeller()): ?>
                        <div>
                            <div class="fw-bold fs-4"><?php echo $product_count; ?></div>
                            <small class="opacity-75">Products Listed</small>
                        </div>
                        <div>
                            <div class="fw-bold fs-4">R<?php echo number_format($total_sales, 0); ?></div>
                            <small class="opacity-75">Total Sales</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger auto-dismiss">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $e): ?>
                        <li><?php echo htmlspecialchars($e); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success auto-dismiss">
                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">

            <!-- Edit Profile -->
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">
                        <i class="fas fa-user-edit me-2 text-primary"></i>Edit Profile
                    </div>
                    <div class="card-body p-4">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="full_name" class="form-control"
                                       value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control"
                                       value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                <small class="text-muted">Email address cannot be changed.</small>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="phone" class="form-control"
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                       placeholder="0712345678">
                            </div>
                            <button type="submit" name="update_profile" class="btn btn-primary w-100">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Change Password -->
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">
                        <i class="fas fa-lock me-2 text-primary"></i>Change Password
                    </div>
                    <div class="card-body p-4">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Current Password</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-control"
                                       placeholder="At least 6 characters" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                            <button type="submit" name="change_password" class="btn btn-outline-primary w-100">
                                <i class="fas fa-key me-2"></i>Update Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header fw-semibold">
                        <i class="fas fa-th-large me-2 text-primary"></i>Quick Access
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6 col-md-3">
                                <a href="my_orders.php" class="card text-decoration-none text-center p-3 d-block">
                                    <i class="fas fa-truck fa-2x text-info mb-2"></i>
                                    <div class="fw-semibold">My Orders</div>
                                </a>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <a href="cart.php" class="card text-decoration-none text-center p-3 d-block">
                                    <i class="fas fa-shopping-cart fa-2x text-success mb-2"></i>
                                    <div class="fw-semibold">Shopping Cart</div>
                                </a>
                            </div>
                            <?php if (isSeller()): ?>
                                <div class="col-sm-6 col-md-3">
                                    <a href="my_products.php" class="card text-decoration-none text-center p-3 d-block">
                                        <i class="fas fa-box fa-2x text-primary mb-2"></i>
                                        <div class="fw-semibold">My Products</div>
                                    </a>
                                </div>
                                <div class="col-sm-6 col-md-3">
                                    <a href="sell.php" class="card text-decoration-none text-center p-3 d-block">
                                        <i class="fas fa-plus-circle fa-2x text-warning mb-2"></i>
                                        <div class="fw-semibold">List a Product</div>
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="col-sm-6 col-md-3">
                                    <a href="complaints.php" class="card text-decoration-none text-center p-3 d-block">
                                        <i class="fas fa-flag fa-2x text-danger mb-2"></i>
                                        <div class="fw-semibold">Reports</div>
                                    </a>
                                </div>
                                <div class="col-sm-6 col-md-3">
                                    <a href="index.php" class="card text-decoration-none text-center p-3 d-block">
                                        <i class="fas fa-store fa-2x text-secondary mb-2"></i>
                                        <div class="fw-semibold">Browse Products</div>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
</body>
</html>