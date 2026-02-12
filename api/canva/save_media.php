<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php');

header('Content-Type: application/json'); // Βεβαιωνόμαστε ότι η απάντηση είναι JSON

if (!isset($_SESSION["user_id"])) {
    echo json_encode(['status' => 'error', 'message' => 'Δεν είστε συνδεδεμένος!']);
    exit;
}

$userId = $_SESSION["user_id"];
$uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/noteapp/api/canva/uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

try {
    $canva_id = !empty($_POST['canva_id']) ? (int)$_POST['canva_id'] : null;
    if (!$canva_id) throw new Exception('Απαιτείται ID πίνακα.');

    // --- ΕΛΕΓΧΟΣ ΠΡΟΣΒΑΣΗΣ ---
    $checkAccess = $pdo->prepare("
        SELECT c.owner_id 
        FROM canvases c 
        LEFT JOIN canvas_collaborators cc ON c.canva_id = cc.canva_id 
        WHERE c.canva_id = ? AND (c.owner_id = ? OR cc.user_id = ?)
    ");
    $checkAccess->execute([$canva_id, $userId, $userId]);
    $canvas = $checkAccess->fetch();
    if (!$canvas) throw new Exception('Δεν έχετε δικαίωμα πρόσβασης.');
    
    $canvasOwnerId = $canvas['owner_id'];
    $group_id = !empty($_POST['group_id']) ? (int)$_POST['group_id'] : null;
    $comment = $_POST['comment'] ?? null;
    $type = $_POST['type'] ?? null;

    $originalFilename = null;
    $data = null;

    // --- ΔΙΑΧΕΙΡΙΣΗ ΑΠΛΗΣ ΣΗΜΕΙΩΣΗΣ (TEXT) ---
    if ($type === 'text') {
        if (!isset($_FILES['file'])) throw new Exception('Το περιεχόμενο της σημείωσης λείπει.');
        
        $originalFilename = $_FILES['file']['name']; // "note.txt"
        $newFilename = uniqid() . '_note.txt';
        $targetPath = $uploadDir . $newFilename;

        if (!move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
            throw new Exception('Αποτυχία αποθήκευσης σημείωσης.');
        }
        $data = '/noteapp/api/canva/uploads/' . $newFilename;
    } 
    // --- ΔΙΑΧΕΙΡΙΣΗ ΑΡΧΕΙΩΝ (Image, File, Video) ---
    else if ($type === 'image' || $type === 'file' || ($type === 'video' && isset($_FILES['file']))) {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Αποτυχία μεταφόρτωσης αρχείου.');
        }

        $originalFilename = $_FILES['file']['name'];
        $newFilename = uniqid() . '_' . basename($originalFilename);
        $targetPath = $uploadDir . $newFilename;

        if (!move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
            throw new Exception('Σφάλμα κατά την αποθήκευση αρχείου.');
        }
        $data = '/noteapp/api/canva/uploads/' . $newFilename;
    } 
    
    // --- ΔΙΑΧΕΙΡΙΣΗ YOUTUBE ---
   elseif ($type === 'video' || $type === 'youtube') {
        if (isset($_POST['url']) && !empty($_POST['url'])) {
            $url = trim($_POST['url']);
            if (!filter_var($url, FILTER_VALIDATE_URL)) throw new Exception('Μη έγκυρο URL.');
            
            $data = $url; // Το πλήρες link (π.χ. https://www.youtube.com/watch?v=...)
            $type = 'video'; // ΑΛΛΑΓΗ: Πάντα 'video' για να το δέχεται το ENUM της βάσης
            $originalFilename = 'YouTube Video';
        }  
        
    }// --- ΔΙΑΧΕΙΡΙΣΗ RICH NOTE ---
    elseif ($type === 'rich_note') {
        $content = $_POST['content'] ?? '';
        $stmt = $pdo->prepare("INSERT INTO notes (owner_id, user_id, content, color, canva_id, group_id, note_type) VALUES (?, ?, ?, ?, ?, ?, 'rich_note')");
        $stmt->execute([$userId, $userId, $content, $_POST['color'] ?? '#fff2a8', $canva_id, $group_id]);
        echo json_encode(['status' => 'success', 'message' => 'Rich note saved', 'id' => $pdo->lastInsertId()]);
        exit;
    } else {
        throw new Exception('Μη έγκυρος τύπος: ' . $type);
    }

    // --- ΤΕΛΙΚΗ ΕΓΓΡΑΦΗ ΣΤΟ MEDIA ---
    $stmt = $pdo->prepare("
        INSERT INTO media (owner_id, user_id, type, data, comment, original_filename, group_id, canva_id, position_x, position_y, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, NOW())
    ");

    $stmt->execute([$canvasOwnerId, $userId, $type, $data, $comment, $originalFilename, $group_id, $canva_id]);
    
    $newId = $pdo->lastInsertId();

    // Φέρνουμε το αντικείμενο για να το επιστρέψουμε στη JS
    $fetch = $pdo->prepare("SELECT * FROM media WHERE id = ?");
    $fetch->execute([$newId]);
    $newMedia = $fetch->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'media' => $newMedia,
        'message' => 'Επιτυχής προσθήκη!'
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}