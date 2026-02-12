<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

header('Content-Type: application/json');

$canvaId = filter_input(INPUT_GET, 'canva_id', FILTER_VALIDATE_INT);
$userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);

if (!$canvaId || !$userId) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Invalid canvas ID or user ID']));
}

try {
    // 1. ΕΛΕΓΧΟΣ ΠΡΟΣΒΑΣΗΣ
    $accessStmt = $pdo->prepare("
        SELECT c.canva_id 
        FROM canvases c 
        LEFT JOIN canvas_collaborators cc ON c.canva_id = cc.canva_id AND cc.user_id = ?
        WHERE c.canva_id = ? 
        AND (c.owner_id = ? OR cc.user_id IS NOT NULL OR c.access_type IN ('public', 'shared', 'δημόσιο'))
        LIMIT 1
    ");
    $accessStmt->execute([$userId, $canvaId, $userId]);
    
    if (!$accessStmt->fetch()) {
        http_response_code(403);
        die(json_encode(['success' => false, 'error' => 'Δεν έχετε δικαίωμα πρόσβασης στον πίνακα.']));
    }

    // 2. ΛΗΨΗ MEDIA (Εικόνες, Βίντεο, Αρχεία)
    $mediaStmt = $pdo->prepare("
        SELECT m.*, 
               u.username AS locked_by_name, 
               'media' as source_table
        FROM media m
        LEFT JOIN users u ON m.locked_by = u.user_id
        WHERE m.canva_id = ?
    ");
    $mediaStmt->execute([$canvaId]);
    $mediaItems = $mediaStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. ΛΗΨΗ RICH NOTES (Πλούσιες Σημειώσεις)
    // 3. ΛΗΨΗ RICH NOTES (Διορθωμένο)
$notesStmt = $pdo->prepare("
    SELECT 
        n.note_id as id,
        n.owner_id,
        n.user_id,
        n.content as data,
        n.content,
        n.color,
        n.tag,
        n.icon,
        n.font,
        n.due_date,
        n.position_x,
        n.position_y,
        n.canva_id,
        n.locked_by,            /* ΠΡΟΣΘΗΚΗ */
        u.username AS locked_by_name, /* ΠΡΟΣΘΗΚΗ */
        'rich_note' as type,
        'notes' as source_table
    FROM notes n
    LEFT JOIN users u ON n.locked_by = u.user_id /* ΠΡΟΣΘΗΚΗ JOIN */
    WHERE n.canva_id = ?
");
    $notesStmt->execute([$canvaId]);
    $notesItems = $notesStmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. ΕΠΙΣΤΡΟΦΗ ΔΕΔΟΜΕΝΩΝ ΣΕ ΞΕΧΩΡΙΣΤΑ ΚΛΕΙΔΙΑ
    // Αυτό επιτρέπει στην displayMediaOnCanvas(media, notes) να δουλέψει αμέσως
    echo json_encode([
        'success' => true,
        'media' => $mediaItems,
        'notes' => $notesItems
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}