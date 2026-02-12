<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

// Έλεγχος αν ο χρήστης είναι συνδεδεμένος
//if (!isset($_SESSION['user_id'])) {
    //header('Location: ../../login.php');
    //exit;}

// Ανάκτηση όλων των δημόσιων πινάκων με τον αριθμό των σημειώσεων
try {
    $stmt = $pdo->prepare("
        SELECT 
            c.*, 
            u.username, 
            u.avatar,
            (SELECT COUNT(*) FROM notes WHERE canva_id = c.canva_id) as note_count
        FROM canvases c
        JOIN users u ON c.owner_id = u.user_id
        WHERE c.access_type = 'public'
        ORDER BY c.created_at DESC
    ");
    $stmt->execute();
    $public_canvases = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Σφάλμα βάσης δεδομένων: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Δημόσιοι Πίνακες - Έξυπνες Σημειώσεις</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .canvas-card { transition: transform 0.3s ease, box-shadow 0.3s ease; height: 100%; }
        .canvas-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .avatar-img { width: 40px; height: 40px; object-fit: cover; }
        .badge-public { background-color: #28a745; color: white; }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Δημόσιοι Πίνακες</h1>
            <a href="../../index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Πίσω στην Αρχική
            </a>
        </div>

        <?php if (empty($public_canvases)): ?>
            <div class="alert alert-info">Δεν βρέθηκαν δημόσιοι πίνακες.</div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($public_canvases as $canvas): ?>
                    <div class="col">
                        <div class="card canvas-card shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="card-title mb-0"><?= htmlspecialchars($canvas['name']) ?></h5>
                                    <span class="badge badge-public">Δημόσιο</span>
                                </div>
                                <p class="card-text text-muted small">
                                    <i class="bi bi-journal-text"></i> <?= $canvas['note_count'] ?? 0 ?> σημειώσεις
                                </p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <?php
                                            $avatarPath = '/noteapp/uploads/avatars/';
                                            $defaultAvatar = '/noteapp/images/default-avatar.png';
                                            $avatarFile = !empty($canvas['avatar']) ? basename($canvas['avatar']) : '';
                                            $userAvatar = !empty($avatarFile) ? $avatarPath . htmlspecialchars($avatarFile) : $defaultAvatar;
                                        ?>
                                        <img src="<?= $userAvatar ?>" class="rounded-circle avatar-img me-2" 
                                             alt="<?= htmlspecialchars($canvas['username']) ?>"
                                             onerror="this.src='<?= $defaultAvatar ?>'">
                                        <span><?= htmlspecialchars($canvas['username']) ?></span>
                                    </div>
                                    <a href="view_canvases.php?id=<?= $canvas['canva_id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> Προβολή
                                    </a>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent">
                                <small class="text-muted">Δημιουργήθηκε: <?= date('d/m/Y', strtotime($canvas['created_at'])) ?></small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>