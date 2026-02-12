<?php
session_start();
require 'includes/database.php';

// 1. Ασφάλεια: Μόνο ο Admin μπαίνει εδώ
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Πρόσβαση μόνο για διαχειριστές.");
}

// 2. Λογική Έγκρισης: Όταν πατηθεί ένα από τα κουμπιά
if (isset($_GET['approve_id']) && isset($_GET['new_role'])) {
    $id = $_GET['approve_id'];
    $new_role = $_GET['new_role'];

    // Επιτρέπουμε μόνο συγκεκριμένους ρόλους για ασφάλεια
    if (in_array($new_role, ['student', 'teacher'])) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET role = :role WHERE user_id = :id AND role = 'guest'");
            $stmt->execute([
                'role' => $new_role,
                'id' => $id
            ]);
            $msg = "Ο χρήστης εγκρίθηκε ως " . ($new_role == 'teacher' ? 'Καθηγητής' : 'Μαθητής');
            header("Location: admin_approve.php?success=" . urlencode($msg));
            exit;
        } catch (PDOException $e) {
            $error = "Σφάλμα κατά την ενημέρωση: " . $e->getMessage();
        }
    }
}

// 3. Φέρνουμε όλους τους χρήστες που είναι ακόμα 'guest'
$stmt = $pdo->query("SELECT user_id, username, fullname, email, created_at FROM users WHERE role = 'guest' ORDER BY created_at DESC");
$guests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Διαχείριση Εγκρίσεων</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="card shadow">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-user-check me-2"></i>Εκκρεμείς Αιτήσεις Χρηστών</h4>
            <a href="admin_dashboard.php" class="btn btn-sm btn-outline-light">Επιστροφή στο Ταμπλό</a>
        </div>
        <div class="card-body">

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= htmlspecialchars($_GET['success']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Όνομα & Username</th>
                            <th>Email</th>
                            <th>Ημ/νία Εγγραφής</th>
                            <th class="text-center">Ενέργεια Έγκρισης</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($guests as $guest): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($guest['fullname']) ?></strong><br>
                                <small class="text-muted">@<?= htmlspecialchars($guest['username']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($guest['email']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($guest['created_at'])) ?></td>
                            <td class="text-center">
                                <div class="btn-group" role="group">
                                    <a href="?approve_id=<?= $guest['user_id'] ?>&new_role=student" 
                                       class="btn btn-primary btn-sm">
                                       <i class="fas fa-user-graduate me-1"></i> Ως Μαθητής
                                    </a>
                                    <a href="?approve_id=<?= $guest['user_id'] ?>&new_role=teacher" 
                                       class="btn btn-success btn-sm">
                                       <i class="fas fa-chalkboard-teacher me-1"></i> Ως Καθηγητής
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <?php if (empty($guests)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted">
                                <i class="fas fa-check-circle fa-2x mb-2 d-block text-success"></i>
                                Δεν υπάρχουν εκκρεμείς αιτήσεις αυτή τη στιγμή.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>