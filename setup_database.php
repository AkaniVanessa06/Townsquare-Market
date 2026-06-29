<?php


echo "<pre style='font-family:monospace; background:#1a202c; color:#68d391; padding:20px; border-radius:8px;'>";
echo "TownSquare Market — Database Setup\n";
echo str_repeat("=", 50) . "\n\n";

// Connect without selecting a database first
$conn = new mysqli('localhost', 'root', '');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database
$conn->query("CREATE DATABASE IF NOT EXISTS townsquare_market CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "Database 'townsquare_market' ready\n";
$conn->select_db('townsquare_market');


// TABLES


// Users
$conn->query("CREATE TABLE IF NOT EXISTS users (
    user_id       INT PRIMARY KEY AUTO_INCREMENT,
    full_name     VARCHAR(100) NOT NULL,
    email         VARCHAR(100) UNIQUE NOT NULL,
    phone         VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    reset_token   VARCHAR(255),
    reset_expiry  DATETIME,
    user_type     ENUM('buyer','seller','admin') DEFAULT 'buyer',
    is_verified   TINYINT(1) DEFAULT 0,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login    DATETIME
) ENGINE=InnoDB");
echo "Table: users\n";

// Products
$conn->query("CREATE TABLE IF NOT EXISTS products (
    product_id          INT PRIMARY KEY AUTO_INCREMENT,
    seller_id           INT NOT NULL,
    product_name        VARCHAR(200) NOT NULL,
    description         TEXT,
    price_excluding_vat DECIMAL(10,2) NOT NULL,
    vat_rate            DECIMAL(5,2) DEFAULT 15.00,
    price_including_vat DECIMAL(10,2),
    discount_percent    DECIMAL(5,2) DEFAULT 0,
    discounted_price    DECIMAL(10,2),
    quantity_in_stock   INT DEFAULT 1,
    category            VARCHAR(50),
    product_image       VARCHAR(500),
    status              ENUM('active','sold','inactive') DEFAULT 'active',
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB");
echo "Table: products\n";

// Cart
$conn->query("CREATE TABLE IF NOT EXISTS cart (
    cart_id    INT PRIMARY KEY AUTO_INCREMENT,
    user_id    INT NOT NULL,
    product_id INT NOT NULL,
    quantity   INT DEFAULT 1,
    added_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)    REFERENCES users(user_id)    ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    UNIQUE KEY unique_cart_item (user_id, product_id)
) ENGINE=InnoDB");
echo "Table: cart\n";

// Orders
$conn->query("CREATE TABLE IF NOT EXISTS orders (
    order_id             INT PRIMARY KEY AUTO_INCREMENT,
    buyer_id             INT NOT NULL,
    order_date           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_amount         DECIMAL(10,2) NOT NULL,
    shipping_address     TEXT NOT NULL,
    shipping_city        VARCHAR(100),
    shipping_postal_code VARCHAR(20),
    shipping_method      VARCHAR(50),
    shipping_cost        DECIMAL(10,2) DEFAULT 0,
    payment_method       VARCHAR(50),
    payment_status       ENUM('pending','completed','failed') DEFAULT 'pending',
    order_status         ENUM('processing','shipped','delivered','cancelled') DEFAULT 'processing',
    tracking_number      VARCHAR(100),
    FOREIGN KEY (buyer_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB");
echo "Table: orders\n";

// Order Items
$conn->query("CREATE TABLE IF NOT EXISTS order_items (
    order_item_id INT PRIMARY KEY AUTO_INCREMENT,
    order_id      INT NOT NULL,
    product_id    INT NOT NULL,
    quantity      INT NOT NULL,
    price_at_time DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id)   REFERENCES orders(order_id)   ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id)
) ENGINE=InnoDB");
echo "Table: order_items\n";

// Reviews
$conn->query("CREATE TABLE IF NOT EXISTS reviews (
    review_id  INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    user_id    INT NOT NULL,
    rating     INT CHECK (rating >= 1 AND rating <= 5),
    comment    TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES users(user_id)       ON DELETE CASCADE
) ENGINE=InnoDB");
echo "Table: reviews\n";

// Complaints
$conn->query("CREATE TABLE IF NOT EXISTS complaints (
    complaint_id        INT PRIMARY KEY AUTO_INCREMENT,
    reporter_id         INT NOT NULL,
    reported_user_id    INT,
    reported_product_id INT,
    subject             VARCHAR(200) NOT NULL,
    description         TEXT NOT NULL,
    status              ENUM('pending','investigating','resolved','dismissed') DEFAULT 'pending',
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at         DATETIME,
    FOREIGN KEY (reporter_id)         REFERENCES users(user_id),
    FOREIGN KEY (reported_user_id)    REFERENCES users(user_id),
    FOREIGN KEY (reported_product_id) REFERENCES products(product_id)
) ENGINE=InnoDB");
echo "Table: complaints\n\n";


// TRIGGERS — auto-calculate VAT prices on INSERT

$conn->query("DROP TRIGGER IF EXISTS trg_product_prices_insert");
$conn->query("CREATE TRIGGER trg_product_prices_insert
    BEFORE INSERT ON products FOR EACH ROW
    BEGIN
        SET NEW.price_including_vat = NEW.price_excluding_vat * (1 + NEW.vat_rate / 100);
        SET NEW.discounted_price    = NEW.price_including_vat * (1 - NEW.discount_percent / 100);
    END");

$conn->query("DROP TRIGGER IF EXISTS trg_product_prices_update");
$conn->query("CREATE TRIGGER trg_product_prices_update
    BEFORE UPDATE ON products FOR EACH ROW
    BEGIN
        SET NEW.price_including_vat = NEW.price_excluding_vat * (1 + NEW.vat_rate / 100);
        SET NEW.discounted_price    = NEW.price_including_vat * (1 - NEW.discount_percent / 100);
    END");
echo "Triggers created (auto VAT calculation)\n\n";


// DEMO USERS

$admin_pw  = password_hash('Admin@123',   PASSWORD_DEFAULT);
$buyer_pw  = password_hash('password123', PASSWORD_DEFAULT);
$seller_pw = password_hash('password123', PASSWORD_DEFAULT);

$conn->query("INSERT IGNORE INTO users (full_name, email, phone, password_hash, user_type, is_verified)
    VALUES ('System Admin',  'admin@townsquare.co.za', '0712345678', '$admin_pw',  'admin',  1)");
$conn->query("INSERT IGNORE INTO users (full_name, email, phone, password_hash, user_type, is_verified)
    VALUES ('Test Buyer',    'buyer@example.com',      '0721234567', '$buyer_pw',  'buyer',  1)");
$conn->query("INSERT IGNORE INTO users (full_name, email, phone, password_hash, user_type, is_verified)
    VALUES ('Test Seller',   'seller@example.com',     '0731234567', '$seller_pw', 'seller', 1)");

echo "Demo users created\n";
echo "  Admin:  admin@townsquare.co.za  /  Admin@123\n";
echo "  Buyer:  buyer@example.com       /  password123\n";
echo "  Seller: seller@example.com      /  password123\n\n";


// SAMPLE PRODUCTS

$seller = $conn->query("SELECT user_id FROM users WHERE email = 'seller@example.com'")->fetch_assoc();
$sid    = $seller['user_id'];

$products = [
    ['Samsung Galaxy Smartphone',  'Excellent condition Samsung Galaxy smartphone with 128GB storage, great camera, and long battery life.',         869.57, 10, 5,  'Electronics', 'https://images.pexels.com/photos/404280/pexels-photo-404280.jpeg?w=400'],
    ['Premium Denim Jeans',        'Brand new denim jeans, size 32, comfortable fit and modern cut. Perfect for casual wear.',                       260.87, 0,  10, 'Clothing',    'https://images.pexels.com/photos/1598507/pexels-photo-1598507.jpeg?w=400'],
    ['Wireless Noise-Cancelling Headphones', 'Premium over-ear wireless headphones with 30-hour battery and active noise cancellation.',            608.70, 15, 8,  'Electronics', 'https://images.pexels.com/photos/3394651/pexels-photo-3394651.jpeg?w=400'],
    ['100% Cotton T-Shirt',        'Comfortable everyday cotton t-shirt, available in multiple colours. Lightweight and breathable.',                130.43, 5,  20, 'Clothing',    'https://images.pexels.com/photos/3965548/pexels-photo-3965548.jpeg?w=400'],
    ['Solid Oak Coffee Table',     'Modern solid oak coffee table. Perfect centrepiece for any living room. Assembly required.',                    1304.35, 0,  3,  'Furniture',   'https://images.pexels.com/photos/12175710/pexels-photo-12175710.jpeg'],
    ['Fiction Bestseller Novel',   'Latest fiction bestseller, perfect for weekend reading. Excellent condition, no markings.',                     130.43, 0,  15, 'Books',       'https://images.pexels.com/photos/27893717/pexels-photo-27893717.jpeg'],
    ['Gaming Laptop',              '15.6" gaming laptop, Intel i7, 16GB RAM, RTX 3060, 512GB SSD. Barely used, excellent condition.',              8695.65, 8, 2,  'Electronics', 'https://images.pexels.com/photos/19012054/pexels-photo-19012054.jpeg'],
    ['Running Shoes',              'Lightweight running shoes, size 10. Only worn twice. Excellent grip and cushioning.',                           434.78, 20, 4,  'Clothing',    'https://images.pexels.com/photos/1456733/pexels-photo-1456733.jpeg'],
];

foreach ($products as [$name, $desc, $price, $disc, $qty, $cat, $img]) {
    $stmt = $conn->prepare(
        "INSERT IGNORE INTO products (seller_id, product_name, description, price_excluding_vat, vat_rate,
                                      discount_percent, quantity_in_stock, category, product_image, status)
         VALUES (?, ?, ?, ?, 15, ?, ?, ?, ?, 'active')"
    );
    $stmt->bind_param("issddiiss", $sid, $name, $desc, $price, $disc, $qty, $cat, $img);
    $stmt->execute();
}

// Recalculate prices for any products missing them
$conn->query("UPDATE products SET
    price_including_vat = price_excluding_vat * (1 + vat_rate/100),
    discounted_price    = price_excluding_vat * (1 + vat_rate/100) * (1 - discount_percent/100)
    WHERE price_including_vat IS NULL OR price_including_vat = 0");

echo count($products) . " sample products inserted\n\n";

// DONE

echo str_repeat("=", 50) . "\n";
echo "SETUP COMPLETE\n";
echo str_repeat("=", 50) . "\n\n";
echo "Next steps:\n";
echo "  1. Visit: http://yourdomain.com/index.php\n";
echo "  2. DELETE this file from your server immediately!\n";
echo "     (Leaving it accessible is a security risk)\n";

$conn->close();
echo "</pre>";
?>