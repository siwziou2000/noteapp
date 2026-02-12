<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$task_id = filter_input(INPUT_GET, 'task_id', FILTER_VALIDATE_INT);
$group_id = filter_input(INPUT_GET, 'group_id', FILTER_VALIDATE_INT);

// Έλεγχος ότι ο χρήστης είναι διαχειριστής της ομάδας
try {
    $check_stmt = $pdo->prepare("
        SELECT gm.role 
        FROM group_members gm
        WHERE gm.user_id = ? AND gm.group_id = ?
    ");
    $check_stmt->execute([$user_id, $group_id]);
    $membership = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$membership || $membership['role'] !== 'admin') {
        $_SESSION['error'] = "Δεν έχετε δικαίωμα να αφαιρέσετε εργασίες από αυτήν την ομάδα";
        header("Location: groups.php");
        exit();
    }
} catch (PDOException $e) {
    die("Σφάλμα βάσης: " . $e->getMessage());
}

// Αφαίρεση εργασίας από ομάδα
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_remove'])) {
    try {
        $delete_stmt = $pdo->prepare("DELETE FROM group_tasks WHERE task_id = ? AND group_id = ?");
        $delete_stmt->execute([$task_id, $group_id]);
        
        $_SESSION['success'] = "✅ Η εργασία αφαιρέθηκε από την ομάδα!";
        header("Location: group.php?id=$group_id");
        exit();
    } catch (PDOException $e) {
        die("Σφάλμα αφαίρεσης: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Αφαίρεση Εργασίας από Ομάδα</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-4">
        <h1> Αφαίρεση Εργασίας από Ομάδα</h1>
        
        <div class="alert alert-warning">
            <p>Είστε βέβαιοι ότι θέλετε να αφαιρέσετε αυτήν την εργασία από την ομάδα;</p>
            <p>Η εργασία θα παραμείνει στο σύστημα, απλώς θα αφαιρεθεί από αυτήν την ομάδα.</p>
        </div>
        
        <form method="POST">
            <button type="submit" name="confirm_remove" class="btn btn-warning">Ναι, Αφαίρεση</button>
            <a href="group.php?id=<?= $group_id ?>" class="btn btn-secondary">Ακύρωση</a>
        </form>
    </div>
</body>
</html>