<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /noteapp/login.php");
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = "Invalid CSRF token";
    header("Location: users.php");
    exit;
}

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
    
    // ΕΝΑΡΞΗ TRANSACTION για atomic operations
    $pdo->beginTransaction();
    
    // Λίστα με όλους τους πίνακες που έχουν σχέση με τον χρήστη
    $related_tables = [
        'notes' => 'user_id',
        'canvases' => 'user_id',
        'user_sessions' => 'user_id',
        'login_attempts' => 'user_id',
        'password_resets' => 'user_id',
        'email_verifications' => 'user_id',
        'admin_logs' => 'target_user_id'
    ];
    
    // Διαγραφή από όλους τους συσχετισμένους πίνακες
    foreach ($related_tables as $table => $column) {
        try {
            $delete_sql = "DELETE FROM {$table} WHERE {$column} = :user_id";
            $delete_stmt = $pdo->prepare($delete_sql);
            $delete_stmt->execute([':user_id' => $user_id]);
        } catch (PDOException $e) {
            // Αγνόησε πίνακες που μπορεί να μην υπάρχουν
            error_log("Note: Table {$table} may not exist: " . $e->getMessage());
        }
    }
    
    // Τελική διαγραφή του χρήστη
    $delete_user_sql = "DELETE FROM users WHERE user_id = :user_id";
    $delete_stmt = $pdo->prepare($delete_user_sql);
    $delete_result = $delete_stmt->execute([':user_id' => $user_id]);
    
    if ($delete_result && $delete_stmt->rowCount() > 0) {
        // Καταγραφή της ενέργειας
        try {
            $log_sql = "INSERT INTO admin_logs (admin_id, action, target_user_id, details, timestamp) 
                       VALUES (:admin_id, :action, :target_user_id, :details, NOW())";
            $log_stmt = $pdo->prepare($log_sql);
            $log_stmt->execute([
                ':admin_id' => $_SESSION['user_id'],
                ':action' => 'delete_user',
                ':target_user_id' => $user_id,
                ':details' => "Deleted user: " . $user['username']
            ]);
        } catch (PDOException $e) {
            error_log("Failed to log action: " . $e->getMessage());
        }
        
        $pdo->commit();
        $_SESSION['success'] = "Ο χρήστης " . htmlspecialchars($user['username']) . " διαγράφηκε επιτυχώς";
    } else {
        $pdo->rollBack();
        $_SESSION['error'] = "Αποτυχία διαγραφής χρήστη. Δεν επηρεάστηκαν εγγραφές.";
    }
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Ανάλυση του σφάλματος για πιο κατανοητό μήνυμα
    $error_message = $e->getMessage();
    
    if (strpos($error_message, 'foreign key constraint') !== false) {
        $_SESSION['error'] = "Δεν είναι δυνατή η διαγραφή λόγω εξαρτήσεων (Foreign Key). 
                            Παρακαλώ πρώτα διαγράψτε όλες τις σημειώσεις και καμβάδες του χρήστη.";
    } else {
        $_SESSION['error'] = "Σφάλμα βάσης δεδομένων: " . htmlspecialchars($error_message);
    }
    
    error_log("Delete User Error: " . $error_message);
}

// Ανανέωση CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

header("Location: users.php");
exit;
?>