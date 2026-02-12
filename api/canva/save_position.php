<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

header('Content-Type: application/json');

// CSRF Protection
if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    die(json_encode(['error' => 'Invalid CSRF token']));
}

$data = json_decode(file_get_contents('php://input'), true);

$noteId = $data['note_id'] ?? $data['noteId'] ?? null;
$positionX = $data['position_x'] ?? null;
$positionY = $data['position_y'] ?? null;
$userId = $_SESSION['user_id'] ?? null;

// Validate required fields
if (!$noteId || $positionX === null || $positionY === null || !$userId) {
    http_response_code(400);
    die(json_encode(['error' => 'Missing required fields']));
}

try {
    // ΔΙΟΡΘΩΣΗ: Ενημέρωση θέσης για οποιονδήποτε χρήστη με πρόσβαση (όχι μόνο owner)
    $stmt = $pdo->prepare("
        UPDATE notes 
        SET position_x = ?, position_y = ?, updated_at = NOW() 
        WHERE note_id = ?
    ");
    $stmt->execute([
        (int)$positionX,
        (int)$positionY,
        (int)$noteId
    ]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Database error: ' . $e->getMessage()]));
}
?>