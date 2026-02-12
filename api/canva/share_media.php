<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Method not allowed']));
}

// CSRF Protection
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    die(json_encode(['error' => 'Invalid CSRF token']));
}

$mediaId = filter_input(INPUT_POST, 'media_id', FILTER_VALIDATE_INT);
$shareWith = filter_input(INPUT_POST, 'share_with', FILTER_VALIDATE_INT);
$permission = $_POST['permission'] ?? 'view';

if (!$mediaId || !$shareWith) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid media ID or user ID']));
}

try {
    // Έλεγχος δικαιωμάτων ιδιοκτήτη
    $stmt = $pdo->prepare("SELECT owner_id, canva_id FROM media WHERE id = ?");
    $stmt->execute([$mediaId]);
    $media = $stmt->fetch();

    if (!$media || $media['owner_id'] !== $_SESSION['user_id']) {
        http_response_code(403);
        die(json_encode(['error' => 'Δεν έχετε δικαίωμα κοινής χρήσης']));
    }

    // Κοινή χρήση πολυμέσου
    $stmt = $pdo->prepare("
        INSERT INTO shared_media (media_id, shared_with, permission, shared_by) 
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE permission = ?, shared_at = NOW()
    ");
    $stmt->execute([$mediaId, $shareWith, $permission, $_SESSION['user_id'], $permission]);

    // Αν το πολυμέσο ανήκει σε canvas, κοινή χρήση και του canvas
    if ($media['canva_id']) {
        $stmt = $pdo->prepare("
            INSERT INTO canvas_collaborators (canva_id, user_id, permission, added_by) 
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE permission = ?
        ");
        $stmt->execute([$media['canva_id'], $shareWith, $permission, $_SESSION['user_id'], $permission]);
    }

    echo json_encode(['success' => true, 'message' => 'Το πολυμέσο μοιράστηκε επιτυχώς']);

} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Σφάλμα βάσης: ' . $e->getMessage()]));
}
?>