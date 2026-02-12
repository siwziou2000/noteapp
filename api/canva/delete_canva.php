<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(403);
    die(json_encode(['error' => 'Μη εξουσιοδοτημένη πρόσβαση']));
}

$canvasId = $_GET['id'];
$userId = $_SESSION['user_id'];

try {
    // Έλεγχος ότι ο πίνακας ανήκει στον χρήστη
    $stmt = $pdo->prepare("DELETE FROM canvases WHERE canva_id = ? AND owner_id = ?");
    $stmt->execute([$canvasId, $userId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Ο πίνακας δεν βρέθηκε ή δεν έχετε δικαίωμα διαγραφής']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Σφάλμα βάσης δεδομένων: ' . $e->getMessage()]);
}
?>