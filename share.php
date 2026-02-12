<?php
// i  selida  ayti xrisimeyei gia na epitepei stoy xristes na moirazontai toyx pinakes toys  se alloys  meso eikikoy sundemoy(token)
//kai einia koinis provolis selida
// dimoyrgia dimosion syndesmon


//

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

// Έλεγχος αν η βάση είναι διαθέσιμη
if (!isset($pdo)) {
    die("
        <div class='container mt-5'>
            <div class='alert alert-danger'>
                <h4>Σφάλμα σύνδεσης!</h4>
                <p>Σφάλμα σύνδεσης με τη βάση δεδομένων.</p>
            </div>
        </div>
    ");
    exit;
}

$token = $_GET['token'] ?? '';// paeini to token apo to url


if (empty($token)) {
    die("
        <div class='container mt-5'>
            <div class='alert alert-danger'>
                <h4>Λείπει το token!</h4>
                <p>Ο σύνδεσμος που ακολουθήσατε είναι ελλιπής.</p>
                <a href='login.php' class='btn btn-primary'>Συνδεθείτε</a>
            </div>
        </div>
    ");
    exit;
}

//elegxos  an yparxei  i  oxi  to  token  kai den exei diagrafei o pinakas
try {
  
    $stmt = $pdo->prepare("
        SELECT c.*, u.username as owner_name, u.avatar
        FROM canvases c
        JOIN users u ON c.owner_id = u.user_id
        WHERE c.share_token = ? 
        AND (c.token_expires_at IS NULL OR c.token_expires_at >= NOW())
        
    ");


    $stmt->execute([$token]);
    $canvas = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$canvas) {
        //deixnei an exei  apotixei 
        $debug_stmt = $pdo->prepare("
            SELECT c.*, 
                 
            
                   (c.token_expires_at >= NOW()) as debug_not_expired,
                
                   
            FROM canvases c
            WHERE c.share_token = ?
        ");
        $debug_stmt->execute([$token]);
        $debug = $debug_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$debug) {
            $message = "Δεν βρέθηκε πίνακας με αυτό το token.";
        } else {
            if ($debug['debug_deleted']) {
                $message = "Ο πίνακας έχει διαγραφεί στις: " . date('d/m/Y H:i', strtotime($debug['debug_deleted']));
            } elseif (!$debug['debug_not_expired']) {
                $message = "Το token έληξε στις: " . date('d/m/Y H:i', strtotime($debug['token_expires_at']));
            } else {
                $message = "Ο πίνακας δεν είναι διαθέσιμος.";
            }
        }
        
        die("
            <div class='container mt-5'>
                <div class='alert alert-warning'>
                    <h4>Το token δεν είναι έγκυρο!</h4>
                    <p>$message</p>
                    <a href='login.php' class='btn btn-primary'>Συνδεθείτε</a>
                </div>
            </div>
        ");
        exit;
    }
    
    // Έλεγχος αν ο συνδεδεμένος χρήστης μπορεί να επεξεργαστεί
    $can_edit = false;
    $is_owner = false;
    
    if (isset($_SESSION['user_id'])) {
        $is_owner = ($_SESSION['user_id'] == $canvas['owner_id']);// elegxos an o trexon xristis einai katoxos pinakas 
        
        if ($is_owner) {
            $can_edit = true;
        } else {
            // Έλεγχος για συνεργάτες
            $stmt = $pdo->prepare("
                SELECT can_edit_notes FROM canvas_collaborators 
                WHERE canva_id = ? AND user_id = ? AND can_edit_notes = 1
            ");
            $stmt->execute([$canvas['canva_id'], $_SESSION['user_id']]);
            // elgxos an  o xristis  einia synergatis  me dikaimota epejergasias 
            $collaborator = $stmt->fetch();
            $can_edit = ($collaborator !== false);
        }
    }
    
    // Φόρτωση σημειώσεων - ΜΟΝΟ μη διαγραμμένες
    $stmtNotes = $pdo->prepare("
        SELECT * FROM notes 
        WHERE canva_id = ? 
        
        ORDER BY position_y, position_x
    ");
    $stmtNotes->execute([$canvas['canva_id']]);
    $notes = $stmtNotes->fetchAll(PDO::FETCH_ASSOC);
    
    // Φόρτωση πολυμέσων - ΜΟΝΟ μη διαγραμμένα
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($canvas['name']); ?> - Κοινή Προβολή | Έξυπνες Σημειώσεις</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --note-padding: 20px;
            --note-font-size: 16px;
            --note-line-height: 1.6;
            --note-max-width: 350px;
            --board-min-height: 700px;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding-bottom: 30px;
        }
        
        .container {
            max-width: 1400px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 30px;
            margin-top: 20px;
            margin-bottom: 30px;
        }
        
        .note-container { 
            position: absolute; 
            padding: var(--note-padding); 
            border-radius: 12px; 
            box-shadow: 0 5px 20px rgba(0,0,0,0.15); 
            max-width: var(--note-max-width); 
            min-width: 250px;
            background: white; 
            pointer-events: none;
            overflow-wrap: break-word;
            cursor: default;
            border: 2px solid rgba(255,255,255,0.8);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            font-size: var(--note-font-size);
            line-height: var(--note-line-height);
            z-index: 1;
        }
        
        .note-container:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            z-index: 100;
        }
        
        .note-content {
            min-height: 30px;
            margin-top: 10px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .media-item {
            position: absolute;
            pointer-events: none;
            max-width: var(--note-max-width);
            min-width: 200px;
            cursor: default;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            border: 2px solid rgba(255,255,255,0.8);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            z-index: 1;
        }
        
        .media-item:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            z-index: 100;
        }
        
        #notesBoard {
            min-height: var(--board-min-height);
            background: linear-gradient(45deg, #e3f2fd, #f3e5f5);
            position: relative;
            overflow: auto;
            border: 3px solid #90caf9;
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
            background-image: 
                radial-gradient(circle at 25px 25px, rgba(120, 120, 120, 0.2) 2%, transparent 2%), 
                radial-gradient(circle at 75px 75px, rgba(120, 120, 120, 0.2) 2%, transparent 2%);
            background-size: 100px 100px;
        }
        
        .owner-info {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: linear-gradient(to right, #f8f9fa, #e9ecef);
            border-radius: 12px;
            border-left: 5px solid #0d6efd;
        }
        
        .owner-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        }
        
        .readonly-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 100;
            background: transparent;
        }
        
        .canvas-title {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 5px;
            padding-bottom: 10px;
            border-bottom: 3px solid #0d6efd;
            position: relative;
        }
        
        .canvas-title:after {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 0;
            width: 100px;
            height: 3px;
            background: linear-gradient(to right, #0d6efd, #20c997);
            border-radius: 3px;
        }
        
        .stats-badge {
            background: linear-gradient(45deg, #0d6efd, #20c997);
            color: white;
            padding: 8px 15px;
            border-radius: 25px;
            font-weight: 600;
            box-shadow: 0 3px 10px rgba(13, 110, 253, 0.3);
        }
        
        .empty-board {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            color: #6c757d;
            padding: 40px;
            background: rgba(255,255,255,0.9);
            border-radius: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border: 2px dashed #adb5bd;
        }
        
        .empty-board i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #adb5bd;
        }
        
        .note-tag {
            font-size: 0.8em;
            padding: 5px 12px;
            border-radius: 20px;
            margin-right: 5px;
            margin-bottom: 10px;
            display: inline-block;
        }
        
        .note-icon {
            font-size: 1.5em;
            color: #6c757d;
            margin-left: 10px;
        }
        
        .due-date {
            background: #fff3cd;
            color: #856404;
            padding: 5px 10px;
            border-radius: 8px;
            margin-top: 15px;
            display: inline-block;
            font-size: 0.9em;
        }
        
        @media print {
            .note-container, .media-item {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
            
            .btn, .alert, .readonly-overlay {
                display: none !important;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
                margin-top: 10px;
            }
            
            #notesBoard {
                min-height: 500px;
                padding: 10px;
            }
            
            .note-container {
                max-width: 250px;
                padding: 15px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="flex-grow-1">
                <h1 class="canvas-title"><?php echo htmlspecialchars($canvas['name']); ?></h1>
                <div class="owner-info mt-3">
                    <?php 
                    $avatarPath = '/noteapp/uploads/avatars/';
                    $defaultAvatar = '/noteapp/images/default-avatar.png';
                    $avatarFile = $canvas['avatar'] ?? '';
                    
                    if (!empty($avatarFile) && file_exists($_SERVER['DOCUMENT_ROOT'] . $avatarPath . $avatarFile)) {
                        $avatarSrc = $avatarPath . htmlspecialchars($avatarFile);
                    } else {
                        $avatarSrc = $defaultAvatar;
                    }
                    ?>
                    <img src="<?php echo $avatarSrc; ?>" 
                         class="owner-avatar" 
                         alt="<?php echo htmlspecialchars($canvas['owner_name']); ?>"
                         onerror="this.src='<?php echo $defaultAvatar; ?>'">
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted d-block">Δημιουργός:</small>
                                <div class="fw-bold fs-5"><?php echo htmlspecialchars($canvas['owner_name']); ?></div>
                                <small class="text-muted">
                                    Δημιουργήθηκε: <?php echo date('d/m/Y H:i', strtotime($canvas['created_at'])); ?>
                                </small>
                            </div>
                            <div class="stats-badge">
                                <i class="bi bi-journal-text"></i> 
                                <?php echo count($notes) + count($media); ?> στοιχεία
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="text-end ms-3">
                <span class="badge bg-info fs-6 px-3 py-2">Κοινή Προβολή</span>
                <?php if ($canvas['token_expires_at']): ?>
                    <div class="small text-muted mt-2 bg-light p-2 rounded">
                        <i class="bi bi-clock-history"></i>
                        Λήγει: <?php echo date('d/m/Y H:i', strtotime($canvas['token_expires_at'])); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Ειδοποίηση για συνδεδεμένους χρήστες -->
        <?php if (isset($_SESSION['user_id'])): ?>
        <div class="alert alert-info mb-3 border-0 shadow-sm">
            <div class="d-flex align-items-center">
                <i class="bi bi-person-check me-3 fs-4 text-primary"></i>
                <div class="flex-grow-1">
                    <strong class="fs-5">Είστε συνδεδεμένος!</strong> 
                    <?php if ($is_owner): ?>
                        <span class="ms-2 text-success">
                            <i class="bi bi-award"></i> Είστε ο κάτοχος αυτού του πίνακα.
                        </span>
                    <?php elseif ($can_edit): ?>
                        <span class="ms-2 text-warning">
                            <i class="bi bi-pencil"></i> Έχετε δικαιώματα επεξεργασίας.
                        </span>
                    <?php else: ?>
                        <span class="ms-2 text-secondary">
                            <i class="bi bi-eye"></i> Δεν έχετε δικαιώματα επεξεργασίας.
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="mt-3">
                <?php if ($can_edit): ?>
                    <a href="api/canva/board.php?id=<?php echo $canvas['canva_id']; ?>" class="btn btn-success px-4">
                        <i class="bi bi-pencil-square"></i> Μετάβαση στην επεξεργασία
                    </a>
                <?php else: ?>
                    <button class="btn btn-secondary px-4" disabled>
                        <i class="bi bi-lock"></i> Μόνο προβολή
                    </button>
                <?php endif; ?>
                <button class="btn btn-outline-primary px-4" onclick="window.location.reload()">
                    <i class="bi bi-eye"></i> Παραμείνετε σε προβολή
                </button>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Information Alert -->
        <div class="alert alert-primary border-0 shadow-sm mb-4">
            <div class="d-flex align-items-center">
                <i class="bi bi-info-circle-fill me-3 fs-4"></i>
                <div>
                    <strong>Προβολή μόνο για ανάγνωση</strong>
                    <p class="mb-0">Αυτή είναι μια προβολή μόνο για ανάγνωση. Για επεξεργασία του πίνακα, πρέπει να συνδεθείτε στο λογαριασμό σας.</p>
                </div>
            </div>
        </div>
        
        <!-- Notes Board -->
        <div id="notesBoard">
            <?php if (empty($notes) && empty($media)): ?>
                <div class="empty-board">
                    <i class="bi bi-inbox"></i>
                    <h4 class="mt-3">Ο πίνακας είναι άδειος</h4>
                    <p class="text-muted">Δεν υπάρχουν σημειώσεις ή πολυμέσα σε αυτόν τον πίνακα.</p>
                </div>
            <?php endif; ?>
            
            <!-- Read-only overlay για προστασία -->
            <div class="readonly-overlay" 
                 onclick="alert('Αυτή είναι προβολή μόνο για ανάγνωση. Συνδεθείτε για επεξεργασία.');"></div>
            
            <!-- Σημειώσεις -->
            <?php foreach ($notes as $note): ?>
                <div class="note-container" 
                     style="left: <?php echo max(20, (int)$note['position_x']); ?>px; 
                            top: <?php echo max(20, (int)$note['position_y']); ?>px; 
                            background: <?php echo htmlspecialchars($note['color'] ?? 'linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%)'); ?>;">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <?php if (!empty($note['tag'])): ?>
                            <span class="note-tag" style="background: #6c757d; color: white;">
                                <?php echo htmlspecialchars($note['tag']); ?>
                            </span>
                        <?php endif; ?>
                        
                        <?php if (!empty($note['icon'])): ?>
                            <i class="bi bi-<?php echo htmlspecialchars($note['icon']); ?> note-icon"></i>
                        <?php endif; ?>
                    </div>
                    
                    <div class="note-content">
                        <?php 
                        // Χειρισμός HTML content με ασφάλεια
                        $content = $note['content'];
                        // Καθαρισμός βασικών HTML tags αν επιτρέπεται
                        $allowed_tags = '<br><strong><b><em><i><u><span><p><div><h1><h2><h3><h4><h5><h6><ul><ol><li>';
                        $content = strip_tags($content, $allowed_tags);
                        // Μετατροπή newlines σε <br> για συμβατότητα
                        $content = nl2br($content);
                        echo $content;
                        ?>
                    </div>
                    
                    <?php if (!empty($note['due_date'])): ?>
                        <div class="due-date">
                            <i class="bi bi-calendar-check"></i>
                            Προθεσμία: <?php echo date('d/m/Y', strtotime($note['due_date'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
            <!-- Πολυμέσα -->
            <?php foreach ($media as $item): ?>
                <div class="media-item" 
                     style="left: <?php echo max(20, (int)$item['position_x']); ?>px; 
                            top: <?php echo max(20, (int)$item['position_y']); ?>px;">
                    <?php if ($item['type'] === 'image'): ?>
                        <?php
                        $imagePath = $item['data'];
                        $fullImagePath = '';
                        
                        if (!empty($imagePath)) {
                            if (strpos($imagePath, '/noteapp/') !== false) {
                                $fullImagePath = $imagePath;
                            } else {
                                // Προσθήκη base path
                                $fullImagePath = '/noteapp' . (strpos($imagePath, '/') === 0 ? '' : '/') . ltrim($imagePath, '/');
                            }
                        }
                        
                        // Fallback εικόνα
                        $defaultImage = '/noteapp/images/default-image.png';
                        ?>
                        <div class="image-wrapper">
                            <img src="<?php echo htmlspecialchars($fullImagePath ?: $defaultImage); ?>" 
                                 class="img-fluid w-100 h-100 object-fit-cover" 
                                 alt="<?php echo htmlspecialchars($item['original_filename'] ?? 'Εικόνα'); ?>"
                                 style="min-height: 150px; max-height: 250px;"
                                 onerror="this.src='<?php echo $defaultImage; ?>'">
                            <?php if (!empty($item['original_filename'])): ?>
                                <div class="image-caption p-2 bg-dark bg-opacity-75 text-white small">
                                    <i class="bi bi-image"></i> 
                                    <?php echo htmlspecialchars($item['original_filename']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($item['type'] === 'video'): ?>
                        <div class="card h-100 shadow-sm">
                            <div class="card-body text-center d-flex flex-column justify-content-center p-4">
                                <i class="bi bi-play-circle-fill fs-1 text-primary mb-3"></i>
                                <p class="mb-0 fw-bold"><?php echo htmlspecialchars($item['original_filename'] ?? 'Βίντεο'); ?></p>
                                <small class="text-muted mt-2">
                                    <i class="bi bi-file-earmark-play"></i> Αρχείο βίντεο
                                </small>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card h-100 shadow-sm">
                            <div class="card-body text-center d-flex flex-column justify-content-center p-4">
                                <i class="bi bi-file-earmark-fill fs-1 text-secondary mb-3"></i>
                                <p class="mb-0 fw-bold"><?php echo htmlspecialchars($item['original_filename'] ?? 'Αρχείο'); ?></p>
                                <small class="text-muted mt-2">
                                    <i class="bi bi-download"></i> Αρχείο 
                                </small>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Actions -->
        <div class="mt-5 pt-4 border-top">
            <h5 class="mb-3 text-muted">Ενέργειες</h5>
            <div class="d-flex gap-3 flex-wrap">
                <?php if (!isset($_SESSION['user_id'])): ?>
                <a href="login.php" class="btn btn-primary px-4 py-2">
                    <i class="bi bi-box-arrow-in-right"></i> Συνδέσου για επεξεργασία
                </a>
                <?php endif; ?>
                
                <button onclick="window.print()" class="btn btn-outline-secondary px-4 py-2">
                    <i class="bi bi-printer"></i> Εκτύπωση
                </button>
                <a href="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" class="btn btn-outline-primary px-4 py-2">
                    <i class="bi bi-arrow-clockwise"></i> Ανανέωση προβολής
                </a>
                <a href="api/canva/public_canvases.php" class="btn btn-outline-info px-4 py-2">
                    <i class="bi bi-grid-3x3-gap"></i> Δημόσιοι Πίνακες
                </a>
                <a href="signup.php." class="btn btn-outline-success px-4 py-2">
                    <i class="bi bi-share"></i> Δημιουργία Λογαριασμού
                </a>
            </div>
            
            <?php if ($canvas['token_expires_at']): ?>
                <div class="alert alert-warning mt-4 border-0 shadow-sm">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-clock-history me-3 fs-4"></i>
                        <div>
                            <strong>Προσοχή!</strong> Αυτός ο σύνδεσμος λήγει στις 
                            <span class="fw-bold"><?php echo date('d/m/Y H:i', strtotime($canvas['token_expires_at'])); ?></span>
                            <p class="mb-0 small mt-1">Μετά από αυτή την ημερομηνία, ο σύνδεσμος δεν θα είναι διαθέσιμος.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="text-center text-muted mt-5 pt-3 border-top">
                <p class="mb-1">
                    <i class="bi bi-c-circle"></i> Σύστημα Έξυπνων Σημειώσεων <?php echo date('Y'); ?> 
                    - Κοινή προβολή πίνακα
                </p>
                <small>
                    <i class="bi bi-shield-check"></i> Ασφαλής προβολή μόνο για ανάγνωση
                </small>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Enhanced read-only protection
        document.addEventListener('DOMContentLoaded', function() {
            const board = document.getElementById('notesBoard');
            
            // Disable all interactions
            board.style.userSelect = 'none';
            board.style.webkitUserSelect = 'none';
            board.style.msUserSelect = 'none';
            board.style.cursor = 'default';
            
            // Add smooth zoom effect on hover
            const items = document.querySelectorAll('.note-container, .media-item');
            items.forEach(item => {
                item.style.transition = 'all 0.3s ease';
            });
            
            // Προστασία από αντιγραφή με καλύτερο μήνυμα
            document.addEventListener('copy', function(e) {
                if (e.target.closest('#notesBoard')) {
                    e.preventDefault();
                    showToast('Η αντιγραφή είναι απενεργοποιημένη σε αυτή την προβολή.');
                    return false;
                }
            });
            
            // Προστασία από δεξί κλικ
            document.addEventListener('contextmenu', function(e) {
                if (e.target.closest('#notesBoard')) {
                    e.preventDefault();
                    showToast('Η λειτουργία δεξιού κλικ είναι απενεργοποιημένη.');
                    return false;
                }
            });
            
            // Προστασία από developer tools
            document.addEventListener('keydown', function(e) {
                // Απενεργοποίηση F12
                if (e.key === 'F12') {
                    e.preventDefault();
                    showToast('Οι developer tools είναι απενεργοποιημένες.');
                    return false;
                }
                
                // Απενεργοποίηση Ctrl+Shift+I (Chrome DevTools)
                if (e.ctrlKey && e.shiftKey && e.key === 'I') {
                    e.preventDefault();
                    showToast('Οι developer tools είναι απενεργοποιημένες.');
                    return false;
                }
            });
            
            // Απλοποιημένη εμφάνιση μηνύματος
            function showToast(message) {
                // Ελέγξτε αν υπάρχει ήδη toast
                let toast = document.querySelector('.protection-toast');
                if (toast) {
                    toast.remove();
                }
                
                toast = document.createElement('div');
                toast.className = 'protection-toast position-fixed bottom-0 end-0 p-3';
                toast.style.zIndex = '9999';
                toast.innerHTML = `
                    <div class="toast show" role="alert">
                        <div class="toast-header bg-warning text-white">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong class="me-auto">Προστασία</strong>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                        </div>
                        <div class="toast-body">
                            ${message}
                        </div>
                    </div>
                `;
                document.body.appendChild(toast);
                
                // Αυτόματη αφαίρεση μετά από 3 δευτερόλεπτα
                setTimeout(() => {
                    if (toast && toast.parentNode) {
                        toast.remove();
                    }
                }, 3000);
            }
        });
        
        // Δυνατότητα zoom για καλύτερη προβολή (μόνο για προβολή)
        let zoomLevel = 1;
        const board = document.getElementById('notesBoard');
        
        if (board) {
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === '+') {
                    e.preventDefault();
                    zoomLevel = Math.min(zoomLevel + 0.1, 2);
                    updateZoom();
                }
                if (e.ctrlKey && e.key === '-') {
                    e.preventDefault();
                    zoomLevel = Math.max(zoomLevel - 0.1, 0.5);
                    updateZoom();
                }
                if (e.ctrlKey && e.key === '0') {
                    e.preventDefault();
                    zoomLevel = 1;
                    updateZoom();
                }
            });
            
            function updateZoom() {
                board.style.transform = `scale(${zoomLevel})`;
                board.style.transformOrigin = 'top left';
                board.style.transition = 'transform 0.3s ease';
                
                // Εμφάνιση του zoom level
                showZoomLevel();
            }
            
            function showZoomLevel() {
                let zoomIndicator = document.getElementById('zoom-indicator');
                if (!zoomIndicator) {
                    zoomIndicator = document.createElement('div');
                    zoomIndicator.id = 'zoom-indicator';
                    zoomIndicator.className = 'position-fixed top-0 end-0 m-3 p-2 bg-dark bg-opacity-75 text-white rounded';
                    zoomIndicator.style.zIndex = '1000';
                    document.body.appendChild(zoomIndicator);
                }
                
                zoomIndicator.innerHTML = `
                    <small>
                        <i class="bi bi-zoom-in"></i> Zoom: ${Math.round(zoomLevel * 100)}%
                        <button class="btn btn-sm btn-outline-light ms-2" onclick="resetZoom()">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </button>
                    </small>
                `;
                
                // Αυτόματη απόκρυψη μετά από 2 δευτερόλεπτα
                setTimeout(() => {
                    if (zoomIndicator && zoomLevel === 1) {
                        zoomIndicator.remove();
                    }
                }, 2000);
            }
            
            // Δυνατότητα reset zoom
            window.resetZoom = function() {
                zoomLevel = 1;
                updateZoom();
            };
            
            // Προσθήκη zoom με mouse wheel + Ctrl
            board.addEventListener('wheel', function(e) {
                if (e.ctrlKey) {
                    e.preventDefault();
                    if (e.deltaY < 0) {
                        zoomLevel = Math.min(zoomLevel + 0.1, 2);
                    } else {
                        zoomLevel = Math.max(zoomLevel - 0.1, 0.5);
                    }
                    updateZoom();
                }
            }, { passive: false });
        }
        
        // Βελτιωμένη προστασία κατά το κλείσιμο της σελίδας
        window.addEventListener('beforeunload', function(e) {
            // Προσθήκη κώδικα εδώ αν θέλετε να εμφανίσετε μήνυμα κατά το κλείσιμο
        });
    </script>
</body>
</html>
<?php
    
} catch (PDOException $e) {
    error_log("Share token error: " . $e->getMessage());
    die("
        <div class='container mt-5'>
            <div class='alert alert-danger'>
                <h4>Σφάλμα Συστήματος</h4>
                <p>Προέκυψε ένα σφάλμα κατά τη φόρτωση της σελίδας.</p>
                <p><small>" . htmlspecialchars($e->getMessage()) . "</small></p>
                <a href='login.php' class='btn btn-primary'>Πίσω στην αρχική</a>
            </div>
        </div>
    ");
    exit;
}
?>