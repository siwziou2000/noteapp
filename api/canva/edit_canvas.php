<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$canva_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Retrieve canvas data
$stmt = $pdo->prepare("SELECT * FROM canvases WHERE canva_id = ? AND user_id = ?");
$stmt->execute([$canva_id, $user_id]);
$canvas = $stmt->fetch();

if (!$canvas) {
    die("Δεν έχετε δικαίωμα επεξεργασίας.");
}

// Process POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS);
    $access_type = $_POST['access_type'] ?? 'private';
    $canva_category = $_POST['canva_category'] ?? 'educational';
    $group_id = $_POST['group_id'] ?? 0;

    $stmt = $pdo->prepare("
        UPDATE canvases 
        SET 
            name = ?, 
            access_type = ?, 
            canva_category = ?,
            copy_from_group_id = ?
        WHERE canva_id = ?
    ");
    $stmt->execute([$name, $access_type, $canva_category, $group_id, $canva_id]);
    
    $_SESSION['success'] = "Ο καμβάς ενημερώθηκε!";
    header("Location: canvas.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Επεξεργασία Καμβά</title>
</head>
<body>
    <div class="container">
        <h2>Επεξεργασία Καμβά: <?= htmlspecialchars($canvas['name']) ?></h2>
        <form method="POST">
            <div class="mb-3">
                <label>Όνομα:</label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($canvas['name']) ?>" required>
            </div>
            <div class="mb-3">
                <label>Συσχέτιση με Ομάδα:</label>
                <select name="group_id" class="form-select">
                    <option value="0">-- Χωρίς ομάδα --</option>
                    <?php 
                    $groups_stmt = $pdo->prepare("SELECT * FROM groups WHERE user_id = ?");
                    $groups_stmt->execute([$user_id]);
                    while($group = $groups_stmt->fetch()):
                    ?>
                        <option value="<?= $group['group_id'] ?>" 
                            <?= ($canvas['copy_from_group_id'] == $group['group_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($group['group_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="mb-5">
                <label>Τύπος Πρόσβασης:</label>
                <select name="access_type" class="form-select">
                    <option value="private" <?= ($canvas['access_type'] == 'private') ? 'selected' : '' ?>>Ιδιωτικό</option>
                    <option value="public" <?= ($canvas['access_type'] == 'public') ? 'selected' : '' ?>>Δημόσιο</option>
                    <option value="shared" <?= ($canvas['access_type'] == 'shared') ? 'selected' : '' ?>>Κοινοχρηστο</option>
                    </select>
            </div> 
            <div class="mb-5">
                <label>Κατηγορία:</label>
                <select name="canva_category" class="form-select">
                    <option value="educational" <?= ($canvas['canva_category'] == 'educational') ? 'selected' : '' ?>>Εκπαίδευση</option>
                    <option value="marketing" <?= ($canvas['canva_category'] == 'marketing') ? 'selected' : '' ?>>Μαρκετινγκ</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Αποθήκευση</button>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.min.js"></script>
</body>
</html>