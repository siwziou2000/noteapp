<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/api/canva/include/alert.php';

// 1. Έλεγχος σύνδεσης
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$group_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$group_id) {
    header("Location: groups.php");
    exit();
}

// 2. Ανάκτηση δεδομένων ομάδας & Έλεγχος αν ο χρήστης είναι μέλος ή ιδιοκτήτης
$stmt = $pdo->prepare("
    SELECT g.*, gm.role as my_role 
    FROM groups g 
    LEFT JOIN group_members gm ON g.group_id = gm.group_id AND gm.user_id = ?
    WHERE g.group_id = ? AND (g.user_id = ? OR gm.user_id IS NOT NULL)
    LIMIT 1
");
$stmt->execute([$user_id, $group_id, $user_id]);
$group = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$group) {
    $_SESSION['error'] = "Δεν έχετε δικαίωμα πρόσβασης σε αυτή την ομάδα.";
    header("Location: groups.php");
    exit();
}

// --- LOGIC: ΔΙΑΓΡΑΦΗ ΟΜΑΔΑΣ (Μόνο ο Ιδιοκτήτης) ---
if (isset($_GET['delete']) && $_GET['delete'] == '1' && $group['user_id'] == $user_id) {
    $pdo->prepare("DELETE FROM groups WHERE group_id = ?")->execute([$group_id]);
    $_SESSION['success'] = "Η ομάδα διαγράφηκε επιτυχώς.";
    header("Location: groups.php");
    exit();
}

// --- LOGIC: ΑΦΑΙΡΕΣΗ ΜΕΛΟΥΣ ---
if (isset($_GET['remove_member'])) {
    $mid = (int)$_GET['remove_member'];
    // Δεν επιτρέπουμε να διαγραφεί ο ιδιοκτήτης της ομάδας
    if ($mid != $group['user_id']) {
        $pdo->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?")->execute([$group_id, $mid]);
        $_SESSION['success'] = "Το μέλος αφαιρέθηκε.";
    }
    header("Location: group.php?id=$group_id");
    exit();
}

// --- LOGIC: ΕΝΗΜΕΡΩΣΗ ΡΟΛΟΥ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role_btn'])) {
    $mid = (int)$_POST['member_id'];
    $role = $_POST['role'];
    $pdo->prepare("UPDATE group_members SET role = ? WHERE group_id = ? AND user_id = ?")->execute([$role, $group_id, $mid]);
    $_SESSION['success'] = "Ο ρόλος ενημερώθηκε.";
    header("Location: group.php?id=$group_id");
    exit();
}

// --- LOGIC: ΠΡΟΣΘΗΚΗ ΜΕΛΟΥΣ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_member'])) {
    $identifier = filter_input(INPUT_POST, 'invite_identifier', FILTER_SANITIZE_SPECIAL_CHARS);
    
    $u_stmt = $pdo->prepare("SELECT user_id, username FROM users WHERE email = ? OR username = ?");
    $u_stmt->execute([$identifier, $identifier]);
    $target = $u_stmt->fetch();

    if ($target) {
        $pdo->prepare("INSERT IGNORE INTO group_members (group_id, user_id, role) VALUES (?, ?, 'viewer')")->execute([$group_id, $target['user_id']]);
        
        // Ειδοποίηση
        $notif_msg = "Προστέθηκες στην ομάδα: " . $group['group_name'];
        $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")->execute([$target['user_id'], $notif_msg]);
        
        $_SESSION['success'] = "Ο χρήστης προστέθηκε!";
    } else {
        $_SESSION['error'] = "Ο χρήστης δεν βρέθηκε.";
    }
    header("Location: group.php?id=$group_id");
    exit();
}

