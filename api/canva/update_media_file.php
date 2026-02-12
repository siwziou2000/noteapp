
//update_media_file.php


<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

// ---------------- CSRF Protection ----------------
if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    die(json_encode(['error' => 'Invalid CSRF token']));
}

// ---------------- Διαχείριση Δεδομένων ----------------
// Αν είναι FormData (multipart/form-data)
$mediaId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$comment = filter_var($_POST['comment'] ?? null, FILTER_SANITIZE_STRING);
$title = filter_var($_POST['title'] ?? null, FILTER_SANITIZE_STRING);
$content = $_POST['content'] ?? null;
$file = $_FILES['file'] ?? null;

// Αν είναι JSON (π.χ. απλή ενημέρωση σχολίου)
if (!$mediaId) {
    $data = json_decode(file_get_contents('php://input'), true);
    $mediaId = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
    $comment = filter_var($data['comment'] ?? $comment, FILTER_SANITIZE_STRING);
    $title = filter_var($data['title'] ?? $title, FILTER_SANITIZE_STRING);
    $content = $data['content'] ?? $content;
}

if (!$mediaId) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid media ID']));
}

try {
    // ---------------- Έλεγχος Δικαιωμάτων ----------------
    $stmt = $pdo->prepare("
        SELECT m.id, m.type 
        FROM media m
        LEFT JOIN canvas_collaborators cc 
            ON m.canva_id = cc.canva_id AND cc.user_id = ?
        WHERE m.id = ? AND (m.owner_id = ? OR (cc.user_id IS NOT NULL AND cc.permission = 'edit'))
    ");
    $stmt->execute([$_SESSION['user_id'], $mediaId, $_SESSION['user_id']]);
    $media = $stmt->fetch();
    
    if (!$media) {
        http_response_code(403);
        die(json_encode(['error' => 'Δεν έχετε δικαίωμα επεξεργασίας']));
    }

    // ---------------- Κλείδωμα Πολυμέσου ----------------
    $pdo->prepare("UPDATE media SET locked_by = ?, locked_at = NOW() WHERE id = ?")
       ->execute([$_SESSION['user_id'], $mediaId]);

    // ---------------- Προετοιμασία Ενημέρωσης ----------------
    $updateFields = ['updated_at = NOW()'];
    $updateParams = [];

    if ($comment !== null) {
        $updateFields[] = 'comment = ?';
        $updateParams[] = $comment;
    }
    if ($title !== null && $media['type'] === 'text') {
        $updateFields[] = 'title = ?';
        $updateParams[] = $title;
    }
    if ($content !== null && in_array($media['type'], ['text', 'rich_note'])) {
        $updateFields[] = 'content = ?';
        $updateParams[] = $content;
    }

    // ---------------- Διαχείριση Αρχείου ----------------
    if ($file && $file['error'] === UPLOAD_ERR_OK) {
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/noteapp/api/canva/uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $filename = basename($file['name']);
        $uniqueName = uniqid() . '_' . $filename;
        $targetPath = $uploadDir . $uniqueName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception('Αποτυχία αποθήκευσης αρχείου');
        }

        // Αποθήκευση στη βάση: χρησιμοποιούμε σχετικό URL
        $updateFields[] = 'data = ?';
        $updateParams[] = '/uploads/' . $uniqueName; // Σχετικό path από τη ρίζα της εφαρμογής



        $updateFields[] = 'original_filename = ?';
        $updateParams[] = $filename;
    }

    $updateParams[] = $mediaId;
    $updateStmt = $pdo->prepare("UPDATE media SET " . implode(', ', $updateFields) . " WHERE id = ?");
    $updateStmt->execute($updateParams);

    // ---------------- Ξεκλείδωμα ----------------
    $pdo->prepare("UPDATE media SET locked_by = NULL, locked_at = NULL WHERE id = ?")
       ->execute([$mediaId]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // Ξεκλείδωμα σε περίπτωση σφάλματος
    if (isset($mediaId)) {
        $pdo->prepare("UPDATE media SET locked_by = NULL, locked_at = NULL WHERE id = ?")
           ->execute([$mediaId]);
    }

    http_response_code(500);
    echo json_encode(['error' => 'Σφάλμα: ' . $e->getMessage()]);
}
