<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Μη υποστηριζόμενη μέθοδος', 405);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Επαλήθευση υποχρεωτικών πεδίων (τώρα με βάση τη δομή του media)
    $required = ['id', 'position_x', 'position_y'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            throw new Exception("Λείπει το πεδίο: $field", 400);
        }
    }

    $stmt = $pdo->prepare("
        UPDATE media 
        SET position_x = :position_x, position_y = :position_y
        WHERE id = :id AND user_id = :user_id
    ");
    
    $stmt->execute([
        ':position_x' => (int)$data['position_x'],
        ':position_y' => (int)$data['position_y'],
        ':id' => (int)$data['id'],  // Αλλάξαμε από note_id σε media_id
        ':user_id' => $_SESSION['user_id']
    ]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>