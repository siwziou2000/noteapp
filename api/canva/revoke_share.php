<?php
session_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        throw new Exception("Μη έγκυρη αίτηση");
    }

    // Λήψη και επικύρωση δεδομένων
    $canva_id = isset($_POST['canva_id']) ? (int)$_POST['canva_id'] : 0;
    $recipient_email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

    // Έλεγχος ιδιοκτησίας καμβά
    $stmt = $pdo->prepare("SELECT * FROM canvases WHERE canva_id = ? AND user_id = ?");
    $stmt->execute([$canva_id, $user_id]);
    $canvas = $stmt->fetch();

    if (!$canvas) {
        throw new Exception("Δεν έχετε δικαίωμα πρόσβασης");
    }

    // Εύρεση ID παραλήπτη
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$recipient_email]);
    $recipient = $stmt->fetch();

    if (!$recipient) {
        throw new Exception("Δεν βρέθηκε ο χρήστης");
    }

    // Διαγραφή κοινής χρήσης
    $stmt = $pdo->prepare("DELETE FROM shared_canvases 
                          WHERE canva_id = ? AND recipient_id = ?");
    $stmt->execute([$canva_id, $recipient['user_id']]);

    $_SESSION['success'] = "Ανακληθηκε η πρόσβαση για " . htmlspecialchars($recipient_email);

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}

// Ανακατεύθυνση πίσω
header("Location: share_canvas.php?canva_id=" . $canva_id);
exit();
?>