<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

header('Content-Type: application/json');

if (!isset($_GET['note_id'])) {
    echo json_encode(['error' => 'Λείπει το ID της σημείωσης']);
    exit;
}

$noteId = (int)$_GET['note_id'];
$userId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'user';

try {
    // 1. Ανάκτηση της σημείωσης και των στοιχείων του καμβά στον οποίο ανήκει
    $stmt = $pdo->prepare("
        SELECT n.*, c.owner_id as canvas_owner_id, c.access_type 
        FROM notes n 
        JOIN canvases c ON n.canva_id = c.canva_id 
        WHERE n.note_id = ?
    ");
    $stmt->execute([$noteId]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$note) {
        echo json_encode(['error' => 'Η σημείωση δεν βρέθηκε']);
        exit;
    }

    // 2. Έλεγχος αν ο χρήστης είναι επίσημος συνεργάτης (Collaborator)
    $stmt_collab = $pdo->prepare("
        SELECT permission FROM canvas_collaborators 
        WHERE canva_id = ? AND user_id = ? AND status = 'accepted'
    ");
    $stmt_collab->execute([(int)$note['canva_id'], $userId]);
    $collaborator = $stmt_collab->fetch();

    // 3. Καθορισμός δικαιωμάτων πρόσβασης (Logic Check)
    $isOwner = ($userId === (int)$note['canvas_owner_id'] || $userId === (int)$note['owner_id']);
    $isAdmin = ($userRole === 'admin');
    $isPublic = ($note['access_type'] === 'public'); // Αν ο πίνακας είναι δημόσιος
    $isAcceptedCollaborator = ($collaborator !== false);

    // Ο Viewer μπορεί να δει τη σημείωση αν ισχύει οποιοδήποτε από τα παρακάτω:
    if ($isAdmin || $isOwner || $isAcceptedCollaborator || $isPublic) {
        echo json_encode($note);
    } else {
        http_response_code(403);
        echo json_encode(['error' => 'Δεν έχετε δικαίωμα προβολής αυτής της σημείωσης']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Σφάλμα συστήματος: ' . $e->getMessage()]);
}