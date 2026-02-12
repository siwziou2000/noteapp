<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';


if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$group_id = filter_input(INPUT_GET, 'group_id', FILTER_VALIDATE_INT);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
    try {
        $task_id = filter_input(INPUT_POST, 'task_id', FILTER_VALIDATE_INT);
        
        // Έλεγχος ότι η εργασία ανήκει στον χρήστη
        $check_stmt = $pdo->prepare("SELECT id FROM tasks WHERE id = ? AND user_id = ?");
        $check_stmt->execute([$task_id, $user_id]);
        
        if ($check_stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO group_tasks (group_id, task_id) VALUES (?, ?)");
            $stmt->execute([$group_id, $task_id]);
            
            $_SESSION['success'] = "✅ Η εργασία προστέθηκε στην ομάδα!";
            header("Location: groups.php");
            exit();
        } else {
            $_SESSION['error'] = "🚨 Η εργασία δεν βρέθηκε ή δεν ανήκει σε εσάς";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "🚨 Σφάλμα: " . $e->getMessage();
    }
}

// Ανάκτηση διαθέσιμων εργασιών του χρήστη
try {
    $tasks_stmt = $pdo->prepare("
        SELECT t.id, t.title 
        FROM tasks t
        WHERE t.user_id = ? 
        AND t.id NOT IN (
            SELECT gt.task_id 
            FROM group_tasks gt 
            WHERE gt.group_id = ?
        )
        ORDER BY t.created_at DESC
    ");
    $tasks_stmt->execute([$user_id, $group_id]);
    $available_tasks = $tasks_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Σφάλμα ανάκτησης εργασιών: " . $e->getMessage());
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/api/canva/include/menu.php';
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Προσθήκη Εργασίας σε Ομάδα</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-4">
        <h1>➕ Προσθήκη Εργασίας σε Ομάδα</h1>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-3">
                <label for="task_id" class="form-label">Επιλέξτε Εργασία:</label>
                <select class="form-select" id="task_id" name="task_id" required>
                    <option value="">-- Επιλέξτε εργασία --</option>
                    <?php foreach ($available_tasks as $task): ?>
                        <option value="<?= $task['id'] ?>"><?= htmlspecialchars($task['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" name="add_task" class="btn btn-primary">Προσθήκη</button>
            <a href="groups.php" class="btn btn-secondary">Ακύρωση</a>
        </form>
    </div>
</body>
</html>