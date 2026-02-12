

<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

$canvaId = $_GET['canva_id'];
$currentUserId = $_GET['user_id'];

$stmt = $pdo->prepare("
    SELECT uc.user_id, u.fullname AS username, uc.x, uc.y 
    FROM user_cursors uc
    JOIN users u ON u.user_id = uc.user_id
    WHERE uc.canva_id = ? 
      AND uc.user_id != ? 
      AND uc.updated_at > (NOW() - INTERVAL 5 SECOND)
");
$stmt->execute([$canvaId, $currentUserId]);
$cursors = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['cursors' => $cursors]);
