<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Unauthorized']));
}

$data = json_decode(file_get_contents('php://input'), true);
$name = htmlspecialchars($data['name']);
$description = htmlspecialchars($data['description'] ?? '');
$owner_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("INSERT INTO canvases (name, description, owner_id) VALUES (?, ?, ?)");
    $stmt->execute([$name, $description, $owner_id]);
    echo json_encode(['success' => true, 'canva_id' => $pdo->lastInsertId()]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>