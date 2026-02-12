<?php

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';


if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Φίλτρα και Αναζήτηση
$search = isset($_GET['search']) ? trim(filter_input(INPUT_GET, 'search', FILTER_SANITIZE_FULL_SPECIAL_CHARS)) : '';
$category = isset($_GET['category']) ? filter_input(INPUT_GET, 'category', FILTER_SANITIZE_FULL_SPECIAL_CHARS) : '';
$access_type_filter = isset($_GET['access_type']) ? filter_input(INPUT_GET, 'access_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS) : '';

// Pagination
$limit = 5;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// --- ΣΥΝΑΡΤΗΣΕΙΣ ---

function getCanvases($pdo, $user_id, $limit, $offset, $search, $category, $access_type) {
    // 1. Καμβάδες που ανήκουν στον χρήστη
    $whereOwned = ["c.user_id = ?"];
    $paramsOwned = [$user_id];
    if (!empty($search)) { $whereOwned[] = "c.name LIKE ?"; $paramsOwned[] = "%$search%"; }
    if (!empty($category)) { $whereOwned[] = "c.canva_category = ?"; $paramsOwned[] = $category; }
    if (!empty($access_type)) { $whereOwned[] = "c.access_type = ?"; $paramsOwned[] = $access_type; }
    $ownedClause = "WHERE " . implode(" AND ", $whereOwned);

    // 2. Καμβάδες που έχουν γίνει share στον χρήστη (από τον πίνακα shared_canvases)
    $whereShared = ["sc.recipient_id = ?"];
    $paramsShared = [$user_id];
    if (!empty($search)) { $whereShared[] = "c.name LIKE ?"; $paramsShared[] = "%$search%"; }
    if (!empty($category)) { $whereShared[] = "c.canva_category = ?"; $paramsShared[] = $category; }
    if (!empty($access_type)) { $whereShared[] = "c.access_type = ?"; $paramsShared[] = $access_type; }
    $sharedClause = "WHERE " . implode(" AND ", $whereShared);

    $sql = "
        (SELECT c.*, 0 AS is_shared_from_other FROM canvases c $ownedClause)
        UNION
        (SELECT c.*, 1 AS is_shared_from_other FROM shared_canvases sc
         JOIN canvases c ON sc.canva_id = c.canva_id $sharedClause)
        ORDER BY created_at DESC LIMIT ? OFFSET ?
    ";

    $params = array_merge($paramsOwned, $paramsShared, [$limit, $offset]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTotalCanvasCount($pdo, $user_id, $search, $category, $access_type) {
    // Παρόμοια λογική UNION για το σωστό σύνολο σελίδων
    $sql = "SELECT COUNT(*) FROM (
                (SELECT canva_id FROM canvases WHERE user_id = ?)
                UNION
                (SELECT canva_id FROM shared_canvases WHERE recipient_id = ?)
            ) as total";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $user_id]);
    return $stmt->fetchColumn();
}

$total_canvases = getTotalCanvasCount($pdo, $user_id, $search, $category, $access_type_filter);
$total_pages = ceil($total_canvases / $limit);
$canvases = getCanvases($pdo, $user_id, $limit, $offset, $search, $category, $access_type_filter);

// --- ΧΕΙΡΙΣΜΟΣ POST (CREATE / DELETE) ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("CSRF Error");
    }

    // ΔΙΑΓΡΑΦΗ
    if (isset($_POST['delete'])) {
        $delete_id = (int)$_POST['delete'];
        $stmt = $pdo->prepare("DELETE FROM canvases WHERE canva_id = ? AND user_id = ?");
        $stmt->execute([$delete_id, $user_id]);
        $_SESSION['success'] = "Ο καμβάς διαγράφηκε.";
        header("Location: canvas.php");
        exit();
    }

    // ΔΗΜΙΟΥΡΓΙΑ
    if (isset($_POST['create'])) {
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $access_type = $_POST['access_type'] ?? 'private';
        $canva_category = $_POST['canva_category'];
        $group_id = (int)($_POST['group_id'] ?? 0) ?: null;
        $unique_id = bin2hex(random_bytes(8));
        $share_token = bin2hex(random_bytes(16));

        // ΕΔΩ ΕΙΝΑΙ ΟΛΕΣ ΟΙ ΣΤΗΛΕΣ ΠΟΥ ΜΟΥ ΕΔΩΣΕΣ
        $sql = "INSERT INTO canvases (
            owner_id, user_id, name, unique_canva_id, title, description, 
            background_type, background_value, canva_category, 
            copy_from_group_id, is_default, access_type, share_token, 
            token_access_type, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, 'solid', '#ffffff', ?, ?, 0, ?, ?, 'view', NOW(), NOW())";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $user_id, $user_id, $name, $unique_id, $name, '', 
            $canva_category, $group_id, $access_type, $share_token
        ]);

        $_SESSION['success'] = "Νέος καμβάς δημιουργήθηκε!";
        header("Location: canvas.php");
        exit();
    }
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/api/canva/include/menu.php';

