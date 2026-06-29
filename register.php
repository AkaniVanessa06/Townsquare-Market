<?php
require_once 'config.php';

if (isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name       = trim($_POST['full_name']);
    $email           = trim($_POST['email']);
    $phone           = trim($_POST['phone']);
    $password        = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $user_type       = $_POST['user_type'];

    // Validation
    if (empty($full_name))                              $errors[] = "Full name is required.";
    if (empty($email))                                  $errors[] = "Email address is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))     $errors[] = "Please enter a valid email address.";
    if (strlen($password) < 6)                          $errors[] = "Password must be at least 6 characters.";
    if ($password !== $confirm_password)                $errors[] = "Passwords do not match.";
    if (!in_array($user_type, ['buyer', 'seller']))     $errors[] = "Please select a valid account type.";

    if (empty($errors)) {
        $conn  = getDBConnection();
        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $errors[] = "This email address is already registered. Please log in.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt   = $conn->prepare("INSERT INTO users (full_name, email, phone, password_hash, user_type, is_verified) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->bind_param("sssss", $full_name, $email, $phone, $hashed, $user_type);

            if ($stmt->execute()) {
                $success = true;
                header("refresh:2;url=login.php");
            } else {
                $errors[] = "Registration failed. Please try again.";
            }
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
    <title>Create Account - TownSquare Market</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 16px;
        }
    </style>
</head>
<body>

    <div class="w-100" style="max-width:500px;">

        <div class="text-center mb-4">
            <a href="index.php" class="text-white text-decoration-none">
                <i class="fas fa-store fa-2x mb-2"></i>
                <h4 class="mb-0 text-white fw-bold">TownSquare Market</h4>
                <small class="text-white opacity-75">South Africa's trusted C2C marketplace</small>
            </a>
        </div>

        <div class="card auth-card">
            <div class="card-body p-4 p-md-5">
                <h4 class="mb-1 fw-bold">Create your account</h4>
                <p class="text-muted mb-4" style="font-size:0.9rem;">Join thousands of buyers and sellers across South Africa</p>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger auto-dismiss">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $err): ?>
                                <li><?php echo htmlspecialchars($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        Registration successful! Redirecting you to sign in...
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="register-form">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-user text-muted"></i>
                            </span>
                            <input type="text" name="full_name" class="form-control border-start-0"
                                   placeholder="Your full name" required
                                   value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-envelope text-muted"></i>
                            </span>
                            <input type="email" name="email" class="form-control border-start-0"
                                   placeholder="you@example.com" required
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-phone text-muted"></i>
                            </span>
                            <input type="tel" name="phone" class="form-control border-start-0"
                                   placeholder="0712345678" required
                                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Account Type</label>
                        <select name="user_type" class="form-select" required>
                            <option value="buyer" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'buyer') ? 'selected' : ''; ?>>
                                Buyer - Browse and purchase products
                            </option>
                            <option value="seller" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'seller') ? 'selected' : ''; ?>>
                                Seller - List and sell products
                            </option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-lock text-muted"></i>
                            </span>
                            <input type="password" name="password" id="password" class="form-control border-start-0"
                                   placeholder="At least 6 characters" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-lock text-muted"></i>
                            </span>
                            <input type="password" name="confirm_password" id="confirm-password"
                                   class="form-control border-start-0"
                                   placeholder="Repeat your password" required>
                        </div>
                        <div id="password-error" class="text-danger mt-1" style="font-size:0.85rem; display:none;"></div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2">
                        Create Account
                    </button>
                </form>

                <hr class="my-4">

                <p class="text-center mb-0" style="font-size:0.9rem;">
                    Already have an account?
                    <a href="login.php" class="fw-semibold">Sign in</a>
                </p>
            </div>
        </div>

        <div class="text-center mt-3">
            <a href="index.php" class="text-white opacity-75" style="font-size:0.85rem;">
                <i class="fas fa-arrow-left me-1"></i> Back to marketplace
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
</body>
</html>