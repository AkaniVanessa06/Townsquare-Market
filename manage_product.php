<?php
require_once 'config.php';

if (!isAdmin()) {
    header("Location: login.php");
    exit();
}

$conn    = getDBConnection();
$message = '';
$msg_type = 'success';

// Add product
if (isset($_POST['add_product'])) {
    $product_name      = trim($_POST['product_name']);
    $description       = trim($_POST['description']);
    $price_excl        = (float)$_POST['price_excluding_vat'];
    $discount_percent  = (float)$_POST['discount_percent'];
    $category          = trim($_POST['category']);
    $quantity          = max(1, (int)$_POST['quantity']);
    $product_image     = trim($_POST['product_image']);
    $seller_id         = (int)$_POST['seller_id'];

    if (empty($product_name) || $price_excl <= 0 || $seller_id <= 0) {
        $message  = "Please fill in all required fields.";
        $msg_type = "danger";
    } else {
        $price_incl = $price_excl * 1.15;
        $discounted = $price_incl * (1 - $discount_percent / 100);

        $stmt = $conn->prepare(
            "INSERT INTO products (seller_id, product_name, description, price_excluding_vat,
                                   price_including_vat, discount_percent, discounted_price,
                                   quantity_in_stock, category, product_image)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("issddddiss", $seller_id, $product_name, $description,
            $price_excl, $price_incl, $discount_percent, $discounted, $quantity, $category, $product_image);

        if ($stmt->execute()) {
            $message = "Product added successfully.";
        } else {
            $message  = "Error adding product: " . $conn->error;
            $msg_type = "danger";
        }
    }
}

// Delete product
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->prepare("DELETE FROM products WHERE product_id = ?")->bind_param("i", $id);
    $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $_SESSION['mp_msg']  = "Product deleted.";
    $_SESSION['mp_type'] = "success";
    header("Location: manage_products.php#list");
    exit();
}

$sellers  = $conn->query("SELECT user_id, full_name FROM users WHERE user_type IN ('seller', 'admin') ORDER BY full_name");
$products = $conn->query(
    "SELECT p.*, u.full_name AS seller_name FROM products p
     JOIN users u ON p.seller_id = u.user_id
     ORDER BY p.created_at DESC"
);

