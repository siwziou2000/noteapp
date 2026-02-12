<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

// CSRF Check
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die(json_encode(['error' => 'Invalid CSRF token']));
}

$mediaId = (int)$_POST['id'];
$x = (int)$_POST['position_x'];
$y = (int)$_POST['position_y'];

// Ενημέρωση θέσης ΧΩΡΊΣ έλεγχο κλειδώματος
$stmt = $pdo->prepare("UPDATE media SET position_x = ?, position_y = ? WHERE id = ?");
$stmt->execute([$x, $y, $mediaId]);

echo json_encode(['success' => true]);
?>