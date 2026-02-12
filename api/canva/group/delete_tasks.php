<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$task_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Έλεγχος ότι η εργασία ανήκει στον χρήστη
try {
    $stmt = $pdo->prepare("SELECT id FROM tasks WHERE id = ? AND user_id = ?");
    $stmt->execute([$task_id, $user_id]);
    
    if (!$stmt->fetch()) {
        $_SESSION['error'] = "Η εργασία δεν βρέθηκε ή δεν έχετε δικαίωμα διαγραφής";
        header("Location: groups.php");
        exit();
    }
} catch (PDOException $e) {
    die("Σφάλμα βάσης: " . $e->getMessage());
}

// Διαγραφή εργασίας
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        // Αρχικά διαγράφουμε από τον πίνακα group_tasks
        $delete_relations = $pdo->prepare("DELETE FROM group_tasks WHERE task_id = ?");
        $delete_relations->execute([$task_id]);
        
        // Μετά διαγράφουμε την εργασία
        $delete_task = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
        $delete_task->execute([$task_id]);
        
        $_SESSION['success'] = "✅ Η εργασία διαγράφηκε επιτυχώς!";
        header("Location: groups.php");
        exit();
    } catch (PDOException $e) {
        die("Σφάλμα διαγραφής: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Διαγραφή Εργασίας</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-4">
        <h1>🗑️ Διαγραφή Εργασίας</h1>
        
        <div class="alert alert-warning">
            <p>Είστε βέβαιοι ότι θέλετε να διαγράψετε αυτήν την εργασία;</p>
            <p>Αυτή η ενέργεια δεν μπορεί να αναιρεθεί!</p>
        </div>
        
        <form method="POST">
            <button type="submit" name="confirm_delete" class="btn btn-danger">Ναι, Διαγραφή</button>
            <a href="groups.php" class="btn btn-secondary">Ακύρωση</a>
        </form>
    </div>
</body>
</html>