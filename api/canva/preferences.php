<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$canva_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$user_id = $_SESSION['user_id'];

if (!$canva_id) { die("Σφάλμα: Μη έγκυρο ID καμβά."); }

// --- 1. ΛΕΙΤΟΥΡΓΙΑ ΔΙΑΓΡΑΦΗΣ ΑΡΧΕΙΟΥ ΕΙΚΟΝΑΣ ---
if (isset($_POST['remove_image'])) {
    $stmt = $pdo->prepare("SELECT background_value FROM canvases WHERE canva_id = ? AND user_id = ?");
    $stmt->execute([$canva_id, $user_id]);
    $img = $stmt->fetchColumn();
    
    if ($img && strpos($img, '/uploads/') !== false) {
        $path = $_SERVER['DOCUMENT_ROOT'] . $img;
        if (file_exists($path)) { unlink($path); }
    }
    
    $pdo->prepare("UPDATE canvases SET background_type = 'solid', background_value = '#ffffff' WHERE canva_id = ?")
        ->execute([$canva_id]);
    $_SESSION['success'] = "Η εικόνα αφαιρέθηκε!";
    header("Location: preferences.php?id=$canva_id");
    exit();
}

// --- 2. ΕΠΕΞΕΡΓΑΣΙΑ ΟΛΩΝ ΤΩΝ POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Αποθήκευση Εμφάνισης (Χρώμα ή Upload)
        if (isset($_POST['save_settings'])) {
            $type = $_POST['background_type'];
            $val = $_POST['current_bg_value'] ?? '#ffffff';

            if ($type === 'solid') {
                $val = $_POST['background_color'];
            } elseif ($type === 'image' && isset($_FILES['bg_file']) && $_FILES['bg_file']['error'] === 0) {
                $dir = $_SERVER['DOCUMENT_ROOT'] . '/noteapp/uploads/';
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                
                $ext = pathinfo($_FILES['bg_file']['name'], PATHINFO_EXTENSION);
                $filename = "canvas_" . $canva_id . "_" . time() . "." . $ext;
                
                if (move_uploaded_file($_FILES['bg_file']['tmp_name'], $dir . $filename)) {
                    $val = "/noteapp/uploads/" . $filename;
                }
            }
            $pdo->prepare("UPDATE canvases SET background_type = ?, background_value = ?, updated_at = NOW() WHERE canva_id = ?")
                ->execute([$type, $val, $canva_id]);
            $_SESSION['success'] = "Οι αλλαγές αποθηκεύτηκαν!";
        }

        // Σύνδεση/Αποσύνδεση Ομάδας
        if (isset($_POST['update_group'])) {
            $gid = filter_input(INPUT_POST, 'group_id', FILTER_VALIDATE_INT);
            $final_gid = ($gid > 0) ? $gid : null;
            $pdo->prepare("UPDATE canvases SET copy_from_group_id = ? WHERE canva_id = ?")
                ->execute([$final_gid, $canva_id]);
            $_SESSION['success'] = "Η σύνδεση με την ομάδα ενημερώθηκε!";
        }
        
   

        header("Location: preferences.php?id=$canva_id");
        exit();
    } catch (Exception $e) { die("Σφάλμα: " . $e->getMessage()); }
}

// --- 3. ΑΝΑΚΤΗΣΗ ΔΕΔΟΜΕΝΩΝ ΓΙΑ ΤΟ UI ---
$canvas = $pdo->prepare("SELECT * FROM canvases WHERE canva_id = ? AND user_id = ?");
$canvas->execute([$canva_id, $user_id]);
$canvas = $canvas->fetch(PDO::FETCH_ASSOC);

$groups = $pdo->prepare("SELECT * FROM groups WHERE user_id = ?");
$groups->execute([$user_id]);
$user_groups = $groups->fetchAll(PDO::FETCH_ASSOC);