?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Διαχείριση Καμβάδων</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .access-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.85rem; font-weight: bold; }
        .bg-private { background: #ffeeba; color: #856404; }
        .bg-public { background: #d4edda; color: #155724; }
        .bg-shared { background: #cce5ff; color: #004085; }
    </style>
</head>
<body class="bg-light">

<div class="container py-5">
    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-5">
        <div class="card-body">
            <h4 class="mb-4 text-primary"><i class="bi bi-plus-circle"></i> Δημιουργια Καμβάς</h4>
            <form method="POST" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <label class="form-label fw-bold"> Όνομα Καμβά</label>
            <input type="text" name="name" class="form-control form-control-lg" placeholder="Εισάγετε όνομα" required>

             <!-- Ομάδα -->
        <div class="col-md-6">
            <label class="form-label fw-bold"> Ομάδα</label>
            <select name="group_id" class="form-select">
                <option value="0">Χωρίς ομάδα</option>
                <?php 
                $groups = $pdo->prepare("SELECT * FROM groups WHERE user_id = ?");
                $groups->execute([$user_id]);
                while ($group = $groups->fetch()): ?>
                    <option value="<?= $group['group_id'] ?>">
                        <?= htmlspecialchars($group['group_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
                <div class="col-md-3">
                    <label class="form-label">Δικαιώματα</label>
                    <select name="access_type" class="form-select">
                        <option value="private">Ιδιωτικό</option>
                        <option value="public">Δημόσιο</option>
                        <option value="shared">Κοινόχρηστο (Shared)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Κατηγορία</label>
                    <select name="canva_category" class="form-select">
                        <option value="educational">Εκπαίδευση</option>
                        <option value="marketing">Μάρκετινγκ</option>
                    </select>
                </div>
                <div class="col-12 text-end">
                    <button type="submit" name="create" class="btn btn-primary px-4">Δημιουργία</button>
                </div>
            </form>
        </div>
    </div>
    <!-- Φόρμα Αναζήτησης/Φίλτρων -->
<!-- Φόρμα Αναζήτησης/Φίλτρων --><form method="GET" class="mb-4 mt-5">
    <div class="row">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control" 
                   placeholder="Αναζήτηση..." 
                   value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-3">
            <select name="category" class="form-select">
                <option value="">Όλες οι κατηγορίες</option>
                <option value="educational" <?= $category === 'educational' ? 'selected' : '' ?>>Εκπαίδευση</option>
                <option value="marketing" <?= $category === 'marketing' ? 'selected' : '' ?>>Μάρκετινγκ</option>
            </select>
        </div>
        <div class="col-md-3">
        <select name="access_type" class="form-select">
    <option value="">Όλα τα δικαιώματα</option>
    <option value="public" <?= $access_type_filter === 'public' ? 'selected' : '' ?>>Δημόσιο</option>
    <option value="private" <?= $access_type_filter === 'private' ? 'selected' : '' ?>>Ιδιωτικό</option>
     <option value="shared" <?= $access_type_filter === 'private' ? 'selected' : '' ?>>Κοινοχρηστο</option>
</select>
        </div>
        <div class="col-md-2">
    <button type="submit" class="btn btn-primary w-100">Εφαρμογή</button>
    <?php if (!empty($search) || !empty($category) || !empty($access_type_filter)): ?>
        <a href="canvas.php" class="btn btn-outline-primary mt-3 w-100">Επιστροφή</a>
    <?php endif; ?>
</div>
    </div>
</form>

      <div class="row">
        <?php foreach ($canvases as $canvas): ?>
            <div class="col-12 mb-3">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1">
                                <?= htmlspecialchars($canvas['name']) ?>
                                <span class="access-badge bg-<?= $canvas['access_type'] ?>">
                                    <?= ($canvas['access_type'] == 'shared') ? 'Κοινόχρηστο' : (($canvas['access_type'] == 'public') ? 'Δημόσιο' : 'Ιδιωτικό') ?>
                                </span>
                                <?php if($canvas['is_shared_from_other']): ?>
                                    <span class="badge bg-warning text-dark ms-2">Shared with me</span>
                                <?php endif; ?>
                            </h5>
                            <small class="text-muted">ID: <?= $canvas['canva_id'] ?> | Κατηγορία: <?= $canvas['canva_category'] ?></small>
                        </div>
                        <div class="btn-group d-flex align-items-center" role="group">
                                <a href="view_canvas.php?link=<?= $canvas['unique_canva_id'] ?>" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center">
                                    <i class="bi bi-eye me-1"></i> Προβολή
                                </a>
                                    <?php if(!$canvas['is_shared_from_other']): ?>
                                        <a href="edit_canvas.php?id=<?= $canvas['canva_id'] ?>" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center justify-content-center">
                                          <i class="bi bi-pencil me-1"></i> Επεξεργασία
                                        </a>   
                                <a href="preferences.php?id=<?= $canvas['canva_id'] ?>" class="btn btn-primary btn-sm d-inline-flex align-items-center justify-content-center">
                                  <i class="bi bi-gear me-1"></i> Προτιμήσεις
                                </a>
                                    <form method="POST" class="m-0 d-inline-flex" onsubmit="return confirm('Διαγραφή;');">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                        <input type="hidden" name="delete" value="<?= $canvas['canva_id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm rounded-start-0 d-inline-flex align-items-center justify-content-center" style="height: 100%;">
                                        <i class="bi bi-trash me-1"></i> Διαγραφή </button>                                       
                                     </form>
         <?php endif; ?>
                </div>
    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
