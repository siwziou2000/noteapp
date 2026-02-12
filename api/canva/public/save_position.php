<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $type = $_POST['type'];
    $x = (int)$_POST['position_x'];
    $y = (int)$_POST['position_y'];

    try {
        if ($type === 'note') {
            $stmt = $pdo->prepare("UPDATE notes SET position_x = ?, position_y = ? WHERE note_id = ?");
        } else {
            $stmt = $pdo->prepare("UPDATE media SET position_x = ?, position_y = ? WHERE id = ?");
        }
        
        $success = $stmt->execute([$x, $y, $id]);
        echo json_encode(['success' => $success]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}