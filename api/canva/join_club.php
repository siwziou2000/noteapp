<?php
session_start();
require_once '../../includes/database.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['canva_id'])) {
    header('Location: ../../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$canva_id = $_GET['canva_id'];

try {
    // Έλεγχος αν ο πίνακας είναι όντως δημόσιος
    $stmt = $pdo->prepare("SELECT access_type FROM canvases WHERE canva_id = ?");
    $stmt->execute([$canva_id]);
    $canvas = $stmt->fetch();

    if ($canvas && $canvas['access_type'] === 'public') {
        // Προσθήκη του χρήστη ως collaborator με status 'accepted'
        $insert = $pdo->prepare("
            INSERT INTO canvas_collaborators (canva_id, user_id, permission, status, accepted_at) 
            VALUES (?, ?, 'view', 'accepted', NOW())
            ON DUPLICATE KEY UPDATE status = 'accepted', accepted_at = NOW()
        ");
        $insert->execute([$canva_id, $user_id]);
        
        $_SESSION['success'] = "Γίνατε μέλος στον πίνακα με επιτυχία!";
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Σφάλμα κατά τη συμμετοχή.";
}

header('Location: ' . $_SERVER['HTTP_REFERER']);
exit;