// Sample images for the gallery
$sample_images = [
    ['url' => 'https://images.pexels.com/photos/404280/pexels-photo-404280.jpeg?w=400',    'label' => 'Smartphone'],
    ['url' => 'https://images.pexels.com/photos/1598507/pexels-photo-1598507.jpeg?w=400',  'label' => 'Denim Jeans'],
    ['url' => 'https://images.pexels.com/photos/3394651/pexels-photo-3394651.jpeg?w=400',  'label' => 'Headphones'],
    ['url' => 'https://images.pexels.com/photos/276583/pexels-photo-276583.jpeg?w=400',    'label' => 'Coffee Table'],
    ['url' => 'https://images.pexels.com/photos/290595/pexels-photo-290595.jpeg?w=400',    'label' => 'Book'],
    ['url' => 'https://images.pexels.com/photos/3965548/pexels-photo-3965548.jpeg?w=400',  'label' => 'T-Shirt'],
    ['url' => 'https://images.pexels.com/photos/1029896/pexels-photo-1029896.jpeg?w=400',  'label' => 'Laptop'],
    ['url' => 'https://images.pexels.com/photos/341523/pexels-photo-341523.jpeg?w=400',    'label' => 'Shoes'],
    ['url' => 'https://images.pexels.com/photos/1866149/pexels-photo-1866149.jpeg?w=400',  'label' => 'Watch'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - TownSquare Market</title>
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
            <div class="d-flex gap-2">
                <a href="admin/index.php" class="btn btn-outline-light btn-sm">Full Admin</a>
                <a href="index.php" class="btn btn-light btn-sm">View Site</a>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <h3 class="fw-bold mb-4">
            <i class="fas fa-boxes me-2"></i>Product Management
        </h3>

        <?php if (isset($_SESSION['mp_msg'])): ?>
            <div class="alert alert-<?php echo $_SESSION['mp_type']; ?> auto-dismiss">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $_SESSION['mp_msg']; unset($_SESSION['mp_msg']); unset($_SESSION['mp_type']); ?>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $msg_type; ?> auto-dismiss">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <ul class="nav nav-tabs mb-4" id="productTabs">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#add">
                    <i class="fas fa-plus me-1"></i>Add Product
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#list" id="list-tab">
                    <i class="fas fa-list me-1"></i>All Products
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#images">
                    <i class="fas fa-images me-1"></i>Image Gallery
                </a>
            </li>
        </ul>

        <div class="tab-content">

            <!-- Add Product -->
            <div class="tab-pane fade show active" id="add">
                <div class="card">
                    <div class="card-header bg-primary text-white fw-semibold">Add New Product</div>
                    <div class="card-body p-4">
                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Product Name <span class="text-danger">*</span></label>
                                    <input type="text" name="product_name" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Category</label>
                                    <select name="category" class="form-select">
                                        <option value="Electronics">Electronics</option>
                                        <option value="Clothing">Clothing</option>
                                        <option value="Furniture">Furniture</option>
                                        <option value="Books">Books</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Description <span class="text-danger">*</span></label>
                                    <textarea name="description" class="form-control" rows="3" required></textarea>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Price (excl. VAT) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">R</span>
                                        <input type="number" step="0.01" min="0.01" name="price_excluding_vat"
                                               class="form-control" id="mp-price" oninput="mpPreview()" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Discount %</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" min="0" max="100"
                                               name="discount_percent" class="form-control"
                                               id="mp-disc" value="0" oninput="mpPreview()">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Quantity <span class="text-danger">*</span></label>
                                    <input type="number" name="quantity" class="form-control" value="1" min="1" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Seller <span class="text-danger">*</span></label>
                                    <select name="seller_id" class="form-select" required>
                                        <?php while ($seller = $sellers->fetch_assoc()): ?>
                                            <option value="<?php echo $seller['user_id']; ?>">
                                                <?php echo htmlspecialchars($seller['full_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Image URL</label>
                                    <input type="text" name="product_image" id="product-image-url" class="form-control"
                                           placeholder="https://images.pexels.com/photos/...">
                                    <small class="text-muted">Use the Image Gallery tab to pick a free image URL.</small>
                                </div>
                                <!-- Price preview -->
                                <div class="col-12">
                                    <div class="p-3 rounded-3" style="background:#f0f4ff; border:1px solid #c3d0f7; font-size:0.9rem;">
                                        Price incl. VAT: <strong id="mp-incl">R0.00</strong>
                                        &nbsp;&nbsp;|&nbsp;&nbsp;
                                        After discount: <strong id="mp-final" class="text-success">R0.00</strong>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <button type="submit" name="add_product" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Add Product
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- All Products -->
            <div class="tab-pane fade" id="list">
                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Product</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Seller</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($product = $products->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <img src="<?php echo !empty($product['product_image']) ? htmlspecialchars($product['product_image']) : 'https://images.pexels.com/photos/5632371/pexels-photo-5632371.jpeg?w=50'; ?>"
                                                 style="width:48px; height:48px; object-fit:cover; border-radius:8px;"
                                                 onerror="this.src='https://images.pexels.com/photos/5632371/pexels-photo-5632371.jpeg?w=50'">
                                        </td>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($product['product_name']); ?></td>
                                        <td>R<?php echo number_format($product['discounted_price'], 2); ?></td>
                                        <td><?php echo $product['quantity_in_stock']; ?></td>
                                        <td><?php echo htmlspecialchars($product['seller_name']); ?></td>
                                        <td>
                                            <a href="?delete=<?php echo $product['product_id']; ?>"
                                               class="btn btn-sm btn-outline-danger delete-confirm">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Image Gallery -->
            <div class="tab-pane fade" id="images">
                <div class="card">
                    <div class="card-header fw-semibold">
                        <i class="fas fa-images me-2 text-primary"></i>Free Image Resources
                        <small class="text-muted ms-2">Click "Use" to fill the image URL in the Add Product form.</small>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach ($sample_images as $img): ?>
                                <div class="col-sm-6 col-md-4 col-lg-3">
                                    <div class="card h-100">
                                        <img src="<?php echo $img['url']; ?>"
                                             class="card-img-top"
                                             style="height:140px; object-fit:cover;"
                                             alt="<?php echo $img['label']; ?>"
                                             loading="lazy">
                                        <div class="card-body p-2">
                                            <div class="text-muted mb-2" style="font-size:0.75rem;">
                                                <?php echo $img['label']; ?>
                                            </div>
                                            <div class="d-flex gap-1">
                                                <button class="btn btn-sm btn-outline-secondary flex-fill copy-btn"
                                                        data-url="<?php echo $img['url']; ?>">
                                                    Copy
                                                </button>
                                                <button class="btn btn-sm btn-primary flex-fill use-btn"
                                                        data-url="<?php echo $img['url']; ?>">
                                                    Use
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
    <script>
        // Price preview
        function mpPreview() {
            var excl     = parseFloat(document.getElementById('mp-price').value) || 0;
            var disc     = parseFloat(document.getElementById('mp-disc').value)  || 0;
            var incl     = excl * 1.15;
            var final    = incl * (1 - disc / 100);
            document.getElementById('mp-incl').textContent  = 'R' + incl.toFixed(2);
            document.getElementById('mp-final').textContent = 'R' + final.toFixed(2);
        }

        // Copy URL to clipboard
        document.querySelectorAll('.copy-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var url  = this.dataset.url;
                navigator.clipboard.writeText(url).then(function() {});
                var orig = btn.textContent;
                btn.textContent = 'Copied!';
                setTimeout(function() { btn.textContent = orig; }, 2000);
            });
        });

        // Use URL - fill the add product form and switch tabs
        document.querySelectorAll('.use-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var url = this.dataset.url;
                document.getElementById('product-image-url').value = url;
                // Switch to Add tab
                var addTab = document.querySelector('[href="#add"]');
                bootstrap.Tab.getOrCreateInstance(addTab).show();
                // Scroll to image field
                setTimeout(function() {
                    document.getElementById('product-image-url').scrollIntoView({ behavior: 'smooth', block: 'center' });
                    document.getElementById('product-image-url').focus();
                }, 300);
            });
        });

        // If hash is #list, open the list tab
        if (window.location.hash === '#list') {
            var listTab = document.getElementById('list-tab');
            if (listTab) bootstrap.Tab.getOrCreateInstance(listTab).show();
        }
    </script>
</body>
</html>