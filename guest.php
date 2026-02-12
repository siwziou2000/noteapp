<?php  

require 'includes/database.php';

// Έλεγχος ρόλου χρήστη
if ($_SESSION['role'] === 'guest') {
    // Αν είναι guest, ανακατεύθυνση στη σελίδα χωρίς πρόσβαση
    header("Location: no-access.php");
    exit;
}

// Έλεγχος αν ο χρήστης είναι συνδεδεμένος
if (!isset($_SESSION['user_id'])) {
    // Αν δεν είναι συνδεδεμένος, ανακατεύθυνση στη σελίδα σύνδεσης
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
try {
    // Ανάκτηση στοιχείων χρήστη από τη βάση δεδομένων
    $sql = "SELECT * FROM users WHERE user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Αν δεν βρέθηκε ο χρήστης, τερματισμός της συνεδρίας και ανακατεύθυνση στη σύνδεση
        session_destroy();
        header("Location: login.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    die("Σφάλμα στη βάση δεδομένων. Παρακαλώ δοκιμάστε ξανά αργότερα.");
}

?>
