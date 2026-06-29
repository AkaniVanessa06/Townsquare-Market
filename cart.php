<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$conn    = getDBConnection();
$user_id = $_SESSION['user_id'];

// Update quantity
if (isset($_POST['update_cart'])) {
    $product_id = (int)$_POST['product_id'];
    $quantity   = max(1, (int)$_POST['quantity']);
    $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("iii", $quantity, $user_id, $product_id);
    $stmt->execute();
}

// Remove item
if (isset($_GET['remove'])) {
    $product_id = (int)$_GET['remove'];
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    header("Location: cart.php");
    exit();
}

// Get cart items
$stmt = $conn->prepare(
    "SELECT c.*, p.product_name, p.discounted_price, p.price_including_vat,
            p.discount_percent, p.quantity_in_stock, p.product_image
     FROM cart c JOIN products p ON c.product_id = p.product_id
     WHERE c.user_id = ?"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_result = $stmt->get_result();

$subtotal = 0;
$items    = [];
while ($item = $cart_result->fetch_assoc()) {
    $subtotal += $item['discounted_price'] * $item['quantity'];
    $items[]   = $item;
}

$shipping_method = $_POST['shipping_method'] ?? 'standard';
$shipping_cost   = ($shipping_method === 'express') ? EXPRESS_SHIPPING : STANDARD_SHIPPING;
$total           = $subtotal + $shipping_cost;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - TownSquare Market</title>
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
                <i class="fas fa-arrow-left me-1"></i>Continue Shopping
            </a>
        </div>
    </nav>

    <div class="container my-5">
        <h2 class="fw-bold mb-4">
            <i class="fas fa-shopping-cart me-2"></i>Your Cart
            <?php if (count($items) > 0): ?>
                <span class="badge bg-primary rounded-pill ms-2" style="font-size:0.7rem;">
                    <?php echo count($items); ?>
                </span>
            <?php endif; ?>
        </h2>

        <?php if (count($items) > 0): ?>
            <div class="row g-4">

                <!-- Cart Items -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-body p-0">
                            <?php foreach ($items as $i => $item): ?>
                                <div class="d-flex gap-3 p-3 <?php echo $i < count($items) - 1 ? 'border-bottom' : ''; ?> align-items-center flex-wrap">
                                    <img src="<?php echo !empty($item['product_image']) ? htmlspecialchars($item['product_image']) : 'https://images.pexels.com/photos/5632371/pexels-photo-5632371.jpeg?w=80'; ?>"
                                         style="width:70px; height:70px; object-fit:cover; border-radius:8px; flex-shrink:0;"
                                         alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                         onerror="this.src='https://images.pexels.com/photos/5632371/pexels-photo-5632371.jpeg?w=80'">

                                    <div class="flex-grow-1">
                                        <h6 class="mb-1 fw-semibold">
                                            <?php echo htmlspecialchars($item['product_name']); ?>
                                        </h6>
                                        <div class="text-muted" style="font-size:0.85rem;">
                                            R<?php echo number_format($item['discounted_price'], 2); ?> each (incl. VAT)
                                        </div>
                                    </div>

                                    <form method="POST" class="d-flex align-items-center gap-2">
                                        <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                        <input type="number" name="quantity"
                                               value="<?php echo $item['quantity']; ?>"
                                               min="1" max="<?php echo $item['quantity_in_stock']; ?>"
                                               class="form-control form-control-sm quantity-input"
                                               style="width:70px;">
                                        <button type="submit" name="update_cart" class="btn btn-sm btn-outline-secondary">
                                            Update
                                        </button>
                                    </form>

                                    <div class="text-end" style="min-width:80px;">
                                        <div class="fw-bold">
                                            R<?php echo number_format($item['discounted_price'] * $item['quantity'], 2); ?>
                                        </div>
                                        <a href="?remove=<?php echo $item['product_id']; ?>"
                                           class="btn btn-sm btn-link text-danger p-0 delete-confirm">
                                            <i class="fas fa-trash-alt"></i> Remove
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header bg-dark text-white">
                            <h6 class="mb-0 fw-semibold">Order Summary</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="checkout.php">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Shipping Method</label>
                                    <select name="shipping_method" class="form-select"
                                            onchange="this.form.submit()">
                                        <option value="standard" <?php echo $shipping_method === 'standard' ? 'selected' : ''; ?>>
                                            Standard Courier - R<?php echo STANDARD_SHIPPING; ?> (5-7 days)
                                        </option>
                                        <option value="express" <?php echo $shipping_method === 'express' ? 'selected' : ''; ?>>
                                            Express Courier - R<?php echo EXPRESS_SHIPPING; ?> (2-3 days)
                                        </option>
                                    </select>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Subtotal</span>
                                    <span>R<?php echo number_format($subtotal, 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-muted">Shipping</span>
                                    <span>R<?php echo number_format($shipping_cost, 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between fw-bold fs-5">
                                    <span>Total (incl. VAT)</span>
                                    <span class="text-primary">R<?php echo number_format($total, 2); ?></span>
                                </div>
                                <input type="hidden" name="shipping_method" value="<?php echo $shipping_method; ?>">
                                <button type="submit" name="checkout" class="btn btn-success w-100 mt-3 py-2">
                                    Proceed to Checkout
                                    <i class="fas fa-arrow-right ms-2"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-shopping-cart fa-4x text-muted mb-4"></i>
                <h4 class="text-muted mb-3">Your cart is empty</h4>
                <p class="text-muted mb-4">Browse our marketplace and add products to your cart.</p>
                <a href="index.php" class="btn btn-primary btn-lg">Browse Products</a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
</body>
</html>\