<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Ο χρήστης δεν είναι συνδεδεμένος.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['note_id'])) {
    echo json_encode(['success' => false, 'error' => 'Μη έγκυρα δεδομένα.']);
    exit;
}

$note_id = (int)$input['note_id'];
$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'student';

try {
    // 1. Φέρνουμε τη σημείωση ΚΑΙ τον owner του καμβά
    // 1. Φέρνουμε τη σημείωση, τον owner του καμβά ΚΑΙ το δικαίωμα από τους collaborators
$checkSql = "SELECT n.*, c.owner_id as canvas_owner_id, 
                    cc.permission, cc.can_edit_notes
             FROM notes n 
             JOIN canvases c ON n.canva_id = c.canva_id 
             LEFT JOIN canvas_collaborators cc ON (c.canva_id = cc.canva_id AND cc.user_id = ?)
             WHERE n.note_id = ?";

$checkStmt = $pdo->prepare($checkSql);
// ΠΡΟΣΟΧΗ: Περνάμε δύο παραμέτρους: το $user_id (για το JOIN) και το $note_id (για το WHERE)
$checkStmt->execute([$user_id, $note_id]); 
$note = $checkStmt->fetch();

    if (!$note) {
        echo json_encode(['success' => false, 'error' => 'Η σημείωση δεν βρέθηκε.']);
        exit;
    }

    // 2. ΕΛΕΓΧΟΣ ΔΙΚΑΙΩΜΑΤΩΝ (Admin ή Owner Καμβά ή Owner Σημείωσης)
    $isAdmin = (strtolower($user_role) === 'admin' || strtolower($user_role) === 'teacher');
    $isCanvasOwner = ((int)$note['canvas_owner_id'] === $user_id);
    $isNoteOwner = ((int)$note['user_id'] === $user_id);
    
    // ΝΕΟΣ ΕΛΕΓΧΟΣ: Αν ο χρήστης είναι στον πίνακα collaborators με δικαίωμα edit
    $isCollaboratorWithEdit = ($note['permission'] === 'edit' || (int)$note['can_edit_notes'] === 1);

    if (!$isAdmin && !$isCanvasOwner && !$isNoteOwner && !$isCollaboratorWithEdit) {
        echo json_encode(['success' => false, 'error' => 'Δεν έχετε δικαίωμα επεξεργασίας σε αυτή τη σημείωση.']);
        exit;
    }

    // 3. ΕΛΕΓΧΟΣ LOCK (Επιτρέπουμε την επεξεργασία αν δεν είναι κλειδωμένη ή αν την κλείδωσε ο ίδιος)
    if (!$isAdmin && !$isCanvasOwner) {
        if (!empty($note['locked_by']) && (int)$note['locked_by'] !== $user_id) {
            echo json_encode(['success' => false, 'error' => 'Η σημείωση είναι κλειδωμένη από άλλον χρήστη.']);
            exit;
        }
    }
    // 4. ΕΝΗΜΕΡΩΣΗ
    $allowedFields = ['content', 'color', 'position_x', 'position_y', 'tag', 'icon', 'due_date', 'font'];
    $updates = [];
    $params = [];

    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updates[] = "$field = ?";
            $params[] = ($field === 'due_date' && empty($input[$field])) ? null : $input[$field];
        }
    }

    if (empty($updates)) {
        echo json_encode(['success' => true, 'message' => 'Καμία αλλαγή.']);
        exit;
    }

    // Ξεκλειδώνουμε αυτόματα τη σημείωση μετά την αποθήκευση
    $sql = "UPDATE notes SET " . implode(', ', $updates) . ", locked_by = NULL, locked_at = NULL WHERE note_id = ?";
    $params[] = $note_id;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}