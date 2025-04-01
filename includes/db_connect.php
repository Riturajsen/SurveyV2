<?php
// --- Database Configuration ---
$dbHost = 'localhost';    // Or your DB host (e.g., 127.0.0.1)
$dbUser = 'root';         // Your DB username (default for XAMPP)
$dbPass = 'admin';             // Your DB password (default for XAMPP)
$dbName = 'surveyV2'; // <<<<< UPDATE if you used a different DB name
$dbCharset = 'utf8mb4';
// --- End Configuration ---

// --- Establish Connection (PDO) ---
$dsn = "mysql:host=$dbHost;dbname=$dbName;charset=$dbCharset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on SQL errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch results as associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Use native prepared statements
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die("Database connection failed. Please check server logs or contact support.");
}
// --- Connection Established ---
?>

<!-- u726396859_surveyV2 -->