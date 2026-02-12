<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';
header('Content-Type: application/json');

$isAdminMode = isset($_GET['admin']) && $_GET['admin'] == '1' && $_SESSION['role'] === 'admin';
$userId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'user';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    die(json_encode(['error' => 'Μη έγκυρη μέθοδος']));
}

$noteId = (int)$_GET['id'];

try {
    // ΔΙΟΡΘΩΣΗ: Φέρνουμε και τον owner του καμβά (c.owner_id)
    $stmt = $pdo->prepare("
        SELECT n.owner_id, n.locked_by, n.canva_id, c.owner_id as canvas_owner_id 
        FROM notes n 
        JOIN canvases c ON n.canva_id = c.canva_id 
        WHERE n.note_id = ?
    ");
    $stmt->execute([$noteId]);
    $note = $stmt->fetch();

    if (!$note) {
        die(json_encode(['error' => 'Η σημείωση δεν βρέθηκε']));
    }

    $canDelete = false;

    // 1. Έλεγχος Admin
    if ($userRole === 'admin' || $isAdminMode) {
        $canDelete = true;
    } 
    else {
        // 2. Έλεγχος αν είναι ο ιδιοκτήτης της ΣΗΜΕΙΩΣΗΣ Η ο ιδιοκτήτης του ΚΑΜΒΑ
        if ($userId === (int)$note['owner_id'] || $userId === (int)$note['canvas_owner_id']) {
            
            // Έλεγχος κλειδώματος (μόνο αν δεν είναι ο canvas owner, ο canvas owner μπορεί να σβήσει τα πάντα)
            if ($userId !== (int)$note['canvas_owner_id']) {
                if (!empty($note['locked_by']) && $note['locked_by'] != $userId) {
                    die(json_encode(['error' => 'Η σημείωση είναι κλειδωμένη από άλλον χρήστη.']));
                }
            }
            $canDelete = true;
        }
    }

    if ($canDelete) {
        $stmtDel = $pdo->prepare("DELETE FROM notes WHERE note_id = ?");
        $stmtDel->execute([$noteId]);
        echo json_encode(['success' => true, 'message' => 'Η σημείωση διαγράφηκε επιτυχώς']);
    } else {
        http_response_code(403);
        echo json_encode(['error' => 'Δεν έχετε δικαίωμα διαγραφής αυτής της σημείωσης']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Σφάλμα βάσης: ' . $e->getMessage()]);
}