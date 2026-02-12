<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

//crsf protection
if(!isset($_SESSION['csrf_token'])){
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
}

// elexfos syndesis xristi
if(!isset($_SESSION['user_id']) && !isset($_GET['token'])){
    header('Content-Type: application/json');
    die(json_encode(['error' =>'πρεπει να συνδεθειτε!']));

}
//vasikes metavolites gia to systima mas
$user_id = (int)$_SESSION['user_id'];
$canva_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : null;

/// token gia tin koinopiisi toy pinaka
$share_token = isset($_GET['token']) ? $_GET['token'] : null;

//veltiomeneoo elexgos prossanis gia to pinaka toy sistimatos
try {
    if (!$canva_id && $share_token) {
        $stmtId = $pdo->prepare("SELECT canva_id FROM canvases WHERE share_token = ?");
        $stmtId->execute([$share_token]);
        $res = $stmtId->fetch();
        if ($res) $canva_id = $res['canva_id'];
    }

    if (!$canva_id && isset($_SESSION['last_canva_id'])) {
        $canva_id = $_SESSION['last_canva_id'];
    }

    if (!$canva_id) {
        die("Ο πίνακας δεν βρέθηκε. Παρακαλώ επιστρέψτε στο <a href='dashboard.php'>Dashboard</a>.");
    }

    // Αποθήκευση για το επόμενο request
    $_SESSION['last_canva_id'] = $canva_id;

    // 4. ΕΛΕΓΧΟΣ ΠΡΟΣΒΑΣΗΣ & ACCESS LEVEL
    $stmtUserRole = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
    $stmtUserRole->execute([$user_id]);
    $currentUserRole = $stmtUserRole->fetchColumn();

    $stmtCheck = $pdo->prepare("
        SELECT c.*,
               CASE
                   WHEN ? = 'admin' THEN 'owner' 
                   WHEN c.owner_id = ? THEN 'owner'
                   WHEN cc.permission = 'edit' AND cc.status = 'accepted' THEN 'editor'
                   WHEN cc.permission = 'view' AND cc.status = 'accepted' THEN 'viewer'
                   WHEN c.access_type IN ('public','δημοσιο') THEN 'public_viewer'
                   ELSE 'no_access'
               END as access_level
        FROM canvases c 
        LEFT JOIN canvas_collaborators cc ON c.canva_id = cc.canva_id AND cc.user_id = ?
        WHERE c.canva_id = ?
    ");
    $stmtCheck->execute([$currentUserRole, $user_id, $user_id, $canva_id]);
    $access = $stmtCheck->fetch();

    if(!$access || $access['access_level'] === 'no_access'){
        die("Δεν έχετε δικαίωμα πρόσβασης.");
    }

    $access_level = $access['access_level'];
    $can_edit = in_array($access_level, ['owner', 'editor']);

} catch(PDOException $e) {
    die("σφαλμα βασης δεδομενων: " . $e->getMessage());
}
// Κρίσιμη μεταβλητή για το UI και το Backend
    $can_edit = in_array($access['access_level'], ['owner', 'editor']);
//anaktisi onomatos kamva
$canvas_name = 'νεος καμβας';
$access_type = 'private';
try{
     $stmt = $pdo->prepare("SELECT name, access_type FROM canvases WHERE canva_id = ?");
    $stmt->execute([$canva_id]);
    $canvas = $stmt->fetch();
    if ($canvas) {
        $canvas_name = htmlspecialchars($canvas['name']);
        $access_type = htmlspecialchars($canvas['access_type']);
    }
} catch (PDOException $e) {
    error_log("Ονομα καμβα λαθος r: " . $e->getMessage());

}

//elegxoa prostasie se omada an e=yarxei to groypid
if ($group_id) {
    try {
        $stmtGroupCheck = $pdo->prepare("
            SELECT * FROM groups 
            WHERE group_id = ? AND (
                user_id = ? OR EXISTS (
                    SELECT * FROM group_members WHERE group_id = ? AND user_id = ?
                )
            )
        ");
        $stmtGroupCheck->execute([$group_id, $user_id, $group_id, $user_id]);
        if (!$stmtGroupCheck->fetch()) {
            header('Content-Type: application/json');
            die(json_encode(['error' => 'Δεν έχετε δικαίωμα σε αυτήν την ομάδα']));
        }
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        die(json_encode(['error' => 'Σφάλμα ελέγχου ομάδας: ' . $e->getMessage()]));
    }
}
//anaktiksi simeioseosn
try {
    $sql = "SELECT * FROM notes WHERE canva_id = ?";
    $params = [$canva_id];
    
    if ($group_id) {
        $sql .= " AND group_id = ?";
        $params[] = $group_id;
    }
    
    $sql .= " ORDER BY position_x ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $has_notes = count($notes) > 0;

} catch (PDOException $e) {
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Σφάλμα φόρτωσης σημειώσεων: ' . $e->getMessage()]));
}

try {
    $stmt = $pdo->prepare("
        SELECT m.*, u.username AS locked_by_name 
        FROM media m 
        LEFT JOIN users u ON m.locked_by = u.user_id
        WHERE m.canva_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$canva_id]);
    $media = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Media load error: " . $e->getMessage());
    $media = [];
}
//anaktisi prilorogorion pinaka
try {
    $stmt = $pdo->prepare("SELECT name FROM canvases WHERE canva_id = ?");
    $stmt->execute([$canva_id]);
    $canvas = $stmt->fetch(PDO::FETCH_ASSOC);
    $canvas_name = $canvas['name'] ?? 'Unnamed Canvas';
} catch (PDOException $e) {
    $canvas_name = 'Unnamed Canvas';
}

//anaktisi sunergaton
try {
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.username, u.avatar, cc.permission 
        FROM canvas_collaborators cc 
        JOIN users u ON cc.user_id = u.user_id 
        WHERE cc.canva_id = ?
    ");
    $stmt->execute([$canva_id]);
    $collaborators = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $collaborators = [];
}
//epejergasia post aitimaton
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die(json_encode(['error' => 'Μη έγκυρο αίτημα!']));
    }
    if (!$can_edit) {
            die(json_encode(['error' => 'Δεν έχετε δικαίωμα επεξεργασίας.']));
        }
    
    try {
        $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING) ?? '';
        
        switch ($action) {
            case 'save_note':
                $content = htmlspecialchars($_POST['content'] ?? '');
                $color = $_POST['color'] ?? '#ffffff';
                $tags = isset($_POST['tags']) ? implode(',', array_map('htmlspecialchars', explode(',', $_POST['tags']))) : '';
                $position_x = isset($_POST['position_x']) ? (int)$_POST['position_x'] : 0;
                $group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : null;
                                
                // Έλεγχος πρόσβασης σε ομάδα (αν υπάρχει)
                if ($group_id) {
                    $stmtGroupCheck = $pdo->prepare("
                        SELECT * FROM groups 
                        WHERE group_id = ? AND (
                            user_id = ? OR EXISTS (
                                SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?
                            )
                        )
                    ");
                    $stmtGroupCheck->execute([$group_id, $user_id, $group_id, $user_id]);
                    if (!$stmtGroupCheck->fetch()) {
                        throw new Exception('Δεν έχετε δικαίωμα σε αυτήν την ομάδα', 403);
                    }
                }                
                
                // Αποθήκευση σημείωσης
                $stmt = $pdo->prepare("
                    INSERT INTO notes 
                    (owner_id, content, color, tags, position_x, canva_id, group_id, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $user_id, 
                    $content, 
                    $color, 
                    $tags, 
                    $position_x, 
                    $canva_id,
                    $group_id
                ]);
                
                echo json_encode([
                    'success' => true,
                    'note_id' => $pdo->lastInsertId()
                ]);
                exit;
                 
            case 'lock_media':
                $mediaId = (int)$_POST['id'];
    
                try {
                    // Έλεγχος αν το πολυμέσο είναι ήδη κλειδωμένο από άλλον
                    $stmt = $pdo->prepare("SELECT locked_by FROM media WHERE id = ? AND locked_by IS NOT NULL AND locked_by != ?");
                    $stmt->execute([$mediaId, $user_id]);
        
                    if ($stmt->fetch()) {
                        die(json_encode(['error' => 'Το πολυμέσο είναι ήδη κλειδωμένο από άλλο χρήστη']));
                    }
        
                    // Κλείδωμα
                    $stmt = $pdo->prepare("UPDATE media SET locked_by = ?, locked_at = NOW() WHERE id = ?");
                    $stmt->execute([$user_id, $mediaId]);
        
                    echo json_encode(['success' => true]);
                } catch (PDOException $e) {
                    die(json_encode(['error' => $e->getMessage()]));
                }
                exit;

            case 'unlock_media':
                $mediaId = (int)$_POST['id'];
    
                try {
                    $stmt = $pdo->prepare("UPDATE media SET locked_by = NULL, locked_at = NULL WHERE id = ? AND locked_by = ?");
                    $stmt->execute([$mediaId, $user_id]);
        
                    echo json_encode(['success' => $stmt->rowCount() > 0]);
                } catch (PDOException $e) {
                    die(json_encode(['error' => $e->getMessage()]));
                }
                exit;
            case 'unlock_media':
                $mediaId = (int)$_POST['id'];
    
                try {
                    $stmt = $pdo->prepare("UPDATE media SET locked_by = NULL, locked_at = NULL WHERE id = ? AND locked_by = ?");
                    $stmt->execute([$mediaId, $user_id]);
        
                    echo json_encode(['success' => $stmt->rowCount() > 0]);
                } catch (PDOException $e) {
                    die(json_encode(['error' => $e->getMessage()]));
                }
                exit;
                    
            case 'add_collaborator':
                $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
                $permission = filter_input(INPUT_POST, 'permission', FILTER_SANITIZE_STRING);

                // FIND TO USER_ID APO TO EMAIL
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if (!$user){
                    throw new Exception('Ο χρήστης δεν βρέθηκε', 404);
                }
                
                $collab_user_id = $user['user_id'];
                
                //check if user einai idi synergatis
                $stmtCheck = $pdo->prepare("SELECT * FROM canvas_collaborators WHERE canva_id = ? AND user_id = ?");
                $stmtCheck->execute([$canva_id, $collab_user_id]);

                if ($stmtCheck->fetch()) {
                    throw new Exception('Ο χρήστης είναι ήδη συνεργάτης', 404);
                }
                
                //add to collaborator
                $stmtInsert = $pdo->prepare("INSERT INTO canvas_collaborators (canva_id, user_id, permission) VALUES(?, ?, ?)");
                $stmtInsert->execute([$canva_id, $collab_user_id, $permission]);
                
                echo json_encode(['success' => true]);
                exit;       

            case 'create_canvas':
                $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
                
                if(empty($name)){
                    throw new Exception('Το όνομα του πίνακα είναι απαραίτητο', 404);
                }
                
                $stmt = $pdo->prepare("INSERT INTO canvases (name, owner_id) VALUES (?, ?)");
                $stmt->execute([$name, $user_id]);
                
                echo json_encode([
                    'success' => true,
                    'canva_id' => $pdo->lastInsertId()
                ]);
                exit;
            case'create_canvas':
                $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);

                //dimoyrgia monadikoy token
                $share_token = bin2hex(random_bytes(16));
                $token_expires = date('Y-m-d H:i:s', strtotime('+30 days')); // Ισχύει 30 μέρεςena mina

                $stmt = $pdo->prepare ("SELECT INTO canvases (name, owner_id, share_token, token_expires_at)
                                        VALUES (?,?,?,?)");
                $stmt->execute([$name, $user_id, $share_token, $token_expires_at]);
                
                echo json_encode([
                    'success' => true,
                    'canva_id' => $pdo->lastInsertId(),
                    'share_token' =>$share_token,
                    'share_url' => "https://noteapp/share.php?token=" . $share_token
                ]);
                exit;
            case 'update_position':
                $notes = json_decode($_POST['notes'], true);
                
                if (!is_array($notes)) {
                    throw new Exception('Μη έγκυρα δεδομένα θέσης', 400);
                }
                
                foreach ($notes as $note) {
                    if (!isset($note['id'], $note['position_x'])) {
                        continue;
                    }
                    
                    $stmt = $pdo->prepare("
                        UPDATE notes 
                        SET position_x = ? 
                        WHERE note_id = ? AND owner_id = ?
                    ");
                    $stmt->execute([
                        (int)$note['position_x'],
                        (int)$note['id'],
                        $user_id
                    ]);
                }

                echo json_encode(['success' => true]);
                exit;
                case 'upload_media':
                if (!isset($_FILES['media'])) {
                    throw new Exception('Δεν βρέθηκε αρχείο προς μεταφόρτωση', 400);
                }
                //idioktikitis pinaka
                $stmtOwner = $pdo->prepare("SELECT owner_id FROM canvases WHERE canva_id = ? ");
                $stmtOwner->execute ([$canva_id]);
                $real_owner_id = $stmtOwner->fetchColumn();
                
                if(!$real_owner_id) {
                    throw new Exception('ο πινακες δεν βρεθηκε', 404);
                }
                


                $file = $_FILES['media'];
                $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/';
                
                // Δημιουργία μοναδικού ονόματος αρχείου
                $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $file_name = uniqid() . '.' . $file_ext;
                $file_path = $upload_dir . $file_name;
                
                if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                    throw new Exception('Αποτυχία μεταφόρτωσης αρχείου', 500);
                }
                
                // Αποθήκευση στη βάση
                $stmt = $pdo->prepare("
                    INSERT INTO media 
                    (owner_id, type, data, position_x, position_y, canva_id, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $real_owner_id,
                    $user_id,
                    $file['type'],
                    '/uploads/' . $file_name,
                    0,
                    0,
                    $canva_id
                ]);
                
                echo json_encode([
                    'success' => true,
                    'id' => $pdo->lastInsertId(),
                    'file_path' => '/uploads/' . $file_name
                ]);
                exit;
                
            default:
                throw new Exception('Μη υποστηριζόμενη ενέργεια', 400);
        }
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Σφάλμα βάσης δεδομένων']);
        exit;
    } catch (Exception $e) {
        http_response_code($e->getCode() ?: 400);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="current-user-id" content="<?php echo $user_id; ?>">
    <meta name="current-username" content="<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>">
    <meta name="user-role" content="<?php echo $_SESSION['role'] ?? 'student'; ?>">
    <meta name="current-canva-id" content="<?php echo $canva_id; ?>">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <meta name="can-edit" content="<?php echo $can_edit ? 'true' : 'false'; ?>">
    <title><?php echo htmlspecialchars($canvas_name); ?> - Έξυπνες Σημειώσεις</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Quill Editor -->
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
    <!-- FullCalendar CSS -->
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.css' rel='stylesheet' />
    <!-- fullcalendar js-->
     <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- html2canvas -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <!-- jsPDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <!-- Word export -->
     <script src="https://cdn.jsdelivr.net/npm/file-saver@2.0.5/dist/FileSaver.min.js"></script>

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Quill Editor -->
    
   <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
   <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    

    
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.js'></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    const currentCanvaId = document.querySelector('meta[name="current-canva-id"]').content;
    if (!calendarEl) return;    
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'el',
        events: {
            url: 'get_due_dates.php',
            method: 'GET',
            extraParams: {
                canva_id: currentCanvaId
            },
            failure: function() {
                console.error('Αποτυχία φόρτωσης events');
                Swal.fire({
                    icon: 'error',
                    title: 'Σφάλμα',
                    text: 'Αποτυχία φόρτωσης ημερολογίου'
                });
            }
        },
        eventClick: function(info) {
            const noteId = info.event.id;
            const canvaId = currentCanvaId;
            
            fetch(`get_note.php?note_id=${noteId}&canva_id=${canvaId}`)
                .then(response => response.json())
                .then(note => {
                    const color = note.color || '#ffffff';
                    const icon = note.icon ? `<i class="bi bi-${note.icon}"></i>` : '';
                    const tag = note.tag || 'Χωρίς ετικέτα';
                    const date = note.due_date || 'Χωρίς προθεσμία';
                    const canvaName = info.event.extendedProps.canva_name || 'Άγνωστος Πίνακας';

                    const contentHTML = `
                        <div class="note-preview-body" style="background:${color}; border-radius:12px; padding:1rem;">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="fw-bold">${canvaName}</span>
                                <span class="badge bg-dark">${tag}</span>
                            </div>
                            <div style="font-family: ${note.font || 'Arial'}; white-space:pre-wrap;">
                                ${note.content}
                            </div>
                            <hr>
                            <div class="text-muted small">
                                Προθεσμία: ${date}
                            </div>
                        </div>
                    `;

                    document.getElementById('notePreviewContent').innerHTML = contentHTML;
                    new bootstrap.Modal(document.getElementById('notePreviewModal')).show();
                })
                .catch(error => {
                    console.error('Σφάλμα:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Σφάλμα',
                        text: 'Αποτυχία φόρτωσης σημείωσης'
                    });
                });

            info.jsEvent.preventDefault();
        }
    });

    calendar.render();
});  
    </script>
