<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$conn    = getDBConnection();
$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject             = trim($_POST['subject']);
    $description         = trim($_POST['description']);
    $reported_user_id    = !empty($_POST['reported_user_id'])    ? (int)$_POST['reported_user_id']    : null;
    $reported_product_id = !empty($_POST['reported_product_id']) ? (int)$_POST['reported_product_id'] : null;

    if (empty($subject) || empty($description)) {
        $error = "Please provide a subject and description.";
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO complaints (reporter_id, reported_user_id, reported_product_id, subject, description)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("iiiss", $_SESSION['user_id'], $reported_user_id, $reported_product_id, $subject, $description);

        if ($stmt->execute()) {
            $success = "Report submitted successfully. Our team will review it shortly.";
        } else {
            $error = "Failed to submit report. Please try again.";
        }
    }
}

// Get this user's reports
$stmt = $conn->prepare(
    "SELECT c.*,
            CASE WHEN c.reported_product_id IS NOT NULL THEN p.product_name ELSE NULL END AS product_name
     FROM complaints c
     LEFT JOIN products p ON c.reported_product_id = p.product_id
     WHERE c.reporter_id = ?
     ORDER BY c.created_at DESC"
);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$complaints = $stmt->get_result();

$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report an Issue - TownSquare Market</title>
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
        <div class="row g-4">

            <!-- Report Form -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0 fw-semibold">
                            <i class="fas fa-flag me-2"></i>Report an Issue
                        </h5>
                    </div>
                    <div class="card-body p-4">
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

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Subject <span class="text-danger">*</span></label>
                                <input type="text" name="subject" class="form-control"
                                       placeholder="Brief description of the issue" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Details <span class="text-danger">*</span></label>
                                <textarea name="description" class="form-control" rows="5"
                                          placeholder="Please provide as much detail as possible about the issue..." required></textarea>
                            </div>

                            <?php if ($product_id): ?>
                                <input type="hidden" name="reported_product_id" value="<?php echo $product_id; ?>">
                                <div class="mb-3 p-2 rounded-2 bg-light border" style="font-size:0.88rem;">
                                    <i class="fas fa-box me-1 text-muted"></i>
                                    Reporting product ID: <strong>#<?php echo $product_id; ?></strong>
                                </div>
                            <?php else: ?>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Product ID <span class="text-muted">(optional)</span></label>
                                        <input type="number" name="reported_product_id" class="form-control"
                                               placeholder="e.g. 42" min="1">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">User ID <span class="text-muted">(optional)</span></label>
                                        <input type="number" name="reported_user_id" class="form-control"
                                               placeholder="e.g. 15" min="1">
                                    </div>
                                </div>
                            <?php endif; ?>

                            <button type="submit" class="btn btn-danger w-100">
                                <i class="fas fa-paper-plane me-2"></i>Submit Report
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- My Reports -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0 fw-semibold">
                            <i class="fas fa-history me-2"></i>My Reports
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($complaints->num_rows > 0): ?>
                            <?php
                            $status_map = [
                                'pending'       => ['warning',   'Pending'],
                                'investigating' => ['info',      'Investigating'],
                                'resolved'      => ['success',   'Resolved'],
                                'dismissed'     => ['secondary', 'Dismissed'],
                            ];
                            $first = true;
                            while ($complaint = $complaints->fetch_assoc()):
                                $s = $status_map[$complaint['status']] ?? ['secondary', ucfirst($complaint['status'])];
                            ?>
                                <div class="px-4 py-3 <?php echo !$first ? 'border-top' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <strong style="font-size:0.9rem;">
                                            <?php echo htmlspecialchars($complaint['subject']); ?>
                                        </strong>
                                        <span class="badge bg-<?php echo $s[0]; ?> flex-shrink-0"><?php echo $s[1]; ?></span>
                                    </div>
                                    <p class="text-muted mb-1 mt-1" style="font-size:0.85rem;">
                                        <?php echo htmlspecialchars(substr($complaint['description'], 0, 120)); ?>
                                        <?php echo strlen($complaint['description']) > 120 ? '…' : ''; ?>
                                    </p>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo date('d M Y', strtotime($complaint['created_at'])); ?>
                                    </small>
                                </div>
                                <?php $first = false; ?>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-clipboard-check fa-3x mb-3"></i>
                                <p>You have not submitted any reports yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
</body>
</html>