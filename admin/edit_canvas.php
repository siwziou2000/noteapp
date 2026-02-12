<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php');

// Έλεγχος εάν έχει σταλεί ID
$canvas_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($canvas_id <= 0) {
    die("Μη έγκυρο canvas ID.");
}

// Αν γίνει υποβολή φόρμας
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $category = $_POST['canva_category'] ?? '';
    $access_type = $_POST['access_type'] ?? '';

    $stmt = $pdo->prepare("UPDATE canvases SET title = :title, description = :description, canva_category = :category, access_type = :access_type, updated_at = NOW() WHERE canva_id = :id");
    $stmt->execute([
        'title' => $title,
        'description' => $description,
        'category' => $category,
        'access_type' => $access_type,
        'id' => $canvas_id
    ]);

    header("Location: view_canvases_users.php?id=" . $_POST['user_id']);
    exit;
}

// Φέρνουμε το canvas για επεξεργασία
$stmt = $pdo->prepare("SELECT * FROM canvases WHERE canva_id = :id");
$stmt->execute(['id' => $canvas_id]);
$canvas = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$canvas) {
    die("Ο πίνακας δεν βρέθηκε.");
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Επεξεργασία Πίνακα</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
<div class="container">
    <h2>Επεξεργασία Πίνακα: <?= htmlspecialchars($canvas['title']) ?></h2>
    <form method="POST">
        <input type="hidden" name="user_id" value="<?= htmlspecialchars($canvas['user_id']) ?>">
        
        <div class="mb-3">
            <label class="form-label">Κατηγορία</label>
            <input type="text" name="canva_category" class="form-control" value="<?= htmlspecialchars($canvas['canva_category']) ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Πρόσβαση</label>
            <select name="access_type" class="form-select">
                <option value="private" <?= $canvas['access_type'] === 'private' ? 'selected' : '' ?>>Ιδιωτικό</option>
                <option value="shared" <?= $canvas['access_type'] === 'shared' ? 'selected' : '' ?>>Κοινόχρηστο</option>
                <option value="public" <?= $canvas['access_type'] === 'public' ? 'selected' : '' ?>>Δημόσιο</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">💾 Αποθήκευση</button>
        <a href="view_canvases_users.php?id=<?= $canvas['user_id'] ?>" class="btn btn-secondary">🔙 Πίσω</a>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.min.js" ></script>
</body>
</html>