<!--modla emfninisi simeioseis pano sto imerolofio -->
<div class="modal fade" id="notePreviewModal" tabindex="-1" aria-labelledby="notePreviewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content shadow-lg">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="notePreviewModalLabel">Προεπισκόπηση Σημείωσης</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Κλείσιμο"></button>
      </div>
      <div class="modal-body" id="notePreviewContent">
        <p>Φόρτωση...</p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Κλείσιμο</button>
      </div>
    </div>
  </div>
</div>

       
        
    <link rel= "stylesheet" href="css/darkmode1.css">
    <link rel="stylesheet" href="css/7.css">

    <style>

         .app-container {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .app-header {
            background-color: #f8f9fa;
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .header-title h1 {
            margin: 0;
            font-size: 1.8rem;
        }
        
        .header-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .main-content {
            display: flex;
            flex: 1;
        }
        
        .sidebar {
            width: 280px;
            background-color: #f8f9fa;
            padding: 1rem;
            border-right: 1px solid #dee2e6;
            overflow-y: auto;
        }
        
        .sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .sidebar-title {
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .notes-board-container {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .board-header {
            padding: 1rem;
            background-color: #fff;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .board-title {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .board-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .notes-board {
            flex: 1;
            padding: 1rem;
            position: relative;
            background-color: #f0f2f5;
            overflow: auto;
            min-height: 500px;
        }
        
        .note-container {
            position: absolute;
            width: 250px;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            cursor: move;
            z-index: 10;
        }
        
        .note-content {
            height: 100%;
        }
        
        .note-toolbar {
            border-bottom: 1px solid rgba(0,0,0,0.1);
            padding-bottom: 0.5rem;
        }
        
        .media-item {
            position: absolute;
            background: white;
            border-radius: 8px;
            padding: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            z-index: 5;
        }
        
        .sidebar-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            display: none;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -280px;
                top: 0;
                bottom: 0;
                z-index: 1000;
                transition: left 0.3s ease;
            }
            
            .sidebar.open {
                left: 0;
            }
            
            .sidebar-toggle {
                display: block;
            }
            
            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .board-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .board-actions {
                flex-wrap: wrap;
            }
        }

        
    </style>
 <style>
  #videoPreview {
    max-height: 300px;
    overflow-y: auto;
  }

  /* Επίσης κάνε το modal να προσαρμόζεται σωστά */
  #mediaModal .modal-dialog {
    max-width: 900px;
  }

  #mediaModal .modal-body {
    overflow-y: auto;
    max-height: 70vh; /* 70% του ύψους της οθόνης */
  }

  #mediaModal .modal-footer {
    position: sticky;
    bottom: 0;
    background: #f8f9fa;
    z-index: 10;
  }

  #searchResults {
    background: white;
    border: 1px solid #ddd;
    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
    max-height: 300px;
    overflow-y: auto;
    top: 55px; /* Κάτω από το search bar */
    right: 10px;
    border-radius: 5px;
}