$shares = $pdo->prepare("SELECT sc.*, u.email FROM shared_canvases sc JOIN users u ON sc.shared_at = u.user_id WHERE sc.canva_id = ?");
$shares->execute([$canva_id]);
$all_shares = $shares->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ρυθμίσεις | <?= htmlspecialchars($canvas['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .preview-box { 
            width: 100%; height: 160px; border: 2px dashed #cbd5e0; border-radius: 12px; 
            display: flex; align-items: center; justify-content: center; 
            background-size: cover; background-position: center; position: relative;
            background-color: #ffffff; transition: all 0.3s ease;
        }
        .section-title { font-weight: 700; color: #2d3748; margin-bottom: 1.5rem; display: flex; align-items: center; }
        .section-title i { margin-right: 10px; color: #4a90e2; }
    </style>
</head>
<body>

<div class="container py-5" style="max-width: 950px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Ρυθμίσεις Καμβά</h2>
        <a href="/noteapp/api/canva/canvas.php?id=<?= $canva_id ?>" class="btn btn-outline-dark px-4 rounded-pill">
            <i class="bi bi-arrow-left me-2"></i>Επιστροφή
        </a>
    </div>

    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-3"><?= $_SESSION['success']; unset($_SESSION['success']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card p-4 h-100">
                <h5 class="section-title"><i class="bi bi-palette-fill"></i>Στυλ & Φόντο</h5>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="current_bg_value" value="<?= htmlspecialchars($canvas['background_value']) ?>">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Τύπος Φόντου</label>
                        <select name="background_type" id="bgType" class="form-select form-select-lg">
                            <option value="solid" <?= $canvas['background_type'] == 'solid' ? 'selected' : '' ?>>Μονόχρωμο</option>
                            <option value="image" <?= $canvas['background_type'] == 'image' ? 'selected' : '' ?>>Εικόνα (Upload)</option>
                        </select>
                    </div>

                    <div id="colorGroup" class="mb-4" style="<?= $canvas['background_type'] == 'image' ? 'display:none' : '' ?>">
                        <label class="form-label">Επιλογή Χρώματος</label>
                        <input type="color" name="background_color" id="colorInput" class="form-control form-control-color w-100" 
                               value="<?= $canvas['background_type'] == 'solid' ? $canvas['background_value'] : '#ffffff' ?>">
                    </div>

                    <div id="imageGroup" class="mb-4" style="<?= $canvas['background_type'] == 'solid' ? 'display:none' : '' ?>">
                        <label class="form-label">Ανεβάστε Εικόνα</label>
                        <input type="file" name="bg_file" id="fileInput" class="form-control" accept="image/*">
                    </div>

                    <div class="mb-4">
                        <label class="form-label small text-muted">Προεπισκόπηση</label>
                        <div id="previewBox" class="preview-box" 
                             style="<?= $canvas['background_type'] == 'solid' ? 'background-color:'.$canvas['background_value'] : 'background-image:url('.$canvas['background_value'].')' ?>">
                            
                            <?php if($canvas['background_type'] == 'image' && !empty($canvas['background_value'])): ?>
                                <button type="submit" name="remove_image" class="btn btn-danger btn-sm position-absolute top-0 end-0 m-2 shadow-sm" onclick="return confirm('Θέλετε να διαγράψετε οριστικά την εικόνα;')">
                                    <i class="bi bi-trash-fill"></i>
                                </button>
                            <?php endif; ?>
                            <span id="previewLabel" class="badge bg-dark opacity-50" style="<?= !empty($canvas['background_value']) ? 'display:none' : '' ?>">No Preview</span>
                        </div>
                    </div>

                    <button type="submit" name="save_settings" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">
                        Αποθήκευση Αλλαγών
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card p-4 shadow-sm border-start border-warning border-4">
                <h5 class="section-title"><i class="bi bi-layers-fill"></i>Ομάδες</h5>
                <p class="small text-muted">Συνδεση πινακα με ομαδες.</p>
                <form method="POST">
                    <div class="d-flex gap-2">
                        <select name="group_id" class="form-select">
                            <option value="0">Χωρίς Σύνδεση</option>
                            <?php foreach ($user_groups as $g): ?>
                                <option value="<?= $g['group_id'] ?>" <?= $canvas['copy_from_group_id'] == $g['group_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($g['group_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="update_group" class="btn btn-warning fw-bold">Ενημέρωση</button>
                    </div>
                </form>
            </div>

            

            </div>
        </div>
    </div>
</div>


<script>
    const bgType = document.getElementById('bgType');
    const colorGroup = document.getElementById('colorGroup');
    const imageGroup = document.getElementById('imageGroup');
    const colorInput = document.getElementById('colorInput');
    const fileInput = document.getElementById('fileInput');
    const previewBox = document.getElementById('previewBox');
    const previewLabel = document.getElementById('previewLabel');

    bgType.addEventListener('change', function() {
        if(this.value === 'solid') {
            colorGroup.style.display = 'block';
            imageGroup.style.display = 'none';
            previewBox.style.backgroundImage = 'none';
            previewBox.style.backgroundColor = colorInput.value;
        } else {
            colorGroup.style.display = 'none';
            imageGroup.style.display = 'block';
            previewBox.style.backgroundColor = '#f8f9fa';
        }
    });

    colorInput.addEventListener('input', function() {
        if(bgType.value === 'solid') {
            previewBox.style.backgroundColor = this.value;
            previewLabel.style.display = 'none';
        }
    });

    fileInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                previewBox.style.backgroundImage = `url('${e.target.result}')`;
                previewBox.style.backgroundColor = 'transparent';
                previewLabel.style.display = 'none';
            };
            reader.readAsDataURL(file);
        }
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>