<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Μη εξουσιοδοτημένη πρόσβαση']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    // Check if name exists in JSON data
    if (!isset($data['name'])) {
        die(json_encode(['error' => 'Το όνομα του πίνακα είναι απαραίτητο']));
    }
    
    $canvasName = htmlspecialchars($data['name']);
    $userId = $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare("INSERT INTO canvases (owner_id, name) VALUES (?, ?)");
        $stmt->execute([$userId, $canvasName]);
        
        echo json_encode(['success' => true, 'canva_id' => $pdo->lastInsertId()]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Σφάλμα δημιουργίας πίνακα: ' . $e->getMessage()]);
    }
}
?>