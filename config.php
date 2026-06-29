<?php
// config.php - Database configuration
session_start();

// Database configuration for XAMPP
define('DB_HOST', 'sql204.infinityfree.com');
define('DB_USER', 'if0_42159884');
define('DB_PASS', 'Phyllisakani');
define('DB_NAME', 'if0_42159884_townsquare_market');

// VAT rate for South Africa
define('VAT_RATE', 15);

// Shipping costs (ZAR)
define('STANDARD_SHIPPING', 60);
define('EXPRESS_SHIPPING', 120);

// Disable error display in production; log errors instead
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function getDBConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (Exception $e) {
        die("Database connection error: " . $e->getMessage());
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}

function isSeller() {
    return isset($_SESSION['user_type']) && ($_SESSION['user_type'] === 'seller' || $_SESSION['user_type'] === 'admin');
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}
?>