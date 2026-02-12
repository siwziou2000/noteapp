<?php
// viewcanvas.php


session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

// Έλεγχος ύπαρξης link
$link = $_GET['link'] ?? '';
if (!$link) die("Μη έγκυρος σύνδεσμος.");

try {
    // Φόρτωση καμβά
    $stmt = $pdo->prepare("
        SELECT 
            c.canva_id, 
            c.name, 
            c.unique_canva_id, 
            c.access_type, 
            c.user_id, 
            c.created_at, 
            c.canva_category,
            u.username
        FROM canvases c
        JOIN users u ON c.user_id = u.user_id
        WHERE c.unique_canva_id = ?
    ");
    $stmt->execute([$link]);
    $canvas = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Σφάλμα βάσης δεδομένων: " . $e->getMessage());
}

if (!$canvas) die("Ο καμβάς δεν βρέθηκε.");

// Έλεγχος πρόσβασης
if ($canvas['access_type'] === 'private') {
    if (!isset($_SESSION['user_id'])) die("Απαιτείται σύνδεση.");
    $user_id = $_SESSION['user_id'];

    if ($user_id !== $canvas['user_id']) {
        $stmt = $pdo->prepare("SELECT * FROM shared_canvases WHERE canva_id = ? AND recipient_id = ?");
        $stmt->execute([$canvas['canva_id'], $user_id]);
        if (!$stmt->fetch()) die("Δεν έχετε δικαίωμα πρόσβασης.");
    }
}

// Φόρτωση σημειώσεων
$stmt = $pdo->prepare("SELECT * FROM notes WHERE canva_id = ? ORDER BY created_at DESC");
$stmt->execute([$canvas['canva_id']]);
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($canvas['name'] ?? 'Καμβάς') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body>
<div class="container mt-5">
    <h1><?= htmlspecialchars($canvas['name']) ?></h1>

    <div class="card mt-4">
        <div class="card-body">
            <h5 class="card-title">Πληροφορίες Καμβά</h5>
            <ul class="list-group list-group-flush">
                <li class="list-group-item">Δημιουργός: <?= htmlspecialchars($canvas['username']) ?></li>
                <li class="list-group-item">Κατηγορία: <?= htmlspecialchars($canvas['canva_category'] ?? '-') ?></li>
                <li class="list-group-item">Ημερομηνία: <?= date('d/m/Y H:i', strtotime($canvas['created_at'])) ?></li>
                <li class="list-group-item">Κατάσταση: <?= $canvas['access_type'] === 'public' ? 'Δημόσιος' : 'Ιδιωτικός' ?></li>
            </ul>
        </div>
    </div>

    <h4 class="mt-4">Σημειώσεις</h4>

    <?php if (count($notes) > 0): ?>
        <div class="row">
            <?php foreach ($notes as $note): ?>
                <div class="col-md-4">
                    <div 
                        class="card mb-3" 
                        style="
                            background-color: <?= htmlspecialchars($note['background_color'] ?? '#f8f9fa') ?>;
                            color: <?= htmlspecialchars($note['font_color'] ?? '#000') ?>;
                            font-family: <?= htmlspecialchars($note['font'] ?? 'inherit') ?>;
                            font-size: <?= intval($note['font_size'] ?? 14) ?>px;
                            height: <?= intval($note['height'] ?? 200) ?>px;
                            width: <?= intval($note['width'] ?? 300) ?>px;
                        "
                    >
                        <div class="card-body">
                            <?php if (!empty($note['icon'])): ?>
                                <div class="mb-2">
                                    <i class="bi bi-<?= htmlspecialchars($note['icon']) ?>"></i>
                                </div>
                            <?php endif; ?>
                            <p class="card-text"><?= nl2br(htmlspecialchars($note['content'])) ?></p>
                            <small class="text-muted">
                                Δημιουργήθηκε: <?= date('d/m/Y H:i', strtotime($note['created_at'])) ?><br>
                                Κατάσταση: <?= htmlspecialchars($note['status'] ?? 'draft') ?><br>
                                Τύπος: <?= htmlspecialchars($note['note_type'] ?? 'text') ?>
                            </small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="text-muted">Δεν υπάρχουν ακόμη σημειώσεις για αυτόν τον καμβά.</p>
    <?php endif; ?>


     <div class="container py-4">
        <div class="card">
            <p> Το περιεχομενο του καμβα βρισκεται εδω
                <a href="board.php?id=<?= $canvas['canva_id'] ?>&name=<?= urlencode($canvas['name']) ?>" class="btn btn-primary btn-sm"> ΠΡΟΒΟΛΗ ΚΑΜΒΑ 
            
    </a>
    </div>
</div>
    <a href="canvas.php" class="btn btn-primary mt-4">
        <i class="bi bi-arrow-left"></i> Επιστροφή
    </a>
</div>
</body>
</html>
