<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

// Ρυθμίσεις αρχείων
$avatarPath = '/noteapp/uploads/avatars/';
$defaultAvatar = '/noteapp/images/default-avatar.png';

// Κατάσταση χρήστη
$user_id = $_SESSION['user_id'] ?? null;
$is_guest = ($user_id === null);

// Ειδοποιήσεις (Toasts)
$toast_html = '';
if (isset($_SESSION['success']) || isset($_SESSION['error'])) {
    $msg = $_SESSION['success'] ?? $_SESSION['error'];
    $type = isset($_SESSION['success']) ? 'success' : 'danger';
    $toast_html = "
    <div class='toast-container position-fixed top-0 end-0 p-3' style='z-index: 1080;'>
        <div class='toast align-items-center text-bg-$type border-0 show' role='alert'>
            <div class='d-flex'>
                <div class='toast-body'>$msg</div>
                <button type='button' class='btn-close btn-close-white me-2 m-auto' data-bs-dismiss='toast'></button>
            </div>
        </div>
    </div>";
    unset($_SESSION['success'], $_SESSION['error']);
}

$pending_canvases = [];
$accepted_canvases = [];
$public_canvases = [];

// 4. ΟΙ ΔΙΚΟΙ ΜΟΥ ΠΙΝΑΚΕΣ (Ιδιωτικοί & Δημόσιοι)
$stmt_mine = $pdo->prepare("
    SELECT c.*, u.username as owner_name, 'owner' as status, 'edit' as permission
    FROM canvases c 
    JOIN users u ON c.owner_id = u.user_id
    WHERE c.owner_id = ?
");
$stmt_mine->execute([$user_id]);
$my_canvases = $stmt_mine->fetchAll(PDO::FETCH_ASSOC);

// Ενοποίηση όλων των πινάκων
$display_canvases = array_merge($my_canvases, $accepted_canvases, $public_canvases);
try {
    if (!$is_guest) {
        // 1. ΣΥΝΕΡΓΑΤΙΚΟΙ ΠΙΝΑΚΕΣ
        $stmt_shared = $pdo->prepare("
            SELECT c.*, u.username as owner_name, cc.permission, cc.status
            FROM canvas_collaborators cc 
            JOIN canvases c ON cc.canva_id = c.canva_id 
            JOIN users u ON c.owner_id = u.user_id
            WHERE cc.user_id = ? AND cc.status IN ('pending', 'accepted')
        ");
        $stmt_shared->execute([$user_id]);
        while ($row = $stmt_shared->fetch(PDO::FETCH_ASSOC)) {
            if ($row['status'] == 'pending') $pending_canvases[] = $row;
            else $accepted_canvases[] = $row;
        }

        // 2. ΔΗΜΟΣΙΟΙ ΠΙΝΑΚΕΣ ΑΛΛΩΝ
        $stmt_pub = $pdo->prepare("
            SELECT c.*, u.username as owner_name 
            FROM canvases c JOIN users u ON c.owner_id = u.user_id
            WHERE c.access_type = 'public' AND c.owner_id != ?
            AND c.canva_id NOT IN (SELECT canva_id FROM canvas_collaborators WHERE user_id = ?)
        ");
        $stmt_pub->execute([$user_id, $user_id]);
        $public_canvases = $stmt_pub->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // 3. ΑΝΩΝΥΜΟΣ: ΜΟΝΟ ΔΗΜΟΣΙΟΙ
        $stmt_pub = $pdo->query("
            SELECT c.*, u.username as owner_name FROM canvases c 
            JOIN users u ON c.owner_id = u.user_id 
            WHERE c.access_type = 'public'
        ");
        $public_canvases = $stmt_pub->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) { error_log($e->getMessage()); }

$display_canvases = array_merge($accepted_canvases, $public_canvases);
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Κοινοχρηστοι Πινακες</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .canvas-card { border-radius: 12px; transition: 0.3s; border: none; background: white; }
        .canvas-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .badge-access { font-size: 0.7rem; text-transform: uppercase; padding: 5px 10px; border-radius: 6px; }
    </style>
</head>
<body class="bg-light">

<?= $toast_html ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
       <p class="text-muted">Πίνακες που σας προσκάλεσαν & Δημόσιοι πίνακες</p>
        <div>
            <?php if($is_guest): ?>
                <a href="/noteapp/login.php" class="btn btn-primary px-4 rounded-pill fw-bold shadow-sm">Σύνδεση</a>
            <?php else: ?>
                <a href="home.php" class="btn btn-outline-dark px-3 rounded-pill">Πίσω</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$is_guest && !empty($pending_canvases)): ?>
        <h6 class="text-danger mb-3 fw-bold"><i class="bi bi-envelope-exclamation"></i> ΠΡΟΣΦΑΤΕΣ ΠΡΟΣΚΛΗΣΕΙΣ</h6>
        <div class="row mb-4">
            <?php foreach ($pending_canvases as $c): ?>
                <div class="col-md-4 mb-3">
                    <div class="card border-danger shadow-sm p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold"><?= htmlspecialchars($c['name']) ?></div>
                                <div class="small text-muted">Από: <?= htmlspecialchars($c['owner_name']) ?></div>
                            </div>
                            <div class="d-flex gap-1">
                                <a href="api/canva/accept_invitation.php?canva_id=<?= $c['canva_id'] ?>" class="btn btn-sm btn-success shadow-sm"><i class="bi bi-check"></i></a>
                                <a href="api/canva/reject_invitation.php?canva_id=<?= $c['canva_id'] ?>" class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <hr>
    <?php endif; ?>

    <div class="row">
        <?php foreach ($display_canvases as $canvas): 
            $is_owner = (!$is_guest && $user_id == $canvas['owner_id']);
            $is_collaborator = isset($canvas['status']) && $canvas['status'] == 'accepted';
            $is_private = (isset($canvas['access_type']) && $canvas['access_type'] == 'private');

            if($is_owner) {
                //idioktitis 
                $type_text = $is_private ? "ΙΔΙΩΤΙΚΟΣ (εσεις)" : "ΔΗΜΟΣΙΟΣ (Εσεις)";
                $type_class = "bg-dark";
            }
            elseif ($is_collaborator) {
                if ($is_private) {
                    //idiotis synergatis
                    $type_text = "ΙΔΙΩΤΙΚΟΣ-ΣΥΝΕΡΓΑΤΙΚΟΣ";
                    $type_class = "bg-primary";
                } else{
                    //koinixristos  synergaris
                     $type_text = "ΚΟΙΝΟΧΡΗΣΤΟΣ-ΣΥΝΕΡΓΑΤΙΚΟΣ";
                     $type_class = "bg-info text-dark";
                }
            }
            else {
                //dimosios
                $type_text = "ΔΗΜΟΣΙΟΣ";
                $type_class = "bg-success";
            }
            //dikaiomta
          $can_edit = ($is_owner || (isset ($canvas['permission'])  && $canvas['permission'] == 'edit'));

        ?>
        
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card canvas-card shadow-sm h-100">
                <div class="card-body p-4 d-flex flex-column">
                    
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="badge badge-access <?= $type_class ?> text-white">
                            <?= $type_text ?>
                            
                        </span>
                        <span class="<?= $can_edit ? 'text-warning' : 'text-info' ?> small fw-bold d-flex align-items-center">
                                        <i class="bi <?= $can_edit ? 'bi-pencil-square' : 'bi-eye' ?> me-1"></i> 
                                                    <?= $can_edit ? 'Επεξεργασία' : 'Προβολή' ?>
                        </span>
                       
                         <i class="bi bi-folder"></i> <?= htmlspecialchars($canvas['canva_category'] ?? 'Γενική') ?>
                        
                    </div>
                    
                    <h5 class="card-title fw-bold text-dark mb-1">
                        <?= htmlspecialchars($canvas['name']) ?>
                    </h5>
                    <p class="text-muted mb-4 small">
                        <i class="bi bi-person-circle me-1"></i> 
                        <?= $is_owner ? "Εσείς" : htmlspecialchars($canvas['owner_name']) ?>
                    </p>

                    <div class="mt-auto">
                      

                        <div class="row g-2">
                            <?php if ($is_guest): ?>
                                <div class="col-12 text-center bg-light py-2 rounded-3 border">
                                    <small class="text-muted">
                                        <a href="/noteapp/login.php" class="text-primary fw-bold text-decoration-none">Συνδεθείτε</a> για συνεργασία
                                    </small>
                                    <a href="/noteapp/login.php" class="btn btn-dark w-100 rounded-pill fw-bold mb-3 shadow-sm">
                                            <i class="bi bi-lock-fill me-2"></i> Είσοδος (Απαιτείται Σύνδεση)
                                    </a>     
                                </div>
                            <?php else: ?>
                                <?php if ($is_collaborator && !$is_owner): ?>
                                     <a href="board.php?id=<?= $canvas['canva_id'] ?>" class="btn btn-dark w-100 rounded-pill fw-bold mb-1 shadow-sm">
                                            <i class="bi bi-box-arrow-in-right me-2"></i> Είσοδος
                                     </a>
                                    <div class="col-10">
                                      
                       
                                        <button class="btn btn-sm btn-outline-success w-100 disabled">
                                            <i class="bi bi-check2-circle me-1"></i> Μέλος
                                        </button>
                                    </div>
                                    <div class="col-2">
                                        <a href="leave_canvas.php?id=<?= $canvas['canva_id'] ?>" class="btn btn-sm btn-outline-danger w-100"  title="Αποχωρηση απο το πινακα" onclick="return confirm('Αποχώρηση;')"><i class="bi bi-box-arrow-right"></i></a>
                                    
                                    </div>
                                <?php elseif (!$is_owner && !$is_collaborator): ?>
                                    <div class="col-12">
                                        <a href="join_public.php?id=<?= $canvas['canva_id'] ?>" class="btn btn-success btn-sm w-100 fw-bold">
                                            <i class="bi bi-plus-circle me-1"></i> Προσθήκη στη Συνεργασία
                                        </a>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
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