/* Το εφέ όταν βρίσκει τη σημείωση */
.highlight-found {
    outline: 4px solid #ffc107 !important;
    transition: outline 0.3s;
}

.search-item {
    white-space: nowrap;      /* Μην αλλάζεις γραμμή */
    overflow: hidden;         /* Κόψε ό,τι περισσεύει */
    text-overflow: ellipsis;  /* Βάλε αποσιωπητικά (...) */
    color: #333;
    font-size: 0.9rem;
}
</style>



    </head>
    <body>
        <div class="app-container">
            <!--header-->
            <header class="app-header">
                <div class="header-content">
                <div class="header-title">
                    <h1>Σημειώσεις Πινακα <span class="text-muted mb-0"><?php echo htmlspecialchars($canvas_name); ?></span>
   <?php 
        // Έλεγχος αν είναι Viewer (δεν είναι ούτε owner ούτε collaborator με edit rights)
        // Προσαρμοσε το logic ανάλογα με το πώς ορίζεις τον viewer στη βάση σου
        $isViewer = ($access_level === 'viewer' || $access_level === 'read_only');
        $isOwner = ($access_level === 'ownwer'); // Προσοχή στο typo 'ownwer' αν υπάρχει όντως στη βάση σου έτσι
        ?>

        <?php if ($isViewer): ?>
            <span class="text-warning ms-2" title="Μόνο προβολή (Read-only)" style="font-size: 0.7em;">
                <i class="bi bi-lock-fill"></i> Προβολη μονο
            </span>
            
        <?php endif; ?>
        <?php
        //elegxs an einia owneri collaboratoe apo to accesslevel 
        $isOwner = ($access_level === 'ownwer');
        ?>
        <?php if ($isOwner): ?>
        <span class="badge bg-dark text-white ms-2" style="font-size: 0.4em; letter-spacing: 1px;">
            <i class="bi bi-person-check-fill"></i> OWNER
        </span>
    <?php else: ?>
        <span class="badge bg-secondary text-white ms-2" style="font-size: 0.4em; letter-spacing: 1px;">
            <i class="bi bi-people-fill"></i> COLLABORATOR
        </span>
    <?php endif; ?>
    <div class="btn-group ms-3">
        <?php 
        // Ορισμός χρωμάτων βάσει του access_type που πήρες από τη βάση
        $badgeClass = 'bg-secondary';
        if (in_array($access_type, ['private', 'ιδιωτικό'])) $badgeClass = 'bg-warning text-dark';
        elseif (in_array($access_type, ['public', 'δημόσιο'])) $badgeClass = 'bg-success';
        elseif (in_array($access_type, ['shared', 'κοινόχρηστο'])) $badgeClass = 'bg-info text-dark';
        ?>
        <span class="badge <?php echo $badgeClass; ?> <?php echo $isOwner ? 'dropdown-toggle' : ''; ?>" 
              <?php echo $isOwner ? 'data-bs-toggle="dropdown" style="cursor:pointer;"' : ''; ?> 
              id="accessTypeBadge">
            <?php echo htmlspecialchars($access_type); ?>
        </span>
        <?php if ($isOwner): ?>
            <ul class="dropdown-menu shadow-sm">
                <li><a class="dropdown-item" href="#" onclick="changeAccessType('private')"><i class="bi bi-lock-fill me-2"></i>ιδιωτικό</a></li>
                <li><a class="dropdown-item" href="#" onclick="changeAccessType('public')"><i class="bi bi-globe me-2"></i>δημόσιο</a></li>
                <li><a class="dropdown-item" href="#" onclick="changeAccessType('shared')"><i class="bi bi-people-fill me-2"></i>κοινόχρηστο</a></li>
            </ul>
        <?php endif; ?>
    </div>
</h1>


