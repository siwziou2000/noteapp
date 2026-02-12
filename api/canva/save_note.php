<?php
session_start();
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'].'/noteapp/includes/database.php';

// 1. Έλεγχος σύνδεσης
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['success'=>false,'error'=>'Ο χρήστης δεν είναι συνδεδεμένος.']));
}

$currentUserId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'user';

// 2. Ανάγνωση δεδομένων
$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['content'])) {
    http_response_code(400);
    die(json_encode(['success'=>false,'error'=>'Το περιεχόμενο είναι απαραίτητο.']));
}

// Προετοιμασία δεδομένων
$note_id = isset($data['note_id']) ? (int)$data['note_id'] : null;
$canva_id = (int)$data['canva_id'];
$content = $data['content'];

try {
    // 3. ΕΛΕΓΧΟΣ ΔΙΚΑΙΩΜΑΤΩΝ
    // Φέρνουμε τον ιδιοκτήτη του καμβά
    $canvasStmt = $pdo->prepare("SELECT owner_id FROM canvases WHERE canva_id = ?");
    $canvasStmt->execute([$canva_id]);
    $canvas = $canvasStmt->fetch();
    $isCanvasOwner = ($canvas && $canvas['owner_id'] == $currentUserId);

    if ($note_id) {
        // ΕΝΗΜΕΡΩΣΗ (UPDATE)
        $checkStmt = $pdo->prepare("SELECT owner_id, locked_by FROM notes WHERE note_id = ?");
        $checkStmt->execute([$note_id]);
        $existingNote = $checkStmt->fetch();

        if (!$existingNote) {
            die(json_encode(['success'=>false,'error'=>'Η σημείωση δεν βρέθηκε.']));
        }

        // Επιτρέπουμε αν: είναι Admin Ή ο κάτοχος του καμβά (Teacher) Ή ο κάτοχος της σημείωσης
        $canEdit = ($userRole === 'admin' || $isCanvasOwner || $existingNote['owner_id'] == $currentUserId);

        if (!$canEdit) {
            die(json_encode(['success'=>false,'error'=>'Δεν έχετε δικαίωμα επεξεργασίας.']));
        }

        // SQL για Update (Ενημερώνουμε μόνο περιεχόμενο, χρώμα κτλ, δεν αλλάζουμε τον αρχικό owner)
        $sql = "UPDATE notes SET 
                content = :content, 
                color = :color, 
                font = :font,
                updated_at = NOW(),
                locked_by = NULL, 
                locked_at = NULL 
                WHERE note_id = :note_id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':content' => $content,
            ':color'   => $data['color'] ?? '#fff2a8',
            ':font'    => $data['font'] ?? 'Arial',
            ':note_id' => $note_id
        ]);

    } else {
        // ΝΕΑ ΣΗΜΕΙΩΣΗ (INSERT)
        // Εδώ ο owner_id παραμένει αυτός που τη δημιουργεί
        $sql = "INSERT INTO notes (owner_id, user_id, content, canva_id, color, position_x, position_y, created_at) 
                VALUES (:owner_id, :user_id, :content, :canva_id, :color, :pos_x, :pos_y, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':owner_id' => $currentUserId,
            ':user_id'  => $currentUserId,
            ':content'  => $content,
            ':canva_id' => $canva_id,
            ':color'    => $data['color'] ?? '#fff2a8',
            ':pos_x'    => $data['position_x'] ?? 0,
            ':pos_y'    => $data['position_y'] ?? 0
        ]);
        $note_id = $pdo->lastInsertId();
    }

    echo json_encode(['success'=>true, 'note_id'=>$note_id]);

} catch (PDOException $e) {
    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
}