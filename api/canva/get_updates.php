<?php
// get_updates.php
// Αντικαταστήστε τη γραμμή 2 στο get_updates.php:
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';
header('Content-Type: application/json');

if (!isset($_GET['canva_id'])) {
    echo json_encode(['success' => false, 'error' => 'No ID provided']);
    exit;
}

$canva_id = $_GET['canva_id'];

try {
    $notesStmt = $pdo->prepare("SELECT * FROM notes WHERE canva_id = ?");
    $notesStmt->execute([$canva_id]);
    
    $mediaStmt = $pdo->prepare("SELECT * FROM media WHERE canva_id = ?");
    $mediaStmt->execute([$canva_id]);

    echo json_encode([
        'success' => true,
        'notes' => $notesStmt->fetchAll(PDO::FETCH_ASSOC),
        'media' => $mediaStmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>