<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

// Έλεγχος αν ο χρήστης είναι συνδεδεμένος
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];
// CSRF Token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];


$canva_id = isset($_GET['canva_id']) ? (int)$_GET['canva_id'] : 0;//Παίρνει το canva_id από το URL και το επικυρώνει ως ακέραιο. Αν λείπει, ορίζεται σε 0.

try {
    // Έλεγχος αν ο καμβάς υπάρχει και αν ανήκει στον χρήστη
    $stmt = $pdo->prepare("SELECT * FROM canvases WHERE canva_id = ? AND user_id = ?");
    $stmt->execute([$canva_id, $user_id]);
    $canvas = $stmt->fetch();

    if (!$canvas || $canva_id < 1) {
        $_SESSION['error'] = "Δεν βρέθηκε ο καμβάς ή δεν έχετε δικαίωμα πρόσβασης.";
        header("Location: canvas.php");
        exit();
    }

    // Διαχείριση POST Αιτήματος (Κοινή Χρήση)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['share'])) {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $_SESSION['error'] = "Μη έγκυρη αίτηση. Το CSRF token δεν ταιριάζει.";
            header("Location: share_canvas.php?canva_id=" . $canva_id);
            exit();
        }

        // Καθαρισμός και έλεγχος email
        $recipient_email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        if (!$recipient_email) {
            $_SESSION['error'] = "Παρακαλώ εισάγετε ένα έγκυρο email.";
            header("Location: share_canvas.php?canva_id=" . $canva_id);
            exit();
        }

        // Επιλογή Δικαιώματος
        $permission = isset($_POST['permission']) && in_array($_POST['permission'], ['view', 'edit']) ? $_POST['permission'] : 'view';

        // Εύρεση χρήστη με το email
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$recipient_email]);
        $recipient = $stmt->fetch();

        if (!$recipient) {
            $_SESSION['error'] = "Δεν βρέθηκε χρήστης με αυτό το email.";
            header("Location: share_canvas.php?canva_id=" . $canva_id);
            exit();
        }

        // Έλεγχος αν ο καμβάς έχει ήδη κοινοποιηθεί στον χρήστη
        $stmt = $pdo->prepare("SELECT * FROM shared_canvases WHERE canva_id = ? AND recipient_id = ?");
        $stmt->execute([$canva_id, $recipient['user_id']]);

        if ($stmt->fetch()) {
            $_SESSION['error'] = "Ο χρήστης έχει ήδη πρόσβαση σε αυτόν τον καμβά.";
            header("Location: share_canvas.php?canva_id=" . $canva_id);
            exit();
        }

        // Προσθήκη κοινής χρήσης στον πίνακα shared_canvases
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO shared_canvases (canva_id, sender_id, recipient_id, permission, shared_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$canva_id, $user_id, $recipient['user_id'], $permission]);
            $pdo->commit();

            $_SESSION['success'] = "Επιτυχής κοινή χρήση με " . htmlspecialchars($recipient_email);
            header("Location: share_canvas.php?canva_id=" . $canva_id);
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Σφάλμα κατά την κοινή χρήση του καμβά.";
            header("Location: share_canvas.php?canva_id=" . $canva_id);
            exit();
        }
    }

    // Διαχείριση Ανάκλησης Κοινής Χρήσης
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revoke'])) {
        $shared_canvases_id = $_POST['shared_canvases_id'];

        // Διαγραφή κοινής χρήσης
        $stmt = $pdo->prepare("DELETE FROM shared_canvases WHERE shared_canvases_id = ?");
        $stmt->execute([$shared_canvases_id]);

        $_SESSION['success'] = "Η κοινή χρήση ανακλήθηκε με επιτυχία.";
        header("Location: share_canvas.php?canva_id=" . $canva_id);
        exit();
    }

    // Ανάκτηση χρηστών που έχουν πρόσβαση στον καμβά
    $stmt = $pdo->prepare("
        SELECT u.email, sc.shared_canvases_id, sc.permission, sc.shared_at
        FROM shared_canvases sc
        JOIN users u ON sc.recipient_id = u.user_id
        WHERE sc.canva_id = ?
    ");
    
    $stmt->execute([$canva_id]);
    $shared_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header("Location: share_canvas.php?canva_id=" . $canva_id);
    exit();
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Κοινή Χρήση Καμβά</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-4">
        <h1 class="mb-4">Κοινή Χρήση: <?= htmlspecialchars($canvas['name']) ?></h1>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <form method="POST" class="mb-5">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

            <div class="mb-3">
                <label for="email" class="form-label">Email χρήστη:</label>
                <input type="email" class="form-control" name="email" required>
            </div>

            <div class="mb-3">
                <label for="permission" class="form-label">Δικαιώματα:</label>
                <select name="permission" class="form-select">
                    <option value="view">Προβολή</option>
                    <option value="edit">Επεξεργασία</option>
                </select>
            </div>

            <button type="submit" name="share" class="btn btn-primary">Κοινή Χρήση</button>
        </form>

        <h3>Χρήστες με πρόσβαση</h3>
        <ul class="list-group">
            <?php foreach($shared_users as $user): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <?= htmlspecialchars($user['email']) ?> - Δικαιώματα: <?= htmlspecialchars($user['permission']) ?>
                    <form method="POST" action="revoke_share.php">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="shared_canvases_id" value="<?= $user['shared_canvases_id'] ?>">
                        <button type="submit" name="revoke" class="btn btn-danger btn-sm">Ανάκληση</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>

        <a href="canvas.php" class="btn btn-secondary mt-3">Επιστροφή</a>
    </div>
</body>
</html>
