<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

header('Content-Type: application/json');

// CSRF Protection
if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
    die(json_encode(['error' => 'Μη έγκυρο αίτημα CSRF']));
}

// Έλεγχος σύνδεσης χρήστη
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Μη εξουσιοδοτημένη πρόσβαση']));
}

$data = json_decode(file_get_contents('php://input'), true);
$media_id = isset($data['media_id']) ? (int)$data['media_id'] : null;
$position_x = isset($data['position_x']) ? (int)$data['position_x'] : 0;
$position_y = isset($data['position_y']) ? (int)$data['position_y'] : 0;
$canva_id = isset($data['canva_id']) ? (int)$data['canva_id'] : null;
$user_id = (int)$_SESSION['user_id'];

if (!$media_id || !$canva_id) {
    die(json_encode(['error' => 'Λείπουν απαραίτητα πεδία']));
}

try {
    // Έλεγχος πρόσβασης
    $stmt_check = $pdo->prepare("
        SELECT m.id 
        FROM media m
        LEFT JOIN canvas_collaborators cc ON m.canva_id = cc.canva_id
        WHERE m.id = ? 
        AND m.canva_id = ?
        AND (m.owner_id = ? OR cc.user_id = ?)
    ");
    $stmt_check->execute([$media_id, $canva_id, $user_id, $user_id]);
    
    if (!$stmt_check->fetch()) {
        die(json_encode(['error' => 'Δεν έχετε δικαίωμα να επεξεργαστείτε αυτό το πολυμέσο']));
    }

    // Ενημέρωση θέσης
    $stmt_update = $pdo->prepare("
        UPDATE media 
        SET position_x = ?, position_y = ? 
        WHERE id = ? AND canva_id = ?
    ");
    $stmt_update->execute([$position_x, $position_y, $media_id, $canva_id]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    error_log("Database error in save_media_position.php: " . $e->getMessage());
    die(json_encode(['error' => 'Σφάλμα βάσης δεδομένων']));
}