<script>
    function changeAccessType(newType) {
    event.preventDefault();
    
    if (!confirm('Είστε σίγουρος ότι θέλετε να αλλάξετε τον τύπο πρόσβασης;')) {
        return;
    }
    
    const canvaId = <?php echo json_encode($canva_id ?? 0); ?>;
    
    // Έλεγχος αν το canvas_id είναι έγκυρο
    if (!canvaId || canvaId == 0) {
        alert('Σφάλμα: Μη έγκυρο ID πίνακα');
        return;
    }
    
    const formData = new FormData();
    formData.append('canva_id', canvaId);
    formData.append('new_access_type', newType);
    formData.append('action', 'change_access_type');
    
    console.log('Αποστολή δεδομένων:', {
        canva_id: canvaId,
        new_access_type: newType
    });
    
    fetch('canvases/update_canvas.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Κατάσταση response:', response.status);
        if (!response.ok) {
            throw new Error('Σφάλμα HTTP: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        console.log('Απάντηση από server:', data);
        if (data.success) {
            updateAccessBadge(newType);
            alert('Ο τύπος πρόσβασης άλλαξε επιτυχώς!');
        } else {
            alert('Σφάλμα: ' + (data.message || 'Αποτυχία αλλαγής τύπου πρόσβασης'));
        }
    })
    .catch(error => {
        console.error('Σφάλμα:', error);
        alert('Σφάλμα δικτύου: ' + error.message);
    });
}

function updateAccessBadge(type) {
    const badge = document.getElementById('accessTypeBadge');
    if (!badge) {
        console.error('Δεν βρέθηκε το badge element');
        return;
    }
    
    badge.classList.remove('bg-warning', 'bg-success', 'bg-info', 'bg-secondary');
    
    switch(type) {
        case 'private':
            badge.textContent = 'ιδιωτικό';
            badge.classList.add('bg-warning');
            badge.title = 'Ιδιωτικός πίνακας';
            break;
        case 'public':
            badge.textContent = 'δημόσιο';
            badge.classList.add('bg-success');
            badge.title = 'Δημόσιος πίνακας';
            break;
        case 'shared':
            badge.textContent = 'κοινόχρηστο';
            badge.classList.add('bg-info');
            badge.title = 'Κοινόχρηστος πίνακας';
            break;
        default:
            badge.textContent = type;
            badge.classList.add('bg-secondary');
            badge.title = 'Απροσδιόριστο';
    }
}
</script>
 <!-- Μήνυμα ενημέρωσης -->
<div id="updateMessage" class="alert mt-2" style="display: none;"></div>

    <a href="home.php" class="btn btn-sm btn-outline-secondary mt-2">
                                <i class="bi bi-arrow-left"></i> Πίσω
                                                 </a>                   
                    <i class="bi bi-people fs-1" role="button" data-bs-toggle="modal" data-bs-target="#addCollaboratorModal"></i>
                    
                    <h3 class="mb-0">Καλωσήρθες, <?= htmlspecialchars($_SESSION['username']) ?>!</h3>
                    <small class="text-muted mb-0">Διαχειρίσου σημειώσεις & πολυμέσα πάνω στον καμβά</small>
                </div>
                <div class="header-actions">
                    <label class="switch">
                        <input type="checkbox" id="darkModeTooggle">
                        <span class="slider round"></span>
                    </label>
                    <span class="theme-label" id="themeLabel">Light mode</span>
                    <?php if ($can_edit): ?>
                         <button type="button" class="btn btn-primary d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#mediaModal">
                        <img src="images/photo.png" alt="Media preview" class="img-thumbnail" style="width: 40px; height: 40px;">
                       Εισαγωγή πολυμέσου
                        </button>
                            <a href="include/drawingcanvas.php" class="btn btn-info btn-large">
                                        <i class="bi bi-brush-fill"></i> Πίνακας ζωγραφικής
                                        </a>  
                         <button class="btn btn-info btn-large" data-bs-toggle="modal" data-bs-target="#newCanvasModal"> 
                         <i class="bi bi-plus-lg fs-4"></i> Νέος Πίνακας
                            </button>
                  <?php endif; ?>
        </div>
        </div>
        </header>

     <main class="main-content">
            <!-- Sidebar -->
             
            <aside class="sidebar" id="sidebar">
                <a href="public_canvases.php" class="btn btn-sm btn-info">
                        Προβολή των δημόσιων πινάκων
                    </a>
                      
                    <div style="height: 28px;"></div>
                    <a href="/noteapp/api/canva/shared_canvases.php?access_token=<?php echo $_SESSION['public_token'] ?? ''; ?>" class="btn btn-sm btn-warning">
                  Προβολή των κοινόχρηστων πινάκων
                   </a>
                    <hr>
                
                <div class="sidebar-header">
                    <hr>
                    <hr>
                    <div class="sidebar-title">Οι πίνακες μου</div>
                    <hr>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#newCanvasModal">
                        <i class="bi bi-plus"></i> Νέος
                    </button>
                </div>
                
                
                <input type="text" class="form-control mb-3" placeholder="Αναζήτηση πινάκων..." id="searchCanvases">
                
                <ul class="list-group" id="canvasesList">
                    <?php
                    $stmt = $pdo->prepare("SELECT canva_id, name, access_type FROM canvases WHERE owner_id = ?");
                    $stmt->execute([$user_id]);
                    
                    while ($canvas = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $active = ($canvas['canva_id'] == $canva_id) ? 'active' : '';
                        $accessType = htmlspecialchars($canvas['access_type'] ?? '');
                        
                        // Επιλογή badge ανάλογα με το access_type
                        $badgeClass = 'bg-secondary';
                        switch ($accessType) {
                            case 'public':
                                $badgeClass = 'bg-success';
                                break;
                            case 'private':
                                $badgeClass = 'bg-danger';
                                break;
                            case 'shared':
                                $badgeClass = 'bg-warning text-dark';
                                break;
                        }            
                        echo '<li class="list-group-item ' . $active . '">
                                <a class="canvas-link fw-bold" href="board.php?id=' . $canvas['canva_id'] . '">' . htmlspecialchars($canvas['name']) . '</a>
                                <span class="badge ' . $badgeClass . ' ms-2">' . $accessType . '</span>
                                <button class="btn btn-sm btn-danger float-end delete-canvas" data-id="' . $canvas['canva_id'] . '">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-secondary edit-name" data-id="' . $canvas['canva_id'] . '">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                              </li>';
                    }
                    ?>
                </ul>
                <div style="height: 28px;"></div>
                
                <?php if($has_notes): ?>
                    <div id='calendar' class="mt-4"></div>
                <?php endif; ?>
                <div style="height: 28px;"></div>
                
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <i class="bi bi-people-fill"></i> Συνεργάτες
                    </div>
                    <div class="card-body">
                        <ul class="list-group">
                            <?php foreach ($collaborators as $collab): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <?php
                                        // Ορίζουμε τη βασική διαδρομή και το default avatar
                                        $avatarPath = '/noteapp/uploads/avatars/';
                                        $defaultAvatar = '/noteapp/images/default-avatar.png';
                                        
                                        // Ελέγχουμε αν ο χρήστης έχει avatar και αν το αρχείο υπάρχει
                                        $userAvatar = !empty($collab['avatar']) ? $avatarPath . htmlspecialchars($collab['avatar']) : $defaultAvatar;
                                        
                                        // Επιπλέον έλεγχος ύπαρξης αρχείου (προαιρετικό)
                                        if (!empty($collab['avatar']) && !file_exists($_SERVER['DOCUMENT_ROOT'] . $avatarPath . $collab['avatar'])) {
                                            $userAvatar = $defaultAvatar;
                                        }
                                        ?>                        
                                        <img src="<?php echo $userAvatar; ?>" 
                                             class="rounded-circle me-2 avatar-hover" width="30" height="30" 
                                             alt="<?php echo htmlspecialchars($collab['username']); ?>">
                                        <?php echo htmlspecialchars($collab['username']); ?>
                                    </div>
                                    
                                    <?php if ($_SESSION['user_id'] == $access['owner_id']): ?>
                                        <button class="btn btn-sm btn-danger p-0 remove-collaborator" 
                                                data-user-id="<?php echo $collab['user_id']; ?>"
                                                style="width: 20px; height: 20px; border-radius: 50%;">
                                            <i class="bi bi-x" style="font-size: 10px;"></i>
                                        </button>
                                    <?php endif; ?>
                                    <span class="badge bg-<?php echo $collab['permission'] === 'edit' ? 'warning' : 'secondary'; ?>">
                                        <?php echo $collab['permission'] === 'edit' ? 'Επεξεργασία' : 'Προβολή'; ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <div class="card mt-4">
                                <div class="card-header bg-success text-white">
                                     <i class="bi bi-share"></i> Κοινή Χρήση με Link
                                       </div>
                                           <div class="card-body">
                                                    <?php  // Βρες το token του τρέχοντος πίνακα
                                                    $stmtToken = $pdo->prepare("SELECT share_token FROM canvases WHERE canva_id = ?");
                                                    $stmtToken->execute([$canva_id]);
                                                    $tokenData = $stmtToken->fetch();
                                                    $share_token = $tokenData['share_token'] ?? '';
                                                    if (empty($share_token)) {
                                                        // Δημιούργησε token αν δεν υπάρχει
                                                        $new_token = bin2hex(random_bytes(16));
                                                        $stmtUpdate = $pdo->prepare("UPDATE canvases SET share_token = ? WHERE canva_id = ?");
                                                        $stmtUpdate->execute([$new_token, $canva_id]);
                                                        $share_token = $new_token;
                                                          }
                                                    $share_url = "https://localhost/noteapp/share.php?token=" . $share_token;
                                                    //?>   
                                                    <div class="mb-3">
                                                         <label class="form-label">Σύνδεσμος Κοινής Χρήσης:</label>
                                                          <div class="input-group">
                                                                <input type="text" class="form-control" id="shareLink" 
                                                                                    value="<?php echo $share_url; ?>" readonly>
                                                                <button class="btn btn-outline-primary" onclick="copyShareLink()">
                                                                     <i class="bi bi-copy"></i> Αντιγραφή</button>
                                                           </div>
                                                                <small class="text-muted">Αυτός ο σύνδεσμος επιτρέπει προβολή χωρίς σύνδεση</small>
                                                            </div> 

                                                   <div class="mb-3">         
                                                      <label class="form-label">Ρυθμίσεις πρόσβασης:</label>
                                                      <select class="form-select" id="tokenAccessType" onchange="updateTokenAccess()">
                                                        <option value="view" selected>Προβολή μόνο</option>
                                                            <option value="edit">Επεξεργασία (απαιτεί σύνδεση)</option>
                                                         </select>
                                                                </div>     
                                                          <button class="btn btn-sm btn-danger" onclick="regenerateToken()">
                                                          <i class="bi bi-arrow-clockwise"></i> Ανανέωση Token
                                                             </button>
                                                    </div>
                        </div>
                                                               
