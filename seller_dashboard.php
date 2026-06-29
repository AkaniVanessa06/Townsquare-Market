<?php
require_once 'config.php';

if (!isSeller()) {
    header("Location: login.php");
    exit();
}

$conn    = getDBConnection();
$user_id = $_SESSION['user_id'];

// Stats
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM products WHERE seller_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_products = $stmt->get_result()->fetch_assoc()['c'];

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM products WHERE seller_id = ? AND status = 'active'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$active_products = $stmt->get_result()->fetch_assoc()['c'];

$stmt = $conn->prepare(
    "SELECT COALESCE(SUM(oi.price_at_time * oi.quantity), 0) AS total, COUNT(DISTINCT oi.order_id) AS cnt
     FROM order_items oi
     JOIN products p ON oi.product_id = p.product_id
     WHERE p.seller_id = ?"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$sales_row    = $stmt->get_result()->fetch_assoc();
$total_sales  = $sales_row['total'];
$total_orders = $sales_row['cnt'];

// Recent orders for seller's products
$stmt = $conn->prepare(
    "SELECT o.order_id, o.order_date, o.order_status, u.full_name AS buyer_name,
            p.product_name, oi.quantity, oi.price_at_time
     FROM order_items oi
     JOIN products p  ON oi.product_id = p.product_id
     JOIN orders o    ON oi.order_id   = o.order_id
     JOIN users u     ON o.buyer_id    = u.user_id
     WHERE p.seller_id = ?
     ORDER BY o.order_date DESC
     LIMIT 10"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_orders = $stmt->get_result();

// Top products by units sold
$stmt = $conn->prepare(
    "SELECT p.product_name, p.price_including_vat, p.quantity_in_stock, p.status,
            COALESCE(SUM(oi.quantity), 0) AS units_sold
     FROM products p
     LEFT JOIN order_items oi ON p.product_id = oi.product_id
     WHERE p.seller_id = ?
     GROUP BY p.product_id
     ORDER BY units_sold DESC
     LIMIT 5"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$top_products = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Dashboard - TownSquare Market</title>
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
            <div class="d-flex gap-2 align-items-center">
                <span class="text-white opacity-75" style="font-size:0.9rem;">
                    <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Sign Out</a>
            </div>
        </div>
    </nav>

    <div class="container my-5">

        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <h3 class="fw-bold mb-0">Seller Dashboard</h3>
                <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
            </div>
            <a href="sell.php" class="btn btn-success">
                <i class="fas fa-plus me-2"></i>List New Product
            </a>
        </div>

        <!-- Stats Row -->
        <div class="row g-4 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="card stats-card bg-primary text-white border-0">
                    <div class="card-body">
                        <h2 class="fw-bold mb-0"><?php echo $active_products; ?></h2>
                        <p class="mb-0 opacity-90">Active Listings</p>
                        <i class="fas fa-box stat-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card stats-card bg-success text-white border-0">
                    <div class="card-body">
                        <h2 class="fw-bold mb-0"><?php echo $total_orders; ?></h2>
                        <p class="mb-0 opacity-90">Total Orders</p>
                        <i class="fas fa-shopping-bag stat-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card stats-card bg-info text-white border-0">
                    <div class="card-body">
                        <h2 class="fw-bold mb-0">R<?php echo number_format($total_sales, 0); ?></h2>
                        <p class="mb-0 opacity-90">Total Revenue</p>
                        <i class="fas fa-chart-line stat-icon"></i>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card stats-card bg-warning text-white border-0">
                    <div class="card-body">
                        <h2 class="fw-bold mb-0"><?php echo $total_products; ?></h2>
                        <p class="mb-0 opacity-90">Total Products</p>
                        <i class="fas fa-tags stat-icon"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">

            <!-- Recent Orders -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">
                            <i class="fas fa-clock me-2 text-primary"></i>Recent Sales
                        </span>
                        <a href="my_products.php" class="btn btn-sm btn-outline-primary">Manage Products</a>
                    </div>
                    <?php if ($recent_orders->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Buyer</th>
                                        <th>Product</th>
                                        <th>Qty</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $status_map = [
                                        'processing' => 'warning',
                                        'shipped'    => 'info',
                                        'delivered'  => 'success',
                                        'cancelled'  => 'danger',
                                    ];
                                    while ($row = $recent_orders->fetch_assoc()):
                                        $sc = $status_map[$row['order_status']] ?? 'secondary';
                                    ?>
                                        <tr>
                                            <td><strong>#<?php echo $row['order_id']; ?></strong></td>
                                            <td><?php echo htmlspecialchars($row['buyer_name']); ?></td>
                                            <td style="max-width:160px;">
                                                <span class="text-truncate d-block" title="<?php echo htmlspecialchars($row['product_name']); ?>">
                                                    <?php echo htmlspecialchars($row['product_name']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $row['quantity']; ?></td>
                                            <td class="fw-semibold">
                                                R<?php echo number_format($row['price_at_time'] * $row['quantity'], 2); ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $sc; ?>">
                                                    <?php echo ucfirst($row['order_status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="card-body text-center py-5 text-muted">
                            <i class="fas fa-shopping-bag fa-3x mb-3"></i>
                            <p>No sales yet. Share your listings to get your first order!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top Products + Quick Links -->
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header fw-semibold">
                        <i class="fas fa-star me-2 text-warning"></i>Top Products
                    </div>
                    <div class="card-body p-0">
                        <?php
                        $first = true;
                        while ($p = $top_products->fetch_assoc()):
                        ?>
                            <div class="px-3 py-2 <?php echo !$first ? 'border-top' : ''; ?> d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold" style="font-size:0.9rem;">
                                        <?php echo htmlspecialchars(substr($p['product_name'], 0, 28)); ?>
                                        <?php echo strlen($p['product_name']) > 28 ? '…' : ''; ?>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo $p['units_sold']; ?> sold &middot;
                                        <?php echo $p['quantity_in_stock']; ?> left
                                    </small>
                                </div>
                                <span class="badge bg-<?php echo $p['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($p['status']); ?>
                                </span>
                            </div>
                            <?php $first = false; ?>
                        <?php endwhile; ?>
                        <?php if ($total_products === 0): ?>
                            <div class="px-3 py-4 text-center text-muted" style="font-size:0.9rem;">
                                No products yet.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header fw-semibold">
                        <i class="fas fa-th-large me-2 text-primary"></i>Quick Actions
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="sell.php" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                            <i class="fas fa-plus-circle text-success"></i> List New Product
                        </a>
                        <a href="my_products.php" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                            <i class="fas fa-boxes text-primary"></i> Manage My Products
                        </a>
                        <a href="profile.php" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                            <i class="fas fa-user-edit text-info"></i> Edit Profile
                        </a>
                        <a href="complaints.php" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                            <i class="fas fa-flag text-warning"></i> Report an Issue
                        </a>
                        <a href="index.php" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                            <i class="fas fa-store text-secondary"></i> View Marketplace
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
</body>
</html>