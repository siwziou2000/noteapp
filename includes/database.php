<?php
// includes/database.php

$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'noteapp';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4", 
        $username, 
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    if (ob_get_length()) ob_end_clean();
    
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    
    if (ini_get('display_errors')) {
        die("Σφάλμα σύνδεσης: " . htmlspecialchars($e->getMessage()));
    } else {
        die("Σφάλμα συστήματος. Παρακαλώ δοκιμάστε ξανά αργότερα.");
    }
}