<script>
function copyShareLink() {
    const input = document.getElementById('shareLink');
    input.select();
    document.execCommand('copy');
    alert('Ο σύνδεσμος αντιγράφηκε!');
}

async function regenerateToken() {
    if (!confirm('Θέλετε να δημιουργήσετε νέο token; Οι παλιοί σύνδεσμοι θα σταματήσουν να λειτουργούν.')) return;
    
    try {
        const response = await fetch('manage_token.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                canva_id: <?php echo $canva_id; ?>,
                action: 'regenerate'
            })
        });
        
        const result = await response.json();
        if (result.success) {
            document.getElementById('shareLink').value = result.new_url;
            alert('Νέο token δημιουργήθηκε!');
        }
    } catch (error) {
        alert('Σφάλμα: ' + error.message);
    }
}

async function updateTokenAccess() {
    const accessType = document.getElementById('tokenAccessType').value;
    
    await fetch('manage_token.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            canva_id: <?php echo $canva_id; ?>,
            action: 'update_access',
            access_type: accessType
        })
    });
}
</script>
                    </div>
                </div>
            </aside>

             <!-- Notes Board -->
            <section class="notes-board-container">
                
                <div class="d-flex align-items-center w-100 p-3">
                    <form class="d-flex ms-auto" id="searchForm" role="search">
                        <input class="form-control me-2" type="search" id="searchInput" placeholder="Αναζήτηση ονόματος καμβά" aria-label="Search">
                        <button class="btn btn-outline-success" type="submit">Αναζήτηση</button>
                    </form>
                    <div id="searchResults" style="position: absolute; z-index: 1000; width: 300px; display: none;"></div>
                    <div class="dropdown ms-3">
                    <button class="btn btn-white shadow-sm border rounded-circle position-relative" type="button" id="notifBell" data-bs-toggle="dropdown" style="width: 45px; height: 45px;">
                    <i class="bi bi-bell-fill text-secondary fs-5"></i>
                    <span id="notif-count" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none">
                        0
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2" aria-labelledby="notifBell" style="width: 320px; border-radius: 12px;">
                    <li><h6 class="dropdown-header fw-bold py-3 border-bottom"><i class="bi bi-clock-history me-2"></i>Εκκρεμότητες Λήξης</h6></li>
                    <div id="notif-content" style="max-height: 350px; overflow-y: auto;">
                        <li class="p-4 text-center text-muted">
                            <i class="bi bi-check2-circle fs-2 d-block mb-2 text-success"></i>
                            <small>Όλα έτοιμα! Δεν υπάρχουν επείγουσες σημειώσεις.</small>
                        </li>
                    </div>
                </ul>
                </div>

                <div class="search-box p-3"></div>
                </div>
                <hr>
                <div class="board-header">
                   
                    <div class="board-title"><?php echo $canvas_name; ?> </div>
                    <div class="board-actions">
                        
                        <button id="exportAsImage" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-image"></i> Εξαγωγή ως Εικόνα
                        </button>
                        <button id="exportAsPDF" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-file-pdf"></i> Εξαγωγή ως PDF
                        </button>
                        <!-- TXT Button -->
                        <button id="exportAsText" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-file-earmark-text"></i> Εξαγωγή ως TXT
                        </button>
                        <!-- Word Button -->
                        <button id="exportWordBtn" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-file-word"></i> Εξαγωγή σε Word
                        </button>
                        
                        <button id="zoomIn" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-zoom-in"></i> Zoom In
                        </button>
                        <button id="zoomOut" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-zoom-out"></i> Zoom Out
                        </button>
                    </div>
                </div>

           <div class="notes-board" id="notesBoard">
                    <!-- Notes from database -->
                    <?php foreach ($notes as $note): ?>
                        <div class="note-container" style="background-color: <?php echo $note['color']; ?>; left: <?php echo $note['position_x'] ?? 100; ?>px; top: <?php echo $note['position_y'] ?? 100; ?>px;"
                             data-note-id="<?php echo $note['note_id']; ?>"                         
                             data-locked-by="<?php echo $note['locked_by'] ?? ''; ?>">
                            
                            <?php if (!empty($note['locked_by'])): ?>
                                <div class="lock-indicator" 
                                     style="position: absolute; 
                                            top: -12px; 
                                            right: 5px; 
                                            background: #ffc107; 
                                            padding: 2px 5px; 
                                            border-radius: 2px; 
                                            font-size: 15px;
                                            color: <?php echo ($note['locked_by'] == 1) ? '#ff0000' : '#0000ff'; ?>">
                                    🔒 <?php echo htmlspecialchars($note['locked_by_name'] ?? 'Κλειδωμένο'); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="note-content">
                                <div class="note-toolbar d-flex justify-content-between align-items-center mb-2">
                                    <div class="badge-group">
                                        <?php if (!empty($note['tag'])): ?>
                                            <span class="badge bg-dark"><?php echo htmlspecialchars($note['tag']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if($can_edit):?>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-light edit-btn">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <!--button trash-->
                                        <button class="btn btn-sm btn-danger delete-btn">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                         <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if (!empty($note['icon'])): ?>
                                    <i class="bi bi-<?php echo htmlspecialchars($note['icon']); ?> float-end fs-5 mb-2"></i>
                                <?php endif; ?>
                                
                                <div class="ql-editor border-top">  <!-- αφαίρεση border -->    
                                    <?php echo $note['content']; ?>
                                </div>
                                
                                <?php if (!empty($note['due_date'])): ?>
                                    <div class="mt-3 small text-muted">
                                        Προθεσμία: <?php echo date('d/m/Y', strtotime($note['due_date'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                  
        <!-- Mobile Sidebar Toggle -->
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="bi bi-list"></i>
        </button>
    </div>

    <!-- Modal Δημιουργίας Σημείωσης -->
    <div class="modal fade" id="noteModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info">
                    <h5 class="modal-title">Δημιουργία Σημείωσης</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="noteForm">
                        <!-- Εργαλειοθήκη Rich Text Editor -->
                        <div id="toolbar-container" class="mb-2">
                            <span class="ql-formats">
                                <button class="ql-bold"></button>
                                <button class="ql-italic"></button>
                                <button class="ql-underline"></button>
                            </span>
                            <span class="ql-formats">
                                <button class="ql-list" value="ordered"></button>
                                <button class="ql-list" value="bullet"></button>
                            </span>
                            <span class="ql-formats">
                                <select class="ql-color"></select>
                                <select class="ql-background"></select>
                            </span>
                                                    </div>
                        <div id="editor" style="height: 200px; border: 1px solid #ddd;"></div>

                        <div class="row mt-3 g-3">
                            <div class="col-md-6">
                                <label class="form-label">Ετικέτα</label>
                                <input type="text" id="noteTag" class="form-control" placeholder="Προσθέστε ετικέτα">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Ομάδα</label>
                                <select class="form-select" id="groupSelect">
                                   
                                    <?php
                                    $stmtGroups = $pdo->prepare("SELECT group_id, group_name FROM groups WHERE user_id = ?");
                                    $stmtGroups->execute([$user_id]);
                                    while ($group = $stmtGroups->fetch(PDO::FETCH_ASSOC)) {
                                        echo '<option value="' . $group['group_id'] . '">' . htmlspecialchars($group['group_name']) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class ="col -md - 6">
                                <label class = "form -label "> πίνακα </label>
                                <select class ="form-select" id="CanvasSelect">
                                  
                                    <?php
                                    try {
                                        $stmtCanvas = $pdo->prepare("SELECT canva_id, name FROM canvases WHERE owner_id = ?");
                                        $stmtCanvas->execute([$user_id]);
                                        while ($canvas = $stmtCanvas->fetch(PDO::FETCH_ASSOC)) {
                                            $selected = ($canvas['canva_id'] == $canva_id) ? 'selected' : '';
                                            echo '<option value="' . $canvas['canva_id'] . '" ' . $selected . '>' 
                                                . htmlspecialchars($canvas['name']) . '</option>';
                                        }
                                    } catch (PDOException $e) {
                                        echo '<option disabled>Σφάλμα φόρτωσης</option>';
                                    }                                    ?>
                        </select>
                            </div>                            
                            <div class="col-md-6">
                                <label class="form-label">Εικονίδιο</label>
                                <select id="noteIcon" class="form-select">
                                    <option value="">— Χωρίς εικονίδιο —</option>
                                    <option value="star">⭐ Αστέρι</option>
                                    <option value="heart">❤️ Καρδιά</option>
                                    <option value="bell">🔔 Κουδούνι</option>
                                    <option value="pin">📌 Πινέζα</option>
                                    <option value="tag">🏷️ Ετικέτα</option>
                                </select>
                            </div>                            
                            <div class="col-md-6">
                                <label class="form-label">Γραμματοσειρά</label>
                                <select class="form-select" id="Font">
                                    <option value="Arial">Arial</option>
                                    <option value="Times New Roman">Times New Roman</option>
                                    <option value="Verdana">Verdana</option>
                                    <option value="Georgia">Georgia</option>
                                </select>
                            </div>
                                 <div class="col-md-6">
                                <label class="form-label">Εμφανιση ως</label>
                                <input type="date" id="due_date" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"> Χρωμα</label>
                                <input type= "color" id="noteColor" class="form-control form-control-color" value="#ffffff"></input> 
                            </div>
                            <div class="col-md-6">
                                
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg"></i> Ακύρωση
                    </button>
                    <button type="button" class="btn btn-primary" id="saveNote">
                        <i class="bi bi-save"></i> Αποθήκευση
                    </button>
                </div>
            </div>
        </div>
    </div>
<!--modal new vcrate kpinaka-->
<div class="modal fade" id="newCanvasModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Δημιουργία Νέου Καμβά</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="newCanvasForm">
                    <div class="mb-3">
                        <label for="canvasName" class="form-label">Όνομα Καμβά </label>
                        <input type="text" class="form-control" id="canvasName" required
                               placeholder="Π.χ. Μαθήματα Φυσικής">
                    </div>
                    <div class="mb-3">
                        <label for="canvasCategory" class="form-label">Κατηγορία </label>
                        <select class="form-select" id="canvasCategory" required>
                            <option value="Εκπαίδευση">Εκπαίδευση</option>
                            <option value="Μάρκετινγκ">Μάρκετινγκ</option>
                            <option value="Προσωπικό">Προσωπικό</option>
                            <option value="Εργασία">Εργασία</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="canvasAccess" class="form-label">Επίπεδο Πρόσβασης </label>
                        <select class="form-select" id="canvasAccess" required>
                            <option value="private">Ιδιωτικό (μόνο εγώ)</option>
                            <option value="public">Δημόσιο (ορατό σε όλους)</option>
                            <option value="shared">Κοινοχρηστος σε όλους</option>
                        </select>
                    </div>
                </form>
                <div class="alert alert-info mt-3">
                    </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Άκυρο</button>
                <button type="button" class="btn btn-primary" id="createCanvasBtn">
                    <span id="submitText">Δημιουργία</span>
                    <span id="loadingSpinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
                </button>
            </div>
        </div>
    </div>
</div>
<!-- Modal Προσθήκης Συνεργάτη -->
<div class="modal fade" id="addCollaboratorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info">
                <h5 class="modal-title">Προσθήκη Συνεργάτη</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addCollaboratorForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Email Συνεργάτη</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Δικαιώματα</label>
                        <select class="form-select" name="permission" required>
                            <option value="view">Προβολή</option>
                            <option value="edit">Επεξεργασία</option>
                        </select>
                    </div>
                </div>                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Ακύρωση</button>
                    <button type="submit" class="btn btn-primary">Προσθήκη</button>
                </div>
            </form>
        </div>
    </div>
</div>
   <!--media modal-texteditor-->
   <div class="modal fade" id="mediaModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg">
      <!-- Header -->
      <div class="modal-header bg-light">
        <h5 class="modal-title fw-bold text-dark">Προσθήκη Πολυμέσου</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <!-- Form -->
      <form id="mediaForm" enctype="multipart/form-data">
        
                <div class="modal-body pb-0">
            <div class="row mb-3">
            <div class="col-md-6 d-none">
              <label for="groupSelect" class="form-label">Ομάδα</label>
              <select class="form-select" id="groupSelect" name="group_id" required>
                <?php
                $stmtGroups = $pdo->prepare("SELECT group_id, group_name FROM groups WHERE user_id = ?");
                $stmtGroups->execute([$user_id]);
                while ($group = $stmtGroups->fetch(PDO::FETCH_ASSOC)) {
                  echo '<option value="' . htmlspecialchars($group['group_id']) . '">' 
                      . htmlspecialchars($group['group_name']) . '</option>';
                }
                ?>
              </select>
            </div>
            <div class="col-md-6 d-none">
              <label class="form-label">Πίνακας</label>
              <select class="form-select" id="CanvasSelect" name="canva_id">
                <?php
                try {
                  $stmtCanvas = $pdo->prepare("SELECT canva_id, name FROM canvases WHERE owner_id = ?");
                  $stmtCanvas->execute([$user_id]);
                  while ($canvas = $stmtCanvas->fetch(PDO::FETCH_ASSOC)) {
                    $selected = ($canvas['canva_id'] == $canva_id) ? 'selected' : '';
                    echo '<option value="' . $canvas['canva_id'] . '" ' . $selected . '>' 
                        . htmlspecialchars($canvas['name']) . '</option>';
                  }
                } catch (PDOException $e) {
                  echo '<option disabled>Σφάλμα φόρτωσης</option>';
                }
                ?>
              </select>
            </div>
          </div>
          <!-- Tabs -->
          <ul class="nav nav-tabs nav-tabs-bordered mb-4" role="tablist">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabImage"><i class="bi bi-card-image"></i> Εικόνα</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabVideo"><i class="bi bi-file-earmark-play-fill"></i> Βίντεο</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabFile"><i class="bi bi-file-earmark-plus"></i> Αρχείο</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabNote"><i class="bi bi-file-text"></i> Σημείωση</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabRichNote"><i class="bi bi-chat-right-text"></i> Πλούσια Σημείωση</a></li>
          </ul>
          <div class="tab-content">
            <!-- Εικόνα -->
            <div class="tab-pane fade show active" id="tabImage">
              <label class="form-label">Ανεβάστε εικόνα</label>
              <input type="file" class="form-control" accept="image/*" id="imageUpload" name="image">
              <div class="mt-2" id="imagePreview"></div>
            </div>
            <!-- Βίντεο -->
            <div class="tab-pane fade" id="tabVideo">
              <label class="form-label">URL YouTube/Vimeo</label>
              <input type="url" class="form-control mb-3" id="videoUrl" name="video_url">
              <label class="form-label">Ή ανεβάστε αρχείο</label>
              <input type="file" class="form-control" accept="video/*" id="videoUpload" name="video_file">
              <div class="mt-2" id="videoPreview"></div>
            </div>
            <div class="tab-pane fade" id="tabFile">
                <label class="form-label"> Επιλεξτε αρχειο</label>
                 <input type="file" class="form-control" id="fileUpload" name="document">
                 <div class="form-text">Μέγιστο μέγεθος: 25MB</div>

                 <div class="mt-2" id="filePreview"></div>
                
            </div>
            
            <!-- Σημείωση -->
            <div class="tab-pane fade" id="tabNote">
              <label class="form-label">Κείμενο σημείωσης</label>
              <textarea class="form-control" rows="5" id="noteText" name="note_text"></textarea>
               <div class="mt-2" id="notePreview"></div>
            </div>
            <!-- Πλούσια Σημείωση -->
            <div class="tab-pane fade" id="tabRichNote">
            <div class="mb-3">
               <label class="form-label">Περιεχόμενο</label>
            <div id="editNoteEditor"></div>
              <input type="hidden" id="richTextContent" name="rich_text">
            </div>
  

              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Ετικέτα</label>
                  <input type="text" id="richTextTag" class="form-control" placeholder="Προσθέστε ετικέτα">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Εικονίδιο</label>
                  <select id="richTextIcon" class="form-select">
                    <option value="">— Χωρίς εικονίδιο —</option>
                    <option value="star">⭐ Αστέρι</option>
                    <option value="heart">❤️ Καρδιά</option>
                    <option value="bell">🔔 Κουδούνι</option>
                    <option value="pin">📌 Πινέζα</option>
                    <option value="tag">🏷️ Ετικέτα</option>
                  </select>
                </div>               
                <div class="col-md-6">
                  <label class="form-label">Γραμματοσειρά</label>
                  <select class="form-select" id="richTextFont">
                    <option value="Arial">Arial</option>
                    <option value="Times New Roman">Times New Roman</option>
                    <option value="Verdana">Verdana</option>
                    <option value="Georgia">Georgia</option>
                  </select>
                </div>               
                <div class="col-md-6">
                  <label class="form-label">Ημερομηνία</label>
                  <input type="date" id="richTextDate" class="form-control">
                </div>
                 <div class="col-md-6">
                  <label class="form-label">Χρώμα</label>
                  <input type="color" id="richTextColor" class="form-control form-control-color" value="#fff2a8">
                </div>
              </div>
            </div>
          </div>
          <!-- Σχόλιο -->
          <div class="mb-3 mt-1">
            <label class="form-label">Σχόλιο (προαιρετικό)</label>
            <textarea class="form-control" id="mediaComment" name="comment" rows="2"></textarea>
          </div>
        </div>
        <!-- Footer -->
        <div class="modal-footer bg-light sticky">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Άκυρο</button>
          <button type="button" class="btn btn-primary" id="insertMediaBtn">Εισαγωγή</button>
        </div>
      </form>
    </div>
  </div>
</div>
<!-- Include Quill JS -->


<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet" />
<script src="https://cdn.quilljs.com/2.0.3/quill.js"></script>





<!-- Modal Επεξεργασίας Πολυμέσου --><div class="modal fade" id="editMediaModal" tabindex="-1" aria-labelledby="editMediaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editMediaModalLabel">Επεξεργασία Πολυμέσου</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <!-- Tabs για διαφορετικούς τύπους πολυμέσων -->
                <ul class="nav nav-tabs" id="mediaTypeTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="image-tab" data-bs-toggle="tab" data-bs-target="#image" type="button" role="tab" aria-controls="image" aria-selected="true">
                            <i class="bi bi-image"></i> Εικόνα
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="note-tab" data-bs-toggle="tab" data-bs-target="#note" type="button" role="tab" aria-controls="note" aria-selected="false">
                            <i class="bi bi-sticky"></i> Σημείωση
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="file-tab" data-bs-toggle="tab" data-bs-target="#file" type="button" role="tab" aria-controls="file" aria-selected="false">
                            <i class="bi bi-file-earmark"></i> Αρχείο
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="video-tab" data-bs-toggle="tab" data-bs-target="#video" type="button" role="tab" aria-controls="video" aria-selected="false">
                            <i class="bi bi-play-btn"></i> Βίντεο
                        </button>
                    </li>
                </ul>

                <!-- Περιεχόμενο Tabs -->
                <div class="tab-content mt-3" id="mediaTypeContent">
                    <!-- Tab Εικόνας -->
                    <div class="tab-pane fade show active" id="image" role="tabpanel" aria-labelledby="image-tab">
                        <div class="mb-3">
                            <label for="editImageUpload" class="form-label">Επιλογή νέας εικόνας</label>
                            <input class="form-control" type="file" id="editImageUpload" accept="image/*">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Προεπισκόπηση:</label>
                            <div id="editImagePreviewContainer">
                                <small class="text-muted">Η προεπισκόπηση θα εμφανιστεί εδώ...</small>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="editImageComment" class="form-label">Σχόλιο:</label>
                            <textarea class="form-control" id="editImageComment" rows="3"></textarea>
                        </div>
                    </div>

                    <!-- Tab Σημείωσης - Βελτιωμένο -->
                    <div class="tab-pane fade" id="note" role="tabpanel" aria-labelledby="note-tab">
                        <div class="mb-3">
                            <label for="editNoteContent" class="form-label">Περιεχόμενο:</label>
                            <textarea class="form-control" id="editNoteContent" rows="10" placeholder="Γράψτε το περιεχόμενο της σημείωσης..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Προεπισκόπηση Σημείωσης:</label>
                            <div id="editNotePreviewContainer">
                                <small class="text-muted">Η προεπισκόπηση θα εμφανιστεί εδώ...</small>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="editNoteComment" class="form-label">Σχόλιο:</label>
                            <textarea class="form-control" id="editNoteComment" rows="3"></textarea>
                        </div>
                    </div>

                    <!-- Tab Αρχείου -->
                    <div class="tab-pane fade" id="file" role="tabpanel" aria-labelledby="file-tab">
                        <div class="mb-3">
                            <label for="editFileUpload" class="form-label">Αντικατάσταση αρχείου</label>
                            <input class="form-control" type="file" id="editFileUpload">
                            <div class="form-text">Υποστηριζόμενοι τύποι: Word (.doc, .docx), PDF (.pdf), και άλλα</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Προεπισκόπηση Αρχείου:</label>
                            <div id="editFilePreviewContainer">
                                <small class="text-muted">Η προεπισκόπηση θα εμφανιστεί εδώ...</small>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="editFileComment" class="form-label">Σχόλιο:</label>
                            <textarea class="form-control" id="editFileComment" rows="3"></textarea>
                        </div>
                    </div>

                    <!-- Tab Βίντεο - Βελτιωμένο -->
                    <div class="tab-pane fade" id="video" role="tabpanel" aria-labelledby="video-tab">
                        <div class="mb-3">
                            <label for="editVideoUpload" class="form-label">Επιλογή βίντεο</label>
                            <input class="form-control" type="file" id="editVideoUpload" accept="video/*">
                            <div class="form-text">Υποστηριζόμενοι τύποι: MP4, WebM, OGG</div>
                            <label class="form-label mt-2">URL YouTube/Vimeo</label>
                            <input type="url" class="form-control mb-3" id="editVideoUrl" name="editvideo_url">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Προεπισκόπηση Βίντεο:</label>
                            <div id="editVideoPreviewContainer">
                                <small class="text-muted">Η προεπισκόπηση θα εμφανιστεί εδώ...</small>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="editVideoComment" class="form-label">Σχόλιο:</label>
                            <textarea class="form-control" id="editVideoComment" rows="3"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Ακύρωση</button>
                <button type="button" class="btn btn-primary" onclick="mediaManager.saveMediaChanges()">Αποθήκευση</button>
            </div>
        </div>
    </div>
</div>






<!-- Modal Επεξεργασίας Σημείωσης -->
<div class="modal fade" id="editNoteModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Επεξεργασία Σημείωσης</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editNoteForm">
                <input type="hidden" id="editNoteId" name="note_id">
                <div class="modal-body">
                    <!-- Πεδία Ομάδας και Πίνακα (κρυφά) -->
                    <div class="row mb-3 d-none">
                        <div class="col-md-6">
                            <label for="groupSelect" class="form-label">Ομάδα</label>
                            <select class="form-select" id="groupSelect" name="group_id">
                                <?php
                                $stmtGroups = $pdo->prepare("SELECT group_id, group_name FROM groups WHERE user_id = ?");
                                $stmtGroups->execute([$user_id]);
                                while ($group = $stmtGroups->fetch(PDO::FETCH_ASSOC)) {
                                    echo '<option value="' . htmlspecialchars($group['group_id']) . '">' 
                                        . htmlspecialchars($group['group_name']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Πίνακας</label>
                            <select class="form-select" id="CanvasSelect" name="canva_id">
                                <?php
                                try {
                                    $stmtCanvas = $pdo->prepare("SELECT canva_id, name FROM canvases WHERE owner_id = ?");
                                    $stmtCanvas->execute([$user_id]);
                                    while ($canvas = $stmtCanvas->fetch(PDO::FETCH_ASSOC)) {
                                        $selected = ($canvas['canva_id'] == $canva_id) ? 'selected' : '';
                                        echo '<option value="' . $canvas['canva_id'] . '" ' . $selected . '>' 
                                            . htmlspecialchars($canvas['name']) . '</option>';
                                    }
                                } catch (PDOException $e) {
                                    echo '<option disabled>Σφάλμα φόρτωσης</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <!-- Εργαλειοθήκη Rich Text Editor -->
                    <div id="editToolbarContainer" class="mb-2">

                     <span class="ql-formats">
                          
                                <select class="ql-size"></select>
                              </span>
                        <span class="ql-formats">

                            <button class="ql-bold"></button>
                            <button class="ql-italic"></button>
                            <button class="ql-underline"></button>
                             <button class="ql-strike"></button>
                             
                        </span>
                        <span class="ql-formats">
                            <button class="ql-list" value="ordered"></button>
                            <button class="ql-list" value="bullet"></button>
                        </span>
                        <span class="ql-formats">
                            <select class="ql-color"></select>
                            <select class="ql-background"></select>
                        </span>
                         <span class="ql-formats">
                             <button class="ql-script" value="sub"></button>
                             <button class="ql-script" value="super"></button>
                        </span>
                  <span class="ql-formats">
                       <button class="ql-header" value="1"></button>
                       <button class="ql-header" value="2"></button>
                       <button class="ql-blockquote"></button>
                       <button class="ql-code-block"></button>
                    </span>
                 <span class="ql-formats">
    
                 <button class="ql-indent" value="-1"></button>
                <button class="ql-indent" value="+1"></button>
                 </span>
  
 
                    </div>
                    
                    <div id="editNotesEditor" style="height: 200px; border: 1px solid #ddd;"></div>

                    <div class="row mt-3 g-3">
                        <div class="col-md-6">
                            <label class="form-label">Ετικέτα</label>
                            <input type="text" id="editNoteTag" class="form-control" name="tag">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Εικονίδιο</label>
                            <select id="editNoteIcon" class="form-select" name="icon">
                                <option value="">— Χωρίς εικονίδιο —</option>
                                <option value="star">⭐ Αστέρι</option>
                                <option value="heart">❤️ Καρδιά</option>
                                <option value="bell">🔔 Κουδούνι</option>
                                <option value="pin">📌 Πινέζα</option>
                                <option value="tag">🏷️ Ετικέτα</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Γραμματοσειρά</label>
                            <select class="form-select" id="editNoteFont" name="font">
                                <option value="Arial">Arial</option>
                                <option value="Times New Roman">Times New Roman</option>
                                <option value="Verdana">Verdana</option>
                                <option value="Georgia">Georgia</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Προθεσμία</label>
                            <input type="date" id="editNoteDueDate" class="form-control" name="due_date">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Χρώμα</label>
                            <input type="color" id="editNoteColor" class="form-control form-control-color" name="color" value="#ffffff">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Ακύρωση</button>
                    <button type="submit" class="btn btn-primary">Αποθήκευση</button>
                </div>
            </form>
        </div>
    </div>
</div>
    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/interactjs@1.10.27/dist/interact.min.js"></script>


          <!-- Bootstrap & jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>                      

   

<script>

 const toggle = document.getElementById('darkModeToggle');
const label = document.getElementById('themeLabel');

// Only run if the elements exist on the current page
if (toggle && label) {
    function updateTheme(isDark) {
        document.body.classList.toggle('dark', isDark);
        label.textContent = isDark ? 'Dark mode' : 'Light mode';
        localStorage.setItem('darkMode', isDark);
    }

    toggle.addEventListener('change', () => {
        updateTheme(toggle.checked);
    });

    // restore
    if (localStorage.getItem('darkMode') === 'true') {
        toggle.checked = true;
        updateTheme(true);
    }
}



    
   $('#searchForm').on('submit', function (e) {
    e.preventDefault(); // Σταματάει το αναίτιο refresh της σελίδας
    
    let searchTerm = $('#searchInput').val();
    // Παίρνουμε το id από το URL (π.χ. board.php?id=4)
    let urlParams = new URLSearchParams(window.location.search);
    let currentCanvaId = urlParams.get('id'); 

    if (searchTerm.trim() === "") return; // Μην κάνεις τίποτα αν είναι κενό

    $.ajax({
        url: 'search_notes.php', // Το νέο αρχείο που φτιάξαμε
        method: 'POST',
        data: { 
            query: searchTerm, 
            canva_id: currentCanvaId // Απαραίτητο για το search_notes.php
        },
        success: function (response) {
            // Εμφάνιση των αποτελεσμάτων στο div με id searchResults
            $('#searchResults').html(response).show(); 
        },
        error: function () {
            $('#searchResults').html('<div class="alert alert-danger">Σφάλμα σύνδεσης.</div>');
        }
    });
});window.focusNote = function(noteId) {
    // Ψάχνει το div που έχει το συγκεκριμένο data-note-id
    let targetNote = $(`.note-container[data-note-id="${noteId}"]`); 

    if (targetNote.length) {
        $('#searchResults').fadeOut();

        // Smooth scroll
        $('html, body').animate({
            scrollTop: targetNote.offset().top - 150,
            scrollLeft: targetNote.offset().left - 150
        }, 1000);

        // Εφέ επισήμανσης
        targetNote.addClass('highlight-found');
        setTimeout(() => targetNote.removeClass('highlight-found'), 3000);
    }
};
   
   </script>


<script>
    // Toggle sidebar mobile
document.getElementById('sidebarToggle').addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('open');
});

// Κλείσιμο sidebar όταν γίνεται κlick έξω από αυτό
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    
    if (sidebar.classList.contains('open') && 
        !sidebar.contains(event.target) && 
        !toggleBtn.contains(event.target)) {
        sidebar.classList.remove('open');
    }
});

// Κλείσιμο mobile menu όταν επιλέγεται κάποιο item
document.querySelectorAll('.mobile-toggle-menu li').forEach(item => {
    item.addEventListener('click', function() {
        document.querySelector('.mobile-toggle-menu').removeAttribute('open');
    });
});
</script>


    <script src="js/board.js"></script>       

    </body>
</html>
