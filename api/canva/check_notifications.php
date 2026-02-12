<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php');

$user_id = $_SESSION['user_id'];

// Φέρνουμε σημειώσεις που έχουν λήξει ή λήγουν σε λιγότερο από 24 ώρες
$stmt = $pdo->prepare("
    SELECT note_id, content, due_date, 
           DATEDIFF(due_date, NOW()) as days_left
    FROM notes 
    WHERE user_id = ? AND due_date IS NOT NULL 
    AND due_date <= DATE_ADD(NOW(), INTERVAL 1 DAY)
    ORDER BY due_date ASC
");
$stmt->execute([$user_id]);
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($notes);