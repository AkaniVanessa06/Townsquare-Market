<?php
require_once 'config.php';

if (!isSeller()) {
    header("Location: login.php");
    exit();
}

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name      = trim($_POST['product_name']);
    $description       = trim($_POST['description']);
    $price_excl        = (float)$_POST['price_excluding_vat'];
    $discount_percent  = (float)$_POST['discount_percent'];
    $category          = trim($_POST['category']);
    $quantity          = max(1, (int)$_POST['quantity']);

    if (empty($product_name) || empty($description) || $price_excl <= 0) {
        $error = "Please fill in all required fields with valid values.";
    } else {
        $price_incl     = $price_excl * (1 + VAT_RATE / 100);
        $discounted     = $price_incl * (1 - $discount_percent / 100);

        $conn = getDBConnection();
        $stmt = $conn->prepare(
            "INSERT INTO products (seller_id, product_name, description, price_excluding_vat,
                                   price_including_vat, discount_percent, discounted_price,
                                   quantity_in_stock, category, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')"
        );
        $stmt->bind_param(
            "issddddis",
            $_SESSION['user_id'],
            $product_name, $description,
            $price_excl, $price_incl,
            $discount_percent, $discounted,
            $quantity, $category
        );

        if ($stmt->execute()) {
            $success = "Product listed successfully! It is now live in the marketplace.";
        } else {
            $error = "Failed to list product. Please try again.";
        }
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>List a Product - TownSquare Market</title>
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
        <div class="row justify-content-center">
            <div class="col-lg-8">

                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary text-white"
                         style="width:48px; height:48px; flex-shrink:0;">
                        <i class="fas fa-plus"></i>
                    </div>
                    <div>
                        <h3 class="mb-0 fw-bold">List a New Product</h3>
                        <p class="text-muted mb-0" style="font-size:0.9rem;">Fill in the details below to publish your listing.</p>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success auto-dismiss d-flex align-items-center gap-2">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger auto-dismiss d-flex align-items-center gap-2">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body p-4">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Product Name <span class="text-danger">*</span></label>
                                <input type="text" name="product_name" class="form-control"
                                       placeholder="e.g. Samsung Galaxy S22" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Description <span class="text-danger">*</span></label>
                                <textarea name="description" class="form-control" rows="4"
                                          placeholder="Describe your product — condition, features, reason for selling..." required></textarea>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Price (excluding VAT) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">R</span>
                                        <input type="number" step="0.01" min="0.01" name="price_excluding_vat"
                                               class="form-control" placeholder="0.00"
                                               id="price-excl" oninput="updatePricePreview()" required>
                                    </div>
                                    <small class="text-muted">15% VAT will be added automatically.</small>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Discount %</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" min="0" max="100"
                                               name="discount_percent" class="form-control"
                                               placeholder="0" value="0"
                                               id="discount" oninput="updatePricePreview()">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Quantity in Stock <span class="text-danger">*</span></label>
                                    <input type="number" name="quantity" class="form-control"
                                           value="1" min="1" required>
                                </div>
                            </div>

                            <!-- Price preview -->
                            <div class="p-3 rounded-3 mb-3" style="background:#f0f4ff; border:1px solid #c3d0f7;">
                                <div class="d-flex gap-4 flex-wrap" style="font-size:0.9rem;">
                                    <div>
                                        <span class="text-muted">Price incl. VAT:</span>
                                        <strong id="price-incl-preview">R0.00</strong>
                                    </div>
                                    <div>
                                        <span class="text-muted">After discount:</span>
                                        <strong id="price-disc-preview" class="text-success">R0.00</strong>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select">
                                    <option value="Electronics">Electronics</option>
                                    <option value="Clothing">Clothing</option>
                                    <option value="Furniture">Furniture</option>
                                    <option value="Books">Books</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-upload me-2"></i>Publish Listing
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
    <script>
        function updatePricePreview() {
            var excl     = parseFloat(document.getElementById('price-excl').value)    || 0;
            var discount = parseFloat(document.getElementById('discount').value)      || 0;
            var incl     = excl * 1.15;
            var final    = incl * (1 - discount / 100);
            document.getElementById('price-incl-preview').textContent = 'R' + incl.toFixed(2);
            document.getElementById('price-disc-preview').textContent = 'R' + final.toFixed(2);
        }
    </script>
</body>
</html>