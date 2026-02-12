<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

$mediaId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$mediaId) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid media ID']));
}

try {
    // 1. ΕΛΕΓΧΟΣ ΑΝ ΕΙΝΑΙ ADMIN
    $isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
    $userId = $_SESSION['user_id'];

    // 2. ΔΙΟΡΘΩΜΕΝΟ SQL QUERY
    // Προσθέσαμε το (? = 1) για να παρακάμπτει ο Admin τους ελέγχους ιδιοκτησίας
    $stmt = $pdo->prepare("
        SELECT m.* FROM media m
        LEFT JOIN canvas_collaborators cc ON m.canva_id = cc.canva_id AND cc.user_id = ?
        WHERE m.id = ? 
        AND (? = 1 OR m.owner_id = ? OR cc.user_id IS NOT NULL)
    ");

    // 3. ΕΚΤΕΛΕΣΗ ΜΕ ΤΙΣ ΣΩΣΤΕΣ ΠΑΡΑΜΕΤΡΟΥΣ (Σύνολο 4 ερωτηματικά)
    $stmt->execute([
        $userId,             // για το cc.user_id = ?
        $mediaId,            // για το m.id = ?
        $isAdmin ? 1 : 0,    // για το ? = 1
        $userId              // για το m.owner_id = ?
    ]);
    
    $media = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$media) {
        http_response_code(404);
        die(json_encode(['error' => 'Το πολυμέσο δεν βρέθηκε ή δεν έχετε πρόσβαση']));
    }

    header('Content-Type: application/json');
    echo json_encode($media);

} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Database error: ' . $e->getMessage()]));
}