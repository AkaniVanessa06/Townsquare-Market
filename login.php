<?php
require_once 'config.php';

if (isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$error = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Please enter your email and password.";
    } else {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT user_id, full_name, email, password_hash, user_type FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id']    = $user['user_id'];
                $_SESSION['full_name']  = $user['full_name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_type']  = $user['user_type'];

                // Update last login
                $upd = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                $upd->bind_param("i", $user['user_id']);
                $upd->execute();

                $conn->close();
                header("Location: index.php");
                exit();
            } else {
                $error = "Invalid email or password. Please try again.";
            }
        } else {
            $error = "Invalid email or password. Please try again.";
        }
        $conn->close();
    }
}

// Handle Forgot Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_password'])) {
    $email = trim($_POST['reset_email']);
    $conn  = getDBConnection();
    $stmt  = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $_SESSION['reset_msg']  = "success";
        $_SESSION['reset_text'] = "If this email exists in our system, a password reset link has been sent.";
    } else {
        $_SESSION['reset_msg']  = "success";
        $_SESSION['reset_text'] = "If this email exists in our system, a password reset link has been sent.";
    }
    $conn->close();
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - TownSquare Market</title>
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

    <div class="w-100" style="max-width:460px;">

        <div class="text-center mb-4">
            <a href="index.php" class="text-white text-decoration-none">
                <i class="fas fa-store fa-2x mb-2"></i>
                <h4 class="mb-0 text-white fw-bold">TownSquare Market</h4>
                <small class="text-white opacity-75">South Africa's trusted C2C marketplace</small>
            </a>
        </div>

        <div class="card auth-card">
            <div class="card-body p-4 p-md-5">
                <h4 class="mb-1 fw-bold">Welcome back</h4>
                <p class="text-muted mb-4" style="font-size:0.9rem;">Sign in to your account to continue</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger auto-dismiss d-flex align-items-center gap-2">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['reset_msg'])): ?>
                    <div class="alert alert-success auto-dismiss d-flex align-items-center gap-2">
                        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['reset_text']; unset($_SESSION['reset_msg']); unset($_SESSION['reset_text']); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
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
                        <div class="d-flex justify-content-between">
                            <label class="form-label">Password</label>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal"
                               class="text-muted" style="font-size:0.85rem;">Forgot password?</a>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-lock text-muted"></i>
                            </span>
                            <input type="password" name="password" id="password-field" class="form-control border-start-0"
                                   placeholder="Enter your password" required>
                            <button type="button" class="btn btn-light border"
                                    onclick="togglePassword()" tabindex="-1">
                                <i class="fas fa-eye" id="pw-icon"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" name="login" class="btn btn-primary w-100 py-2 mt-2">
                        Sign In
                    </button>
                </form>

                <hr class="my-4">

                <p class="text-center mb-0" style="font-size:0.9rem;">
                    Don't have an account?
                    <a href="register.php" class="fw-semibold">Create one</a>
                </p>

                <div class="mt-4 p-3 rounded-3" style="background:#f8f9fa; font-size:0.82rem; color:#718096;">
                    <strong>Demo accounts:</strong><br>
                    Buyer: buyer@example.com / password123<br>
                    Seller: seller@example.com / password123<br>
                    Admin: admin@townsquare.co.za / Admin@123
                </div>
            </div>
        </div>

        <div class="text-center mt-3">
            <a href="index.php" class="text-white opacity-75" style="font-size:0.85rem;">
                <i class="fas fa-arrow-left me-1"></i> Back to marketplace
            </a>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div class="modal fade" id="forgotPasswordModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Reset your password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <p class="text-muted mb-3" style="font-size:0.9rem;">
                            Enter your email address and we'll send you a reset link.
                        </p>
                        <input type="email" name="reset_email" class="form-control"
                               placeholder="your@email.com" required>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="forgot_password" class="btn btn-primary">Send Reset Link</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
    <script>
        function togglePassword() {
            var f  = document.getElementById('password-field');
            var ic = document.getElementById('pw-icon');
            if (f.type === 'password') {
                f.type = 'text';
                ic.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                f.type = 'password';
                ic.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>
</html>