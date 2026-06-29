<?php
require_once 'config.php';
$conn = getDBConnection();

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare(
    "SELECT p.*, u.full_name AS seller_name, u.user_id AS seller_id
     FROM products p
     JOIN users u ON p.seller_id = u.user_id
     WHERE p.product_id = ? AND p.status = 'active'"
);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    header("Location: index.php");
    exit();
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review']) && isLoggedIn()) {
    $rating  = max(1, min(5, (int)$_POST['rating']));
    $comment = trim($_POST['comment']);
    $user_id = $_SESSION['user_id'];

    if (!empty($comment)) {
        $rs = $conn->prepare("INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
        $rs->bind_param("iiis", $product_id, $user_id, $rating, $comment);
        $rs->execute();
    }
    header("Location: product.php?id=$product_id#reviews");
    exit();
}

// Get reviews
$rev_stmt = $conn->prepare(
    "SELECT r.*, u.full_name FROM reviews r
     JOIN users u ON r.user_id = u.user_id
     WHERE r.product_id = ? ORDER BY r.created_at DESC"
);
$rev_stmt->bind_param("i", $product_id);
$rev_stmt->execute();
$reviews = $rev_stmt->get_result();

// Average rating
$avg_stmt = $conn->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS total FROM reviews WHERE product_id = ?");
$avg_stmt->bind_param("i", $product_id);
$avg_stmt->execute();
$rating_stats = $avg_stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['product_name']); ?> - TownSquare Market</title>
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
                <?php if (isLoggedIn()): ?>
                    <a href="cart.php" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-shopping-cart me-1"></i>Cart
                    </a>
                <?php endif; ?>
                <a href="javascript:history.back()" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i>Back
                </a>
            </div>
        </div>
    </nav>

    <div class="container my-5">

        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <?php if ($product['category']): ?>
                    <li class="breadcrumb-item">
                        <a href="index.php?category=<?php echo urlencode($product['category']); ?>">
                            <?php echo htmlspecialchars($product['category']); ?>
                        </a>
                    </li>
                <?php endif; ?>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($product['product_name']); ?></li>
            </ol>
        </nav>

        <div class="row g-4">
            <!-- Product Image -->
            <div class="col-md-5">
                <div class="card overflow-hidden" style="border-radius:var(--radius);">
                    <img src="<?php echo !empty($product['product_image']) ? htmlspecialchars($product['product_image']) : 'https://images.pexels.com/photos/5632371/pexels-photo-5632371.jpeg?w=600'; ?>"
                         class="w-100" style="max-height:400px; object-fit:cover;"
                         alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                         onerror="this.src='https://images.pexels.com/photos/5632371/pexels-photo-5632371.jpeg?w=600'">
                </div>
            </div>

            <!-- Product Details -->
            <div class="col-md-7">
                <h1 class="h2 fw-bold mb-1"><?php echo htmlspecialchars($product['product_name']); ?></h1>
                <p class="text-muted mb-3">
                    <i class="fas fa-store me-1"></i>Sold by
                    <strong><?php echo htmlspecialchars($product['seller_name']); ?></strong>
                    &nbsp;&middot;&nbsp;
                    <span class="badge bg-light text-dark"><?php echo htmlspecialchars($product['category'] ?? 'General'); ?></span>
                </p>

                <!-- Rating -->
                <?php if ($rating_stats['total'] > 0): ?>
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="rating-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star<?php echo $i <= round($rating_stats['avg_rating']) ? '' : '-o'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="text-muted" style="font-size:0.9rem;">
                            <?php echo number_format($rating_stats['avg_rating'], 1); ?> / 5
                            (<?php echo $rating_stats['total']; ?> review<?php echo $rating_stats['total'] != 1 ? 's' : ''; ?>)
                        </span>
                    </div>
                <?php endif; ?>

                <!-- Price -->
                <div class="mb-4 p-3 rounded-3" style="background:#f8f9fa; border:1px solid var(--border);">
                    <?php if ($product['discount_percent'] > 0): ?>
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <span class="text-muted text-decoration-line-through fs-5">
                                R<?php echo number_format($product['price_including_vat'], 2); ?>
                            </span>
                            <span class="text-danger fw-bold" style="font-size:2rem; line-height:1;">
                                R<?php echo number_format($product['discounted_price'], 2); ?>
                            </span>
                            <span class="badge bg-danger fs-6">
                                Save <?php echo (int)$product['discount_percent']; ?>%
                            </span>
                        </div>
                    <?php else: ?>
                        <span class="text-primary fw-bold" style="font-size:2rem; line-height:1;">
                            R<?php echo number_format($product['price_including_vat'], 2); ?>
                        </span>
                    <?php endif; ?>
                    <div class="text-muted mt-1" style="font-size:0.85rem;">
                        <i class="fas fa-info-circle me-1"></i>Price includes 15% VAT
                    </div>
                </div>

                <!-- Description -->
                <div class="mb-4">
                    <h6 class="fw-semibold mb-2">Description</h6>
                    <p class="text-muted" style="line-height:1.7;">
                        <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                    </p>
                </div>

                <!-- Stock & Actions -->
                <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                    <?php if ($product['quantity_in_stock'] > 0): ?>
                        <span class="badge bg-success">
                            <i class="fas fa-check-circle me-1"></i><?php echo $product['quantity_in_stock']; ?> in stock
                        </span>
                    <?php else: ?>
                        <span class="badge bg-danger">Out of stock</span>
                    <?php endif; ?>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <?php if ($product['quantity_in_stock'] > 0): ?>
                        <a href="add_to_cart.php?id=<?php echo $product['product_id']; ?>"
                           class="btn btn-success btn-lg">
                            <i class="fas fa-cart-plus me-2"></i>Add to Cart
                        </a>
                    <?php else: ?>
                        <button class="btn btn-secondary btn-lg" disabled>Out of Stock</button>
                    <?php endif; ?>
                    <a href="complaints.php?product_id=<?php echo $product['product_id']; ?>"
                       class="btn btn-outline-danger btn-lg">
                        <i class="fas fa-flag me-2"></i>Report Issue
                    </a>
                </div>
            </div>
        </div>

        <!-- Reviews Section -->
        <div class="row mt-5" id="reviews">
            <div class="col-12">
                <h4 class="fw-bold mb-4">
                    <i class="fas fa-star me-2 text-warning"></i>Customer Reviews
                </h4>

                <!-- Leave a review -->
                <?php if (isLoggedIn()): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0 fw-semibold">Write a Review</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Your Rating</label>
                                        <select name="rating" class="form-select" required>
                                            <option value="5">5 - Excellent</option>
                                            <option value="4">4 - Very Good</option>
                                            <option value="3">3 - Good</option>
                                            <option value="2">2 - Fair</option>
                                            <option value="1">1 - Poor</option>
                                        </select>
                                    </div>
                                    <div class="col-md-9">
                                        <label class="form-label">Your Review</label>
                                        <textarea name="comment" class="form-control" rows="3"
                                                  placeholder="Share your experience with this product..." required></textarea>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" name="submit_review" class="btn btn-primary">
                                            Submit Review
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-light mb-4">
                        <a href="login.php">Sign in</a> to leave a review.
                    </div>
                <?php endif; ?>

                <!-- Existing reviews -->
                <?php if ($reviews->num_rows > 0): ?>
                    <?php while ($review = $reviews->fetch_assoc()): ?>
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                    <div>
                                        <div class="rating-stars mb-1">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : '-o'; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <p class="mb-1"><?php echo htmlspecialchars($review['comment']); ?></p>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($review['full_name']); ?>
                                            &middot;
                                            <?php echo date('d M Y', strtotime($review['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-comment-slash fa-2x mb-2"></i>
                        <p>No reviews yet. Be the first to review this product!</p>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
</body>
</html>