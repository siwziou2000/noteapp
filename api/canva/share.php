<?php
// Σελίδα κοινής προβολής μέσω ειδικού συνδέσμου (token)
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

// Έλεγχος σύνδεσης βάσης
if (!isset($pdo)) {
    die("<div class='alert alert-danger'>Σφάλμα σύνδεσης με τη βάση δεδομένων.</div>");
}

$token = $_GET['token'] ?? '';

if (empty($token)) {
    die("<div class='alert alert-warning'>Λείπει το token πρόσβασης.</div>");
}

try {
    // 1. Επαλήθευση Token και ανάκτηση στοιχείων πίνακα
    $stmt = $pdo->prepare("SELECT * FROM canvases WHERE share_token = ?");
    $stmt->execute([$token]);
    $canvas = $stmt->fetch();

    if (!$canvas) {
        die("<div class='alert alert-danger'>Ο πίνακας δεν βρέθηκε ή ο σύνδεσμος έχει λήξει.</div>");
    }

    // 2. Ανάκτηση Σημειώσεων (Αφαιρέθηκε το deleted_at για να φαίνονται όλα)
    $stmtNotes = $pdo->prepare("
        SELECT * FROM notes 
        WHERE canva_id = ? 
        ORDER BY position_y, position_x
    ");
    $stmtNotes->execute([$canvas['canva_id']]);
    $notes = $stmtNotes->fetchAll(PDO::FETCH_ASSOC);

    // 3. Ανάκτηση Πολυμέσων (Αφαιρέθηκε το deleted_at)
    $stmtMedia = $pdo->prepare("
        SELECT * FROM media 
        WHERE canva_id = ? 
        ORDER BY position_y, position_x
    ");
    $stmtMedia->execute([$canvas['canva_id']]);
    $media = $stmtMedia->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Προβολή Πίνακα: <?= htmlspecialchars($canvas['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        #drawing-board {
            position: relative;
            width: 5000px;
            height: 5000px;
            background-color: #f8f9fa;
            transform-origin: 0 0;
            transition: transform 0.2s ease;
        }
        .note-card {
            position: absolute;
            width: 250px;
            padding: 10px;
            background: #fff9c4;
            border: 1px solid #fbc02d;
            box-shadow: 2px 2px 5px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark sticky-top">
        <div class="container-fluid">
            <span class="navbar-brand">📌 <?= htmlspecialchars($canvas['title']) ?> (View Only)</span>
            <div class="d-flex">
                <button class="btn btn-outline-light me-2" onclick="zoomIn()">+</button>
                <button class="btn btn-outline-light me-2" onclick="resetZoom()">100%</button>
                <button class="btn btn-outline-light" onclick="zoomOut()">-</button>
            </div>
        </div>
    </nav>

    <div id="canvas-container" style="overflow: auto; width: 100vw; height: 90vh;">
        <div id="drawing-board">
            <?php foreach ($notes as $note): ?>
                <div class="note-card" style="left: <?= $note['position_x'] ?>px; top: <?= $note['position_y'] ?>px;">
                    <div class="note-content"><?= $note['content'] ?></div>
                </div>
            <?php endforeach; ?>

            <?php foreach ($media as $item): ?>
                <div class="media-item" style="position: absolute; left: <?= $item['position_x'] ?>px; top: <?= $item['position_y'] ?>px;">
                    <?php if ($item['type'] == 'image'): ?>
                        <img src="<?= $item['file_path'] ?>" width="200">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        let zoomLevel = 1;
        const board = document.getElementById('drawing-board');

        function updateZoom() {
            board.style.transform = `scale(${zoomLevel})`;
        }

        function zoomIn() { zoomLevel = Math.min(zoomLevel + 0.1, 2); updateZoom(); }
        function zoomOut() { zoomLevel = Math.max(zoomLevel - 0.1, 0.5); updateZoom(); }
        function resetZoom() { zoomLevel = 1; updateZoom(); }

        // Απενεργοποίηση δεξιού κλικ για προστασία περιεχομένου
        document.addEventListener('contextmenu', event => event.preventDefault());
    </script>
</body>
</html>
<?php
} catch (PDOException $e) {
    die("Σφάλμα: " . $e->getMessage());
}
?>