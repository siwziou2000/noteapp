<?php
session_start();
require 'includes/database.php'; // Χρειαζόμαστε τη βάση για να δούμε αν άλλαξε ο ρόλος

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Ελέγχουμε τον ρόλο απευθείας από τη βάση δεδομένων
$stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = :id");
$stmt->execute(['id' => $_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user && $user['role'] !== 'guest') {
    // Αν ο ρόλος άλλαξε, ενημερώνουμε το session και τον στέλνουμε στην αρχική
    $_SESSION['role'] = $user['role'];
    header("Location: api/canva/home.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Αναμονή Έγκρισης</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container vh-100 d-flex justify-content-center align-items-center">
        <div class="card shadow p-4 text-center" style="max-width: 500px;">
            <h2 class="text-warning">⚠️ Εκκρεμεί η Έγκριση</h2>
            <hr>
            <p class="lead">Γεια σου, <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>!</p>
            <p>Ο λογαριασμός σου δημιουργήθηκε με επιτυχία, αλλά ως <strong>Guest</strong> δεν έχεις ακόμη πρόσβαση στους πίνακες.</p>
            <div class="alert alert-info">
                Ο διαχειριστής θα ελέγξει την αίτησή σου και θα σου αποδώσει ρόλο (Μαθητή ή Καθηγητή) σύντομα.
            </div>
            <p class="text-muted small">Δοκίμασε να κάνεις ανανέωση στη σελίδα αργότερα ή συνδέσου ξανά.</p>
            <div class="d-grid gap-2">
                <button onclick="window.location.reload();" class="btn btn-primary">Έλεγχος Κατάστασης</button>
                <a href="logout.php" class="btn btn-outline-danger">Αποσύνδεση</a>
                  <a href="api/canva/public_canvases.php" class="btn btn-outline-info">
                                    <i class="bi bi-eye"></i> Περιηγηθείτε στους Δημόσιους Πίνακες
                                </a>
            </div>
        </div>
    </div>
</body>
</html>