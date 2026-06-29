<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id    = $_SESSION['user_id'];

if ($product_id > 0) {
    $conn = getDBConnection();

    // Verify the product exists and is active
    $prod = $conn->prepare("SELECT product_id, quantity_in_stock FROM products WHERE product_id = ? AND status = 'active'");
    $prod->bind_param("i", $product_id);
    $prod->execute();
    $product = $prod->get_result()->fetch_assoc();

    if ($product) {
        // Check if item already in cart
        $check = $conn->prepare("SELECT cart_id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $check->bind_param("ii", $user_id, $product_id);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $item    = $result->fetch_assoc();
            $new_qty = min($item['quantity'] + 1, $product['quantity_in_stock']);
            $update  = $conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
            $update->bind_param("iii", $new_qty, $user_id, $product_id);
            $update->execute();
        } else {
            $insert = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)");
            $insert->bind_param("ii", $user_id, $product_id);
            $insert->execute();
        }
    }

    $conn->close();
}

header("Location: cart.php");
exit();
?>