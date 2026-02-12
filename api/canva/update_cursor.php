<?php




require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';




$data = json_decode(file_get_contents("php://input"), true);

$userId = $data['user_id'];
$canvaId = $data['canva_id'];
$x = $data['x'];
$y = $data['y'];

$stmt = $pdo->prepare("INSERT INTO user_cursors (user_id, canva_id, x, y)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE x = VALUES(x), y = VALUES(y), updated_at = NOW()");
$ok = $stmt->execute([$userId, $canvaId, $x, $y]);

echo json_encode(['success' => $ok]);
