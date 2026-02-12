<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

header('Content-Type: application/json');

// 1. ΛΗΨΗ ΠΑΡΑΜΕΤΡΩΝ & ΕΛΕΓΧΟΣ ADMIN
$canva_id = isset($_GET['canva_id']) ? (int)$_GET['canva_id'] : 0;
$last_update = isset($_GET['last_update']) ? (int)$_GET['last_update'] : 0;
$current_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Έλεγχος αν ο χρήστης είναι Admin (από Session και URL)
$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') && 
           (isset($_GET['admin']) && $_GET['admin'] == '1');

if ($canva_id <= 0) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid canvas ID']));
}

try {
    // 2. ΕΛΕΓΧΟΣ ΠΡΟΣΒΑΣΗΣ (Ο Admin περνάει ελεύθερα)
    if (!$isAdmin) {
        $accessStmt = $pdo->prepare("
            SELECT canva_id FROM canvases 
            WHERE canva_id = ? 
            AND (owner_id = ? OR access_type IN ('public', 'shared', 'δημόσιο') 
            OR canva_id IN (SELECT canva_id FROM canvas_collaborators WHERE user_id = ?))
        ");
        $accessStmt->execute([$canva_id, $current_user_id, $current_user_id]);
        if (!$accessStmt->fetch()) {
            http_response_code(403);
            die(json_encode(['error' => 'Δεν έχετε δικαίωμα πρόσβασης']));
        }
    }

    // 3. ΛΗΨΗ ΝΕΩΝ/ΕΝΗΜΕΡΩΜΕΝΩΝ ΠΟΛΥΜΕΣΩΝ
    $stmt = $pdo->prepare("
        SELECT m.*, u.username as locked_by_name 
        FROM media m
        LEFT JOIN users u ON m.locked_by = u.user_id
        WHERE m.canva_id = ? 
        AND m.updated_at > FROM_UNIXTIME(?)
      
        AND m.type IS NOT NULL
    ");
    $stmt->execute([$canva_id, $last_update]);
    $media = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. ΣΥΓΧΡΟΝΙΣΜΟΣ ΔΙΑΓΡΑΦΩΝ (Delete Sync)
    // Στέλνουμε όλα τα ID που υπάρχουν ΤΩΡΑ στη βάση για αυτόν τον καμβά
    $activeIdsStmt = $pdo->prepare("SELECT id FROM media WHERE canva_id = ?");
    $activeIdsStmt->execute([$canva_id]);
    $active_ids = $activeIdsStmt->fetchAll(PDO::FETCH_COLUMN);

    // 5. ΛΗΨΗ CURSORS (Live κινήσεις άλλων χρηστών)
    $stmt = $pdo->prepare("
        SELECT uc.user_id, uc.x, uc.y, u.username 
        FROM user_cursors uc
        JOIN users u ON uc.user_id = u.user_id
        WHERE uc.canva_id = ? 
        AND uc.last_active > NOW() - INTERVAL 5 SECOND
        AND uc.user_id != ?
    ");
    $stmt->execute([$canva_id, $current_user_id]);
    $cursors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Απάντηση
    echo json_encode([
        'success' => true,
        'media' => $media,
        'active_ids' => $active_ids, // Η JS θα σβήνει ό,τι ID λείπει από εδώ
        'cursors' => $cursors,
        'timestamp' => time()
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}