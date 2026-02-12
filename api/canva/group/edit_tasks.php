<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$task_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Ανάκτηση πληροφοριών εργασίας
try {
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? AND user_id = ?");
    $stmt->execute([$task_id, $user_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$task) {
        $_SESSION['error'] = "Η εργασία δεν βρέθηκε ή δεν έχετε δικαίωμα επεξεργασίας";
        header("Location: groups.php");
        exit();
    }
} catch (PDOException $e) {
    die("Σφάλμα βάσης: " . $e->getMessage());
}

// Επεξεργασία εργασίας
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_task'])) {
    try {
        $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $due_date = filter_input(INPUT_POST, 'due_date');
        $priority = filter_input(INPUT_POST, 'priority', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        
        $update_stmt = $pdo->prepare("
            UPDATE tasks 
            SET title = ?, description = ?, due_date = ?, priority = ?, updated_at = NOW()
            WHERE id = ? AND user_id = ?
        ");
        $update_stmt->execute([$title, $description, $due_date, $priority, $task_id, $user_id]);
        
        $_SESSION['success'] = "✅ Η εργασία ενημερώθηκε επιτυχώς!";
        header("Location: groups.php");
        exit();
    } catch (PDOException $e) {
        die("Σφάλμα ενημέρωσης: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Επεξεργασία Εργασίας</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-4">
        <h1>✏️ Επεξεργασία Εργασίας</h1>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-3">
                <label for="title" class="form-label">Τίτλος:</label>
                <input type="text" class="form-control" id="title" name="title" 
                       value="<?= htmlspecialchars($task['title']) ?>" required>
            </div>
            
            <div class="mb-3">
                <label for="description" class="form-label">Περιγραφή:</label>
                <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($task['description']) ?></textarea>
            </div>
            
            <div class="mb-3">
                <label for="due_date" class="form-label">Προθεσμία:</label>
                <input type="date" class="form-control" id="due_date" name="due_date" 
                       value="<?= $task['due_date'] ? htmlspecialchars($task['due_date']) : '' ?>">
            </div>
            
            <div class="mb-3">
                <label for="priority" class="form-label">Προτεραιότητα:</label>
                <select class="form-select" id="priority" name="priority" required>
                    <option value="Low" <?= $task['priority'] === 'Low' ? 'selected' : '' ?>>Χαμηλή</option>
                    <option value="Medium" <?= $task['priority'] === 'Medium' ? 'selected' : '' ?>>Μέτρια</option>
                    <option value="High" <?= $task['priority'] === 'High' ? 'selected' : '' ?>>Υψηλή</option>
                </select>
            </div>
            
            <button type="submit" name="update_task" class="btn btn-primary">Αποθήκευση</button>
            <a href="groups.php" class="btn btn-secondary">Ακύρωση</a>
        </form>
    </div>
</body>
</html>