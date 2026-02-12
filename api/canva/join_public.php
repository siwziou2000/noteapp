<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

// Έλεγχος αν ο χρήστης είναι συνδεδεμένος και αν δόθηκε ID πίνακα
if (isset($_GET['id']) && isset($_SESSION['user_id'])) {
    $canva_id = $_GET['id'];
    $user_id = $_SESSION['user_id'];

    try {
        // Χρησιμοποιούμε INSERT με ON DUPLICATE KEY UPDATE 
        // ώστε αν ο χρήστης είχε παλιά πρόσκληση που απέρριψε, να την ξανακάνει active
        $stmt = $pdo->prepare("
            INSERT INTO canvas_collaborators (canva_id, user_id, permission, status, accepted_at) 
            VALUES (?, ?, 'view', 'accepted', NOW()) 
            ON DUPLICATE KEY UPDATE status='accepted', accepted_at=NOW()
        ");
        
        if ($stmt->execute([$canva_id, $user_id])) {
            // Αυτό το session variable ενεργοποιεί το Toast στο shared_canvases.php
            $_SESSION['success'] = "Ο πίνακας προστέθηκε με επιτυχία στις συνεργασίες σας!";
        } else {
            $_SESSION['error'] = "Αποτυχία προσθήκης του πίνακα.";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Σφάλμα βάσης δεδομένων: " . $e->getMessage();
    }
} else {
    $_SESSION['error'] = "Μη εξουσιοδοτημένη πρόσβαση.";
}

// Επιστροφή στην προηγούμενη σελίδα
header('Location: ' . $_SERVER['HTTP_REFERER']);
exit;