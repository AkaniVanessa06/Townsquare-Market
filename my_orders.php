<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$conn    = getDBConnection();
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare(
    "SELECT o.*, COUNT(oi.order_item_id) AS item_count
     FROM orders o
     LEFT JOIN order_items oi ON o.order_id = oi.order_id
     WHERE o.buyer_id = ?
     GROUP BY o.order_id
     ORDER BY o.order_date DESC"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - TownSquare Market</title>
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
            <a href="index.php" class="btn btn-outline-light btn-sm">Continue Shopping</a>
        </div>
    </nav>

    <div class="container my-5">
        <h2 class="fw-bold mb-4">
            <i class="fas fa-truck me-2"></i>My Orders
        </h2>

        <?php if (isset($_SESSION['order_success'])): ?>
            <div class="alert alert-success auto-dismiss d-flex align-items-center gap-2">
                <i class="fas fa-check-circle"></i>
                <?php echo $_SESSION['order_success']; unset($_SESSION['order_success']); ?>
            </div>
        <?php endif; ?>

        <?php if ($orders->num_rows > 0): ?>
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th>Shipping</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Payment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($order = $orders->fetch_assoc()): ?>
                                <?php
                                $status_map = [
                                    'processing' => ['warning',  'Processing'],
                                    'shipped'    => ['info',     'Shipped'],
                                    'delivered'  => ['success',  'Delivered'],
                                    'cancelled'  => ['danger',   'Cancelled'],
                                ];
                                $pay_map = [
                                    'pending'   => ['secondary', 'Pending'],
                                    'completed' => ['success',   'Paid'],
                                    'failed'    => ['danger',    'Failed'],
                                ];
                                $s = $status_map[$order['order_status']] ?? ['secondary', ucfirst($order['order_status'])];
                                $p = $pay_map[$order['payment_status']]  ?? ['secondary', ucfirst($order['payment_status'])];
                                ?>
                                <tr>
                                    <td><strong>#<?php echo $order['order_id']; ?></strong></td>
                                    <td><?php echo date('d M Y', strtotime($order['order_date'])); ?></td>
                                    <td><?php echo $order['item_count']; ?> item(s)</td>
                                    <td>R<?php echo number_format($order['shipping_cost'], 2); ?></td>
                                    <td class="fw-semibold">R<?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td><span class="badge bg-<?php echo $s[0]; ?>"><?php echo $s[1]; ?></span></td>
                                    <td><span class="badge bg-<?php echo $p[0]; ?>"><?php echo $p[1]; ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-shopping-bag fa-4x text-muted mb-4"></i>
                <h4 class="text-muted mb-3">No orders yet</h4>
                <p class="text-muted mb-4">You haven't placed any orders. Start shopping today!</p>
                <a href="index.php" class="btn btn-primary btn-lg">Browse Products</a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
</body>
</html>