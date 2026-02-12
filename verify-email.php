<?php
session_start();
require 'includes/database.php';

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    try {
        // Έλεγχος αν το token υπάρχει και δεν έχει λήξει
        $query = $pdo->prepare("SELECT ev.*, u.user_id, u.email 
                               FROM email_verifications ev 
                               JOIN users u ON ev.user_id = u.user_id 
                               WHERE ev.token = :token AND ev.expires_at >= NOW()");
        $query->execute(['token' => $token]);

        if ($query->rowCount() > 0) {
            $verification = $query->fetch(PDO::FETCH_ASSOC);
            $user_id = $verification['user_id'];
            $email = $verification['email'];

            // Έλεγχος αν είναι ήδη verified
            $checkStmt = $pdo->prepare("SELECT email_verified FROM users WHERE user_id = :user_id");
            $checkStmt->execute(['user_id' => $user_id]);
            $user = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($user && $user['email_verified'] == 1) {
                $_SESSION['message'] = "Το email σας είναι ήδη επιβεβαιωμένο! Μπορείτε να συνδεθείτε.";
            } else {
                // Ενημέρωση χρήστη ως επιβεβαιωμένου
                $updateUser = $pdo->prepare("UPDATE users SET is_verified = 1, email_verified = 1 WHERE user_id = :user_id");
                $updateUser->execute(['user_id' => $user_id]);

                // Διαγραφή του token από τη βάση
                $deleteToken = $pdo->prepare("DELETE FROM email_verifications WHERE token = :token");
                $deleteToken->execute(['token' => $token]);

                $_SESSION['message'] = "✅ Το email σας έχει επιβεβαιωθεί με επιτυχία! Μπορείτε τώρα να συνδεθείτε.";
            }
            
            header("Location: login.php");
            exit;
        } else {
            $_SESSION['error'] = "❌ Ο σύνδεσμος δεν είναι έγκυρος ή έχει λήξει.";
            header("Location: login.php");
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Σφάλμα: " . $e->getMessage();
        header("Location: login.php");
        exit;
    }
} else {
    $_SESSION['error'] = "Μη έγκυρο αίτημα.";
    header("Location: login.php");
    exit;
}
?>