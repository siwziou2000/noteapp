<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

if (isset($_GET['id']) && isset($_SESSION['user_id'])) {
    $canva_id = $_GET['id'];
    $user_id = $_SESSION['user_id'];

    try {
        // Διαγραφή της εγγραφής συνεργασίας για τον συγκεκριμένο χρήστη και πίνακα
        $stmt = $pdo->prepare("DELETE FROM canvas_collaborators WHERE canva_id = ? AND user_id = ?");
        
        if ($stmt->execute([$canva_id, $user_id])) {
            $_SESSION['success'] = "Αποχωρήσατε από τον πίνακα με επιτυχία.";
        } else {
            $_SESSION['error'] = "Αποτυχία αποχώρησης από τον πίνακα.";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Σφάλμα βάσης δεδομένων: " . $e->getMessage();
    }
} else {
    $_SESSION['error'] = "Μη εξουσιοδοτημένη πρόσβαση.";
}

// Επιστροφή στη σελίδα των κοινόχρηστων πινάκων
header('Location: /noteapp/api/canva/shared_canvases.php'); 
exit;