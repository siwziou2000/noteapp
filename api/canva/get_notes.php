<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';
header('Content-Type: application/json');

$canvasId = (int)$_GET['canva_id'];
$userId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'user';

try {
    // ΕΛΕΓΧΟΣ: Προσθήκη "OR ? = 'admin'" για να περνάει ο διαχειριστής
    $access_sql = "
        SELECT c.canva_id 
        FROM canvases c 
        LEFT JOIN canvas_collaborators col ON c.canva_id = col.canva_id 
        WHERE c.canva_id = ? 
        AND (c.owner_id = ? OR col.user_id = ? OR ? = 'admin')
        LIMIT 1
    ";
    
    $access_stmt = $pdo->prepare($access_sql);
    $access_stmt->execute([$canvasId, $userId, $userId, $userRole]);
    
    if (!$access_stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Δεν έχετε πρόσβαση σε αυτόν τον πίνακα']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM notes WHERE canva_id = ?");
    $stmt->execute([$canvasId]);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ΔΙΟΡΘΩΣΗ: Στέλνουμε success και τα δεδομένα στο κλειδί 'data'
    echo json_encode([
        'success' => true,
        'data' => $notes
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'error' => 'Σφάλμα βάσης: ' . $e->getMessage()
    ]);
}