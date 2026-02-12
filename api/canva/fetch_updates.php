<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

header('Content-Type: application/json');

// Λήψη παραμέτρων
$canva_id = isset($_GET['canva_id']) ? (int)$_GET['canva_id'] : 0;
$last_update = isset($_GET['last_update']) ? (int)$_GET['last_update'] : 0;
$current_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

$response = [
    'success' => true, // ΠΡΕΠΕΙ ΝΑ ΥΠΑΡΧΕΙ ΑΥΤΟ
    'notes' => [],
    'media' => [],
    'cursors' => []
];

try {
    // 1. Λήψη Σημειώσεων (Μετατροπή MS σε Seconds για την FROM_UNIXTIME)
    $stmt = $pdo->prepare("
        SELECT n.*, u.username as locked_by_name 
        FROM notes n
        LEFT JOIN users u ON n.locked_by = u.user_id
        WHERE n.canva_id = ?
    ");
    $stmt->execute([$canva_id]);
    $response['notes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Λήψη Media
    $stmt = $pdo->prepare("
        SELECT m.*, u.username as locked_by_name 
        FROM media m
        LEFT JOIN users u ON m.locked_by = u.user_id
        WHERE m.canva_id = ?
    ");
    $stmt->execute([$canva_id]);
    $response['media'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Λήψη Cursors
    $stmt = $pdo->prepare("
        SELECT uc.user_id, uc.x, uc.y, u.username 
        FROM user_cursors uc
        JOIN users u ON uc.user_id = u.user_id
        WHERE uc.canva_id = ? AND uc.user_id != ? 
        AND uc.last_active > NOW() - INTERVAL 10 SECOND
    ");
    $stmt->execute([$canva_id, $current_user_id]);
    $response['cursors'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = $e->getMessage();
}

echo json_encode($response);