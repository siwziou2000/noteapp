<?php
session_start();

// Include database connection
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

// CSRF Protection
if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(403);
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Invalid CSRF token']));
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Not authenticated']));
}

// Αρχικοποίηση μεταβλητών
$mediaId = null;
$comment = null;
$content = null;
$file = null;
$url = null;
$keepExisting = false;

// Έλεγχος αν είναι multipart/form-data (για μεταφορτώσεις αρχείων)
if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false) {
    $mediaId = isset($_POST['id']) ? filter_var($_POST['id'], FILTER_VALIDATE_INT) : null;
    $comment = isset($_POST['comment']) ? htmlspecialchars($_POST['comment'], ENT_QUOTES, 'UTF-8') : null;
    $content = $_POST['content'] ?? null;
    $file = $_FILES['file'] ?? null;
    $url = $_POST['url'] ?? null;
    $keepExisting = isset($_POST['keep_existing']) && $_POST['keep_existing'] === 'true';
} 
// Έλεγχος αν είναι JSON (για απλές ενημερώσεις)
else {
    $inputData = file_get_contents('php://input');
    $data = json_decode($inputData, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        header('Content-Type: application/json');
        die(json_encode(['error' => 'Invalid JSON data']));
    }
    
    $mediaId = isset($data['id']) ? filter_var($data['id'], FILTER_VALIDATE_INT) : null;
    $comment = isset($data['comment']) ? htmlspecialchars($data['comment'], ENT_QUOTES, 'UTF-8') : null;
    $content = $data['content'] ?? null;
    $url = $data['url'] ?? null;
    $keepExisting = isset($data['keep_existing']) && $data['keep_existing'] === true;
}

if (!$mediaId) {
    http_response_code(400);
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Invalid media ID']));
}

