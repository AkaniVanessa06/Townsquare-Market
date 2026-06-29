<?php

$conn = new mysqli("127.0.0.1", "root", "", "");

if ($conn->connect_error) {
    die("FAILED: " . $conn->connect_error);
}

echo "CONNECTED SUCCESSFULLY";