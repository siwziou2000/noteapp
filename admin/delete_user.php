<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php');

// Έλεγχος πρόσβασης - μόνο για διαχειριστές
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /noteapp/login.php");
    exit;
}

// CSRF Protection
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = "Invalid CSRF token";
    header("Location: users.php");
    exit;
}

// Έλεγχος αν παρέχεται ID χρήστη
if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    $_SESSION['error'] = "Invalid user ID";
    header("Location: users.php");
    exit;
}

$user_id = (int)$_POST['id'];

// Αποτροπή διαγραφής του εαυτού του διαχειριστή
if ($user_id === $_SESSION['user_id']) {
    $_SESSION['error'] = "Δεν μπορείτε να διαγράψετε τον ίδιο σας τον λογαριασμό";
    header("Location: users.php");
    exit;
}

try {
    // Αρχικά ελέγχουμε αν υπάρχει ο χρήστης
    $check_sql = "SELECT user_id, username FROM users WHERE user_id = :user_id";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([':user_id' => $user_id]);
    $user = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $_SESSION['error'] = "Ο χρήστης δεν βρέθηκε";
        header("Location: users.php");
        exit;
    }
    
    // Διαγραφή σημειώσεων του χρήστη (αν υπάρχουν foreign keys)
    $delete_notes_sql = "DELETE FROM notes WHERE user_id = :user_id";
    $notes_stmt = $pdo->prepare($delete_notes_sql);
    $notes_stmt->execute([':user_id' => $user_id]);
    
    // Διαγραφή καμβών του χρήστη (αν υπάρχουν foreign keys)
    $delete_canvases_sql = "DELETE FROM canvases WHERE user_id = :user_id";
    $canvases_stmt = $pdo->prepare($delete_canvases_sql);
    $canvases_stmt->execute([':user_id' => $user_id]);
    
    // Τελική διαγραφή του χρήστη
    $delete_user_sql = "DELETE FROM users WHERE user_id = :user_id";
    $delete_stmt = $pdo->prepare($delete_user_sql);
    $delete_stmt->execute([':user_id' => $user_id]);
    
    // Καταγραφή της ενέργειας (προαιρετικά)
    $log_sql = "INSERT INTO admin_logs (admin_id, action, target_user_id, timestamp) 
                VALUES (:admin_id, :action, :target_user_id, NOW())";
    $log_stmt = $pdo->prepare($log_sql);
    $log_stmt->execute([
        ':admin_id' => $_SESSION['user_id'],
        ':action' => 'delete_user',
        ':target_user_id' => $user_id
    ]);
    
    $_SESSION['success'] = "Ο χρήστης " . htmlspecialchars($user['username']) . " διαγράφηκε επιτυχώς";
    
} catch (PDOException $e) {
    error_log("Delete User Error: " . $e->getMessage());
    $_SESSION['error'] = "Προέκυψε σφάλμα κατά τη διαγραφή του χρήστη: " . $e->getMessage();
} catch (Exception $e) {
    error_log("General Error: " . $e->getMessage());
    $_SESSION['error'] = "Προέκυψε σφάλμα: " . $e->getMessage();
}

// Ανανέωση token για ασφάλεια
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Επιστροφή στη σελίδα χρηστών
header("Location: users.php");
exit;
?>