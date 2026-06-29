<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$conn    = getDBConnection();
$user_id = $_SESSION['user_id'];

// Load cart
$stmt = $conn->prepare(
    "SELECT c.*, p.product_name, p.discounted_price, p.seller_id, p.quantity_in_stock
     FROM cart c JOIN products p ON c.product_id = p.product_id
     WHERE c.user_id = ?"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_result = $stmt->get_result();

$subtotal = 0;
$cart_items = [];
while ($item = $cart_result->fetch_assoc()) {
    $subtotal    += $item['discounted_price'] * $item['quantity'];
    $cart_items[] = $item;
}

if (empty($cart_items)) {
    header("Location: cart.php");
    exit();
}

$shipping_method = $_POST['shipping_method'] ?? 'standard';
$shipping_cost   = ($shipping_method === 'express') ? EXPRESS_SHIPPING : STANDARD_SHIPPING;
$total           = $subtotal + $shipping_cost;

// Place order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $address        = trim($_POST['address']);
    $city           = trim($_POST['city']);
    $postal_code    = trim($_POST['postal_code']);
    $payment_method = $_POST['payment_method'];
    $ship_method    = $_POST['shipping_method'] ?? 'standard';
    $ship_cost      = ($ship_method === 'express') ? EXPRESS_SHIPPING : STANDARD_SHIPPING;
    $order_total    = $subtotal + $ship_cost;

    if (empty($address) || empty($city) || empty($postal_code)) {
        $order_error = "Please fill in all shipping fields.";
    } else {
        $order_stmt = $conn->prepare(
            "INSERT INTO orders (buyer_id, total_amount, shipping_address, shipping_city, shipping_postal_code,
                                 shipping_method, shipping_cost, payment_method, order_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'processing')"
        );
        $order_stmt->bind_param("idssssds", $user_id, $order_total, $address, $city, $postal_code, $ship_method, $ship_cost, $payment_method);
        $order_stmt->execute();
        $order_id = $conn->insert_id;

        foreach ($cart_items as $item) {
            $item_stmt = $conn->prepare(
                "INSERT INTO order_items (order_id, product_id, quantity, price_at_time) VALUES (?, ?, ?, ?)"
            );
            $item_stmt->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['discounted_price']);
            $item_stmt->execute();

            // Reduce stock
            $stock_stmt = $conn->prepare(
                "UPDATE products SET quantity_in_stock = quantity_in_stock - ? WHERE product_id = ?"
            );
            $stock_stmt->bind_param("ii", $item['quantity'], $item['product_id']);
            $stock_stmt->execute();
        }

        // Clear cart
        $clear = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        $clear->bind_param("i", $user_id);
        $clear->execute();

        $_SESSION['order_success'] = "Order #" . $order_id . " placed successfully! We will notify you when it ships.";
        header("Location: my_orders.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - TownSquare Market</title>
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
            <a href="cart.php" class="btn btn-outline-light btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Back to Cart
            </a>
        </div>
    </nav>

    <!-- Checkout Progress -->
    <div class="bg-light border-bottom py-3">
        <div class="container">
            <div class="d-flex align-items-center gap-2" style="font-size:0.9rem;">
                <span class="text-muted">Cart</span>
                <i class="fas fa-chevron-right text-muted" style="font-size:0.7rem;"></i>
                <span class="fw-semibold text-primary">Checkout</span>
                <i class="fas fa-chevron-right text-muted" style="font-size:0.7rem;"></i>
                <span class="text-muted">Confirmation</span>
            </div>
        </div>
    </div>

    <div class="container my-5">
        <h2 class="fw-bold mb-4">Checkout</h2>

        <?php if (isset($order_error)): ?>
            <div class="alert alert-danger auto-dismiss">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $order_error; ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Shipping & Payment Form -->
            <div class="col-lg-7">
                <form method="POST">
                    <!-- Shipping Information -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0 fw-semibold">
                                <i class="fas fa-map-marker-alt me-2 text-primary"></i>Shipping Information
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Street Address</label>
                                <input type="text" name="address" class="form-control"
                                       placeholder="123 Main Street" required>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">City</label>
                                    <input type="text" name="city" class="form-control"
                                           placeholder="Cape Town" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Postal Code</label>
                                    <input type="text" name="postal_code" class="form-control"
                                           placeholder="8001" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0 fw-semibold">
                                <i class="fas fa-credit-card me-2 text-primary"></i>Payment Method
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-column gap-2">
                                <label class="d-flex align-items-center gap-3 p-3 border rounded-3 cursor-pointer"
                                       style="cursor:pointer;">
                                    <input type="radio" name="payment_method" value="card" checked>
                                    <i class="fas fa-credit-card text-primary"></i>
                                    <div>
                                        <div class="fw-semibold">Credit / Debit Card</div>
                                        <small class="text-muted">Visa, Mastercard accepted</small>
                                    </div>
                                </label>
                                <label class="d-flex align-items-center gap-3 p-3 border rounded-3"
                                       style="cursor:pointer;">
                                    <input type="radio" name="payment_method" value="eft">
                                    <i class="fas fa-university text-info"></i>
                                    <div>
                                        <div class="fw-semibold">EFT (Bank Transfer)</div>
                                        <small class="text-muted">Direct bank payment</small>
                                    </div>
                                </label>
                                <label class="d-flex align-items-center gap-3 p-3 border rounded-3"
                                       style="cursor:pointer;">
                                    <input type="radio" name="payment_method" value="cod">
                                    <i class="fas fa-money-bill-wave text-success"></i>
                                    <div>
                                        <div class="fw-semibold">Cash on Delivery</div>
                                        <small class="text-muted">Pay when your order arrives</small>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="shipping_method" value="<?php echo htmlspecialchars($shipping_method); ?>">

                    <button type="submit" name="place_order" class="btn btn-success btn-lg w-100">
                        <i class="fas fa-lock me-2"></i>Place Order &mdash; R<?php echo number_format($total, 2); ?>
                    </button>
                </form>
            </div>

            <!-- Order Summary -->
            <div class="col-lg-5">
                <div class="card position-sticky" style="top:80px;">
                    <div class="card-header bg-dark text-white">
                        <h6 class="mb-0 fw-semibold">Order Summary</h6>
                    </div>
                    <div class="card-body p-0">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                                <div style="font-size:0.9rem;">
                                    <?php echo htmlspecialchars($item['product_name']); ?>
                                    <span class="text-muted">x<?php echo $item['quantity']; ?></span>
                                </div>
                                <span class="fw-semibold" style="font-size:0.9rem;">
                                    R<?php echo number_format($item['discounted_price'] * $item['quantity'], 2); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="card-footer bg-transparent">
                        <div class="d-flex justify-content-between mb-1 text-muted" style="font-size:0.9rem;">
                            <span>Subtotal</span>
                            <span>R<?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2 text-muted" style="font-size:0.9rem;">
                            <span>Shipping (<?php echo ucfirst($shipping_method); ?>)</span>
                            <span>R<?php echo number_format($shipping_cost, 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between fw-bold fs-5 pt-2 border-top">
                            <span>Total</span>
                            <span class="text-success">R<?php echo number_format($total, 2); ?></span>
                        </div>
                        <small class="text-muted">15% VAT included</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
</body>
</html>