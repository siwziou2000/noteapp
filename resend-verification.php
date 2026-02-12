<?php
session_start();
require 'includes/database.php';

if (isset($_GET['email'])) {
    $email = filter_var($_GET['email'], FILTER_SANITIZE_EMAIL);

    try {
        // 1. Ελέγχουμε αν ο χρήστης υπάρχει και αν είναι ήδη verified
        $stmt = $pdo->prepare("SELECT user_id, email_verified FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            $_SESSION['error'] = "Δεν βρέθηκε λογαριασμός με αυτό το email.";
            header("Location: login.php");
            exit;
        }

        if ($user['email_verified'] == 1) {
            $_SESSION['message'] = "Ο λογαριασμός σας είναι ήδη επαληθευμένος. Μπορείτε να συνδεθείτε.";
            header("Location: login.php");
            exit;
        }

        // 2. Αναζητούμε αν υπάρχει ήδη ένα έγκυρο token στον πίνακα email_verifications
        $stmt = $pdo->prepare("SELECT token FROM email_verifications WHERE email = :email AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
        $stmt->execute(['email' => $email]);
        $verification = $stmt->fetch();

        if ($verification) {
            // Αν υπάρχει ήδη, παίρνουμε το υπάρχον
            $token = $verification['token'];
        } else {
            // Αν δεν υπάρχει ή έληξε, δημιουργούμε ένα νέο
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

            $stmt = $pdo->prepare("INSERT INTO email_verifications (user_id, email, token, expires_at) VALUES (:user_id, :email, :token, :expires_at)");
            $stmt->execute([
                'user_id' => $user['user_id'],
                'email' => $email,
                'token' => $token,
                'expires_at' => $expires_at
            ]);
        }

        // 3. Αποθηκεύουμε τα στοιχεία στη session για να τα δει το show-verification-link.php
        $_SESSION['new_user_token'] = $token;
        $_SESSION['new_user_email'] = $email;
        $_SESSION['new_user_id'] = $user['user_id'];

        header("Location: show-verification-link.php");
        exit;

    } catch (PDOException $e) {
        $_SESSION['error'] = "Σφάλμα συστήματος: " . $e->getMessage();
        header("Location: login.php");
        exit;
    }
} else {
    header("Location: login.php");
    exit;
}