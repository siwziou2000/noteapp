<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

header('Content-Type: application/json');

if (!isset($_GET['note_id'])) {
    echo json_encode(['error' => 'Λείπει το ID']);
    exit;
}

$noteId = (int)$_GET['note_id'];
$userId = $_SESSION['user_id'];

try {
    // Βρες τον καμβά της σημείωσης
    $stmt = $pdo->prepare("SELECT canva_id FROM notes WHERE note_id = ?");
    $stmt->execute([$noteId]);
    $note = $stmt->fetch();
    
    if (!$note) {
        echo json_encode(['error' => 'Η σημείωση δεν βρέθηκε']);
        exit;
    }
    
    $canvaId = $note['canva_id'];
    
    // Έλεγχος δικαιωμάτων
    $access_sql = "
        SELECT 
            CASE 
                WHEN c.owner_id = ? THEN 'owner'
                WHEN cc.user_id = ? AND cc.can_edit_notes = 1 THEN 'collaborator_edit'
                ELSE 'no_access'
            END as access_level
        FROM canvases c 
        LEFT JOIN canvas_collaborators cc ON c.canva_id = cc.canva_id AND cc.user_id = ?
        WHERE c.canva_id = ?
        LIMIT 1
    ";
    
    $access_stmt = $pdo->prepare($access_sql);
    $access_stmt->execute([$userId, $userId, $userId, $canvaId]);
    $access_data = $access_stmt->fetch();
    
    $can_edit = ($access_data && in_array($access_data['access_level'], ['owner', 'collaborator_edit']));
    
    echo json_encode(['can_edit' => $can_edit]);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Σφάλμα βάσης: ' . $e->getMessage()]);
}
?>