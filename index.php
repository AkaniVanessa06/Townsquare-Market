<?php
require_once 'config.php';
$conn = getDBConnection();

// Handle search and category filter
$search   = isset($_GET['search'])   ? trim($_GET['search'])   : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';

$query  = "SELECT p.*, u.full_name AS seller_name FROM products p
           JOIN users u ON p.seller_id = u.user_id
           WHERE p.status = 'active'";
$params = [];
$types  = "";

if ($search) {
    $query   .= " AND (p.product_name LIKE ? OR p.description LIKE ?)";
    $sp       = "%$search%";
    $params[] = $sp;
    $params[] = $sp;
    $types   .= "ss";
}

if ($category) {
    $query   .= " AND p.category = ?";
    $params[] = $category;
    $types   .= "s";
}

$query .= " ORDER BY p.created_at DESC LIMIT 24";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result();

// Get distinct categories
$cat_result = $conn->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' AND status = 'active' ORDER BY category ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TownSquare Market - South Africa's Trusted C2C Marketplace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-store me-2"></i>TownSquare Market
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <form class="d-flex mx-auto my-2 my-lg-0" style="width:min(100%,480px);" method="GET" action="index.php">
                    <div class="input-group">
                        <input class="form-control" type="search" name="search"
                               placeholder="Search products..."
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
                <ul class="navbar-nav ms-auto align-items-lg-center gap-1">
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="cart.php">
                                <i class="fas fa-shopping-cart me-1"></i>Cart
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($_SESSION['full_name']); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="profile.php">
                                        <i class="fas fa-user-circle me-2 text-secondary"></i>My Profile
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <?php if (isSeller()): ?>
                                    <li>
                                        <a class="dropdown-item" href="seller_dashboard.php">
                                            <i class="fas fa-chart-line me-2 text-success"></i>Seller Dashboard
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="sell.php">
                                            <i class="fas fa-plus-circle me-2 text-success"></i>List a Product
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="my_products.php">
                                            <i class="fas fa-box me-2 text-primary"></i>My Products
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                <?php endif; ?>
                                <li>
                                    <a class="dropdown-item" href="my_orders.php">
                                        <i class="fas fa-truck me-2 text-info"></i>My Orders
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="complaints.php">
                                        <i class="fas fa-flag me-2 text-warning"></i>Report an Issue
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="logout.php">
                                        <i class="fas fa-sign-out-alt me-2"></i>Sign Out
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <?php if (isAdmin()): ?>
                            <li class="nav-item">
                                <a class="nav-link btn btn-sm btn-danger px-3 text-white ms-1" href="admin/index.php">
                                    <i class="fas fa-shield-alt me-1"></i>Admin
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">
                                <i class="fas fa-sign-in-alt me-1"></i>Sign In
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-primary btn-sm ms-1" href="register.php">Join Now</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <?php if (!$search && !$category): ?>
    <div class="hero-section text-white py-5">
        <div class="container text-center position-relative" style="z-index:1;">
            <h1 class="display-5 fw-bold mb-3">South Africa's Trusted C2C Marketplace</h1>
            <p class="lead mb-4 opacity-90">Buy and sell with confidence. VAT-inclusive pricing. Secure transactions. Local support.</p>
            <div class="d-flex gap-3 justify-content-center flex-wrap">
                <?php if (!isLoggedIn()): ?>
                    <a href="register.php?type=seller" class="btn btn-light btn-lg fw-semibold">
                        Start Selling
                    </a>
                    <a href="register.php" class="btn btn-outline-light btn-lg">
                        Browse as Buyer
                    </a>
                <?php else: ?>
                    <a href="#products" class="btn btn-light btn-lg fw-semibold">
                        Browse Products
                    </a>
                    <?php if (isSeller()): ?>
                        <a href="sell.php" class="btn btn-outline-light btn-lg">
                            List a Product
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="container my-5" id="products">

        <?php if ($search || $category): ?>
            <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
                <h5 class="mb-0">
                    <?php if ($search): ?>
                        Search results for <strong>"<?php echo htmlspecialchars($search); ?>"</strong>
                    <?php endif; ?>
                    <?php if ($category): ?>
                        Category: <strong><?php echo htmlspecialchars($category); ?></strong>
                    <?php endif; ?>
                </h5>
                <a href="index.php" class="btn btn-sm btn-outline-secondary ms-auto">Clear filters</a>
            </div>
        <?php endif; ?>

        <div class="row g-4">

            <!-- Category Sidebar -->
            <div class="col-md-3 col-lg-2">
                <div class="card">
                    <div class="card-header bg-dark text-white py-2">
                        <small class="fw-semibold text-uppercase letter-spacing-1">
                            <i class="fas fa-layer-group me-2"></i>Categories
                        </small>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="index.php"
                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 <?php echo !$category ? 'active' : ''; ?>">
                            All Products
                        </a>
                        <?php if ($cat_result && $cat_result->num_rows > 0): ?>
                            <?php while ($cat = $cat_result->fetch_assoc()): ?>
                                <a href="?category=<?php echo urlencode($cat['category']); ?>&search=<?php echo urlencode($search); ?>"
                                   class="list-group-item list-group-item-action py-2 <?php echo $category === $cat['category'] ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($cat['category']); ?>
                                </a>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="list-group-item text-muted" style="font-size:0.85rem;">No categories yet</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Products Grid -->
            <div class="col-md-9 col-lg-10">
                <div class="row g-4">
                    <?php if ($products->num_rows > 0): ?>
                        <?php while ($product = $products->fetch_assoc()): ?>
                            <div class="col-sm-6 col-lg-4 col-xl-3">
                                <div class="card product-card h-100">
                                    <div class="product-image-wrapper">
                                        <img src="<?php echo !empty($product['product_image']) ? htmlspecialchars($product['product_image']) : 'https://images.pexels.com/photos/5632371/pexels-photo-5632371.jpeg?w=400'; ?>"
                                             class="card-img-top product-img"
                                             alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                             onerror="this.src='https://images.pexels.com/photos/5632371/pexels-photo-5632371.jpeg?w=400'">
                                        <?php if ($product['discount_percent'] > 0): ?>
                                            <span class="discount-badge">
                                                -<?php echo (int)$product['discount_percent']; ?>%
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body d-flex flex-column">
                                        <h6 class="card-title mb-1 fw-semibold">
                                            <?php echo htmlspecialchars($product['product_name']); ?>
                                        </h6>
                                        <p class="text-muted mb-2" style="font-size:0.8rem;">
                                            by <?php echo htmlspecialchars($product['seller_name']); ?>
                                        </p>
                                        <div class="mt-auto">
                                            <?php if ($product['discount_percent'] > 0): ?>
                                                <div>
                                                    <span class="text-muted text-decoration-line-through" style="font-size:0.85rem;">
                                                        R<?php echo number_format($product['price_including_vat'], 2); ?>
                                                    </span>
                                                    <span class="text-danger fw-bold fs-5 ms-1">
                                                        R<?php echo number_format($product['discounted_price'], 2); ?>
                                                    </span>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-primary fw-bold fs-5">
                                                    R<?php echo number_format($product['price_including_vat'], 2); ?>
                                                </span>
                                            <?php endif; ?>
                                            <div class="text-muted" style="font-size:0.75rem;">incl. VAT</div>
                                            <div class="d-flex gap-2 mt-2">
                                                <a href="product.php?id=<?php echo $product['product_id']; ?>"
                                                   class="btn btn-sm btn-outline-primary flex-fill">View</a>
                                                <a href="add_to_cart.php?id=<?php echo $product['product_id']; ?>"
                                                   class="btn btn-sm btn-success flex-fill">
                                                    <i class="fas fa-cart-plus"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="text-center py-5">
                                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No products found</h5>
                                <?php if ($search || $category): ?>
                                    <p class="text-muted">Try a different search or category.</p>
                                    <a href="index.php" class="btn btn-primary">View all products</a>
                                <?php else: ?>
                                    <p class="text-muted">Be the first to list a product!</p>
                                    <?php if (isSeller()): ?>
                                        <a href="sell.php" class="btn btn-primary">List a Product</a>
                                    <?php else: ?>
                                        <a href="register.php" class="btn btn-primary">Start Selling</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p class="mb-1 fw-semibold">TownSquare Market</p>
            <p class="mb-2" style="font-size:0.85rem;">South Africa's trusted peer-to-peer marketplace. All prices include 15% VAT.</p>
            <p style="font-size:0.8rem; opacity:0.6;">&copy; <?php echo date('Y'); ?> TownSquare Market. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
</body>
</html>