<?php
// api/checknewinvitations.php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

// Set JSON header FIRST
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$last_check = $_GET['last_check'] ?? date('Y-m-d H:i:s', strtotime('-1 hour'));

// Validate last_check format
if (!DateTime::createFromFormat('Y-m-d H:i:s', $last_check)) {
    $last_check = date('Y-m-d H:i:s', strtotime('-1 hour'));
}

try {
    // Έλεγχος για νέες PENDING προσκλήσεις - ΧΡΗΣΗ PREPARED STATEMENT
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as new_count 
        FROM canvas_collaborators 
        WHERE user_id = ? 
        AND status = 'pending'
        AND invited_at > ?
    ");
    $stmt->execute([$user_id, $last_check]);
    $result = $stmt->fetch();
    
    // Έλεγχος για νέους δημόσιους πίνακες - ΧΡΗΣΗ PREPARED STATEMENT
    $stmt_public = $pdo->prepare("
        SELECT COUNT(DISTINCT c.canva_id) as public_count 
        FROM canvases c 
        WHERE c.access_type = 'public' 
        AND c.created_at > ?
        AND c.owner_id != ?
        AND c.canva_id NOT IN (
            SELECT canva_id FROM canvas_collaborators WHERE user_id = ? AND status = 'accepted'
        )
        AND c.canva_id NOT IN (
            SELECT canva_id FROM canvas_collaborators WHERE user_id = ? AND status = 'pending'
        )
    ");
    $stmt_public->execute([$last_check, $user_id, $user_id, $user_id]);
    $public_result = $stmt_public->fetch();
    
    $total_new = $result['new_count'] + $public_result['public_count'];
    
    // Success response
    echo json_encode([
        'has_new_invitations' => $total_new > 0,
        'new_count' => $total_new,
        'collaboration_invitations' => (int)$result['new_count'],
        'new_public_canvases' => (int)$public_result['public_count'],
        'last_check' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in checknewinvitations: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>