// 3. ΑΝΑΚΤΗΣΗ ΜΕΛΩΝ
$members = $pdo->prepare("
    SELECT u.user_id, u.username, u.email, gm.role 
    FROM group_members gm 
    JOIN users u ON gm.user_id = u.user_id 
    WHERE gm.group_id = ?
");
$members->execute([$group_id]);
$all_members = $members->fetchAll();

// 4. ΑΝΑΚΤΗΣΗ ΠΙΝΑΚΩΝ (CANVASES) ΤΗΣ ΟΜΑΔΑΣ
// Χρησιμοποιούμε το copy_from_group_id όπως ορίζεται στο SQL σου
$canvases = $pdo->prepare("
    SELECT c.*, u.username as creator 
    FROM canvases c 
    JOIN users u ON c.owner_id = u.user_id 
    WHERE c.copy_from_group_id = ? 
    ORDER BY c.canva_id DESC
");
$canvases->execute([$group_id]);
$all_canvases = $canvases->fetchAll();
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Ομάδα: <?= htmlspecialchars($group['group_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card { border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .section-title { font-weight: bold; border-left: 5px solid #0d6efd; padding-left: 15px; margin-bottom: 20px; }
    </style>
</head>
<body class="bg-light p-4">

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><?= htmlspecialchars($group['group_name']) ?></h2>
        <a href="groups.php" class="btn btn-outline-secondary btn-sm">Επιστροφή</a>
    </div>

    <?php include '../../../includes/alerts.php'; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card p-4">
                <h5 class="section-title">Πίνακες Ομάδας</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
    <thead>
        <tr>
            <th>Τίτλος</th>
            <th>Δημιουργός</th>
            <th>Τύπος Πρόσβασης</th>
            <th>Κατηγορία</th>
            <th class="text-end">Ενέργεια</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($all_canvases as $c): ?>
        <tr>
            <td><strong><?= htmlspecialchars($c['name'] ?: 'Πίνακας #'.$c['canva_id']) ?></strong></td>
            <td><span class="badge bg-info text-dark"><?= htmlspecialchars($c['creator']) ?></span></td>
            <td>
                <?php
                $access_type = $c['access_type'] ?? 'private';
                $badge_class = 'bg-secondary'; // default
                
                switch($access_type) {
                    case 'public':
                        $badge_class = 'bg-success';
                        $access_text = 'Δημόσιος';
                        break;
                    case 'shared':
                        $badge_class = 'bg-warning text-dark';
                        $access_text = 'Κοινόχρηστος';
                        break;
                    case 'private':
                    default:
                        $badge_class = 'bg-secondary';
                        $access_text = 'Ιδιωτικός';
                        break;
                }
                ?>
                <span class="badge <?= $badge_class ?>"><?= htmlspecialchars($access_text) ?></span>
            </td>
            <td>
                <?php if(!empty($c['canva_category'])): ?>
                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($c['canva_category']) ?></span>
                <?php else: ?>
                    <span class="text-muted">-</span>
                <?php endif; ?>
            </td>
            <td class="text-end">
    <a href="../11.php?id=<?= $c['canva_id'] ?>" class="btn btn-sm btn-primary">Άνοιγμα</a>
    
    <?php if($c['access_type'] === 'shared' && !empty($c['share_token'])): ?>
        <?php 
            // Δημιουργούμε το πλήρες link που περιλαμβάνει το token
            $share_url = "https://" . $_SERVER['HTTP_HOST'] . "/noteapp/api/canva/11.php?token=" . $c['share_token'];
        ?>
        <button type="button" class="btn btn-sm btn-outline-success" 
                onclick="copyToClipboard('<?= $share_url ?>')" 
                title="Αντιγραφή συνδέσμου κοινής χρήσης">
            🔗
        </button>
    <?php endif; ?>
</td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($all_canvases)): ?>
            <tr><td colspan="5" class="text-center text-muted">Δεν υπάρχουν πίνακες σε αυτή την ομάδα.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        alert("Ο σύνδεσμος κοινής χρήσης αντιγράφηκε!");
    }, function(err) {
        console.error('Σφάλμα κατά την αντιγραφή: ', err);
    });
}
</script>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card p-4">
                <h5 class="section-title">Προσθήκη Μέλους</h5>
                <form method="POST" class="d-flex gap-2">
                    <input type="text" name="invite_identifier" class="form-control" placeholder="Email ή Username" required>
                    <button type="submit" name="add_member" class="btn btn-success">Προσθήκη</button>
                </form>
            </div>

            <div class="card p-4">
                <h5 class="section-title">Μέλη Ομάδας</h5>
                <?php foreach($all_members as $m): ?>
                <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                    <span class="fw-bold small"><?= htmlspecialchars($m['username']) ?></span>
                    <div class="d-flex align-items-center gap-2">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="member_id" value="<?= $m['user_id'] ?>">
                            <select name="role" class="form-select form-select-sm" onchange="this.form.submit()" <?= ($m['user_id'] == $user_id) ? 'disabled' : '' ?>>
                                <option value="viewer" <?= $m['role']=='viewer'?'selected':'' ?>>Viewer</option>
                                <option value="editor" <?= $m['role']=='editor'?'selected':'' ?>>Editor</option>
                                <option value="admin" <?= $m['role']=='admin'?'selected':'' ?>>Admin</option>
                            </select>
                            <input type="hidden" name="update_role_btn" value="1">
                        </form>
                        <?php if($m['user_id'] != $group['user_id']): ?>
                            <a href="?id=<?= $group_id ?>&remove_member=<?= $m['user_id'] ?>" class="btn btn-sm text-danger" onclick="return confirm('Αφαίρεση μέλους;')">×</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if($group['user_id'] == $user_id): ?>
            <div class="card border-danger bg-light p-3 mt-3">
                <h6 class="text-danger fw-bold">Διαχείριση Ομάδας</h6>
                <p class="small text-muted mb-2">Η διαγραφή είναι οριστική και θα διαγράψει όλα τα μέλη.</p>
                <a href="?id=<?= $group_id ?>&delete=1" class="btn btn-sm btn-outline-danger w-100" onclick="return confirm('Είστε σίγουροι;')">Διαγραφή Ομάδας</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>