try {
    global $pdo;
    
    if (!isset($pdo)) {
        throw new Exception('Database connection not available');
    }
    
    // 1. Έλεγχος αν ο χρήστης είναι admin
    $adminCheckStmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
    $adminCheckStmt->execute([$_SESSION['user_id']]);
    $userInfo = $adminCheckStmt->fetch();
    
    $isAdmin = ($userInfo && $userInfo['role'] === 'admin');
    
    // 2. Διαφορετική διαχείριση για admin vs regular user
    if ($isAdmin) {
        // Ο ADMIN μπορεί να επεξεργαστεί ΟΠΟΙΟΔΉΠΟΤΕ media
        $stmt = $pdo->prepare("SELECT m.* FROM media m WHERE m.id = ? LIMIT 1");
        $stmt->execute([$mediaId]);
        $media = $stmt->fetch();
        
        if (!$media) {
            throw new Exception('Το πολυμέσο δεν βρέθηκε.');
        }
    } else {
        // REGULAR USER: Έλεγχος δικαιωμάτων
        $permissionStmt = $pdo->prepare("
            SELECT m.* FROM media m
            JOIN canvases c ON m.canva_id = c.canva_id
            LEFT JOIN canvas_collaborators cc ON c.canva_id = c.canva_id
            WHERE m.id = ? AND (c.owner_id = ? OR cc.user_id = ? OR m.user_id = ?)
            LIMIT 1
        ");
        $permissionStmt->execute([$mediaId, $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
        $media = $permissionStmt->fetch();
        
        if (!$media) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            die(json_encode(['success' => false, 'error' => 'Δεν έχετε δικαίωμα επεξεργασίας.']));
        }
    }

    // 3. Κλείδωμα του πολυμέσου για επεξεργασία
    $lockStmt = $pdo->prepare("UPDATE media SET locked_by = ?, locked_at = NOW() WHERE id = ?");
    $lockStmt->execute([$_SESSION['user_id'], $mediaId]);

    // 4. Προετοιμασία ενημέρωσης
    $updateFields = ['updated_at = NOW()'];
    $updateParams = [];

    if ($comment !== null) {
        $updateFields[] = 'comment = ?';
        $updateParams[] = $comment;
    }
    
    // Για σημειώσεις (text), ενημερώνουμε το πεδίο data
    if ($content !== null && in_array($media['type'], ['text', 'rich_note'])) {
        $updateFields[] = 'data = ?';
        $cleanContent = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
        $cleanContent = nl2br($cleanContent);
        $updateParams[] = $cleanContent;

        if($media['type'] === 'rich_note') {
            $updateFields[] = 'type = ?';
            $updateParams[] = 'text';
        }
    }
// Επεξεργασία YouTube URL
    if ($url !== null && ($media['type'] === 'video' || $media['type'] === 'youtube')) {
        $cleanUrl = filter_var($url, FILTER_SANITIZE_URL);
        
        // 1. Ενημερώνουμε το data με το πλήρες URL
        $updateFields[] = 'data = ?';
        $updateParams[] = $cleanUrl;
        
        // 2. ΠΡΟΣΟΧΗ: Βάζουμε 'video' και ΟΧΙ 'youtube' γιατί το ENUM της βάσης 
        // σου δέχεται μόνο: 'image','video','file','text'
        $updateFields[] = 'type = ?';
        $updateParams[] = 'video'; 
        
        // 3. Ενημερώνουμε το όνομα αρχείου για να ξέρουμε τι είναι
        $updateFields[] = 'original_filename = ?';
        $updateParams[] = "YouTube Video"; 
        
        // Καθαρίζουμε τυχόν περιεχόμενο αν ήταν προηγουμένως κείμενο
        if (isset($media['content'])) {
            $updateFields[] = 'content = NULL';
        }
    }

    // Αν ζητήθηκε να παραμείνουν τα υπάρχοντα δεδομένα
    if ($keepExisting) {
        // Δεν κάνουμε τίποτα - διατηρούμε τα υπάρχοντα
    }

    // Διαχείριση μεταφόρτωσης αρχείου
    if ($file && $file['error'] === UPLOAD_ERR_OK && empty($url)) {
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/noteapp/api/canva/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = preg_replace('/[^A-Za-z0-9_\.-]/', '_', basename($file['name']));
        $uniqueName = uniqid() . '_' . $filename;
        $targetPath = $uploadDir . $uniqueName;

        $allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $allowedVideoTypes = ['video/mp4', 'video/webm', 'video/ogg'];
        $allowedFileTypes = array_merge($allowedImageTypes, $allowedVideoTypes, [
            'application/pdf', 
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ]);

        if (!in_array($file['type'], $allowedFileTypes)) {
            throw new Exception('Μη υποστηριζόμενος τύπος αρχείου: ' . $file['type']);
        }

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception('Αποτυχία αποθήκευσης αρχείου');
        }

        $updateFields[] = 'data = ?';
        $updateParams[] = '/noteapp/api/canva/uploads/' . $uniqueName;

        $updateFields[] = 'original_filename = ?';
        $updateParams[] = $filename;
        
        if (in_array($file['type'], $allowedImageTypes)) {
            $updateFields[] = 'type = ?';
            $updateParams[] = 'image';
        } elseif (in_array($file['type'], $allowedVideoTypes)) {
            $updateFields[] = 'type = ?';
            $updateParams[] = 'video';
        } else {
            $updateFields[] = 'type = ?';
            $updateParams[] = 'file';
        }
    }

    // Εκτέλεση ενημέρωσης μόνο αν υπάρχουν αλλαγές
    if (count($updateFields) > 1) {
        $updateParams[] = $mediaId;
        $updateStmt = $pdo->prepare("UPDATE media SET " . implode(', ', $updateFields) . " WHERE id = ?");
        $updateStmt->execute($updateParams);
    }

    // Ξεκλείδωμα
    $unlockStmt = $pdo->prepare("UPDATE media SET locked_by = NULL, locked_at = NULL WHERE id = ?");
    $unlockStmt->execute([$mediaId]);

    // Απάντηση με πληροφορία για admin
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true, 
        'message' => 'Επιτυχής ενημέρωση',
        'media_id' => $mediaId,
        'updated_by_admin' => $isAdmin,
        'admin_note' => $isAdmin ? 'Ενημέρωση από διαχειριστή' : 'Ενημέρωση από κανονικό χρήστη'
    ]);
    
} catch (Exception $e) {
    // Αποτυχία - ξεκλείδωμα σε περίπτωση σφάλματος
    if (isset($mediaId) && isset($pdo)) {
        try {
            $unlockStmt = $pdo->prepare("UPDATE media SET locked_by = NULL, locked_at = NULL WHERE id = ?");
            $unlockStmt->execute([$mediaId]);
        } catch (Exception $unlockError) {
            error_log("Unlock error: " . $unlockError->getMessage());
        }
    }
    
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['success' => false, 'error' => 'Σφάλμα: ' . $e->getMessage()]));
}