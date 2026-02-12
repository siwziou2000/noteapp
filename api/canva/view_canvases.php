<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

// Αρχικοποίηση avatars για αποφυγή Undefined Variable Warnings
$defaultAvatar = '/noteapp/images/default-avatar.png';
$userAvatar = $defaultAvatar;

if (!isset($_GET['id'])) {
    header('Location: public_canvases.php');
    exit;
}

$canva_id = (int)$_GET['id'];

// Έλεγχος αν ο πίνακας είναι δημόσιος
try {
    $stmt = $pdo->prepare("
        SELECT c.*, u.username, u.avatar 
        FROM canvases c
        JOIN users u ON c.owner_id = u.user_id
        WHERE c.canva_id = ? AND c.access_type = 'public'
    ");
    $stmt->execute([$canva_id]);
    $canvas = $stmt->fetch();
    
    if (!$canvas) {
        header('Location: public_canvases.php');
        exit;
    }
    // Ορισμός του σωστού avatar αν υπάρχει
    if (!empty($canvas['avatar'])) {
        $userAvatar = '/noteapp/uploads/avatars/' . basename($canvas['avatar']);
    }
} catch (PDOException $e) {
    die("Σφάλμα βάσης δεδομένων: " . $e->getMessage());
}

// Ανάκτηση σημειώσεων
try {
    $stmt = $pdo->prepare("
        SELECT * FROM notes 
        WHERE canva_id = ?
        ORDER BY position_x ASC
    ");
    $stmt->execute([$canva_id]);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Σφάλμα φόρτωσης σημειώσεων: " . $e->getMessage());
}

// Ανάκτηση πολυμέσων
try {
    $stmt = $pdo->prepare("
        SELECT * FROM media 
        WHERE canva_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$canva_id]);
    $media = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $media = [];
}

// Ορισμός διαδρομών για avatar
// Ορισμός διαδρομών για avatar - Αρχικοποίηση ΠΡΙΝ από κάθε έλεγχο
$avatarPath = '/noteapp/uploads/avatars/';
$defaultAvatar = '/noteapp/images/default-avatar.png';
$userAvatar = $defaultAvatar; // Default τιμή για να μην είναι ποτέ undefined

if (!empty($canvas['avatar'])) {
    $potentialPath = $avatarPath . htmlspecialchars($canvas['avatar']);
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . $avatarPath . $canvas['avatar'];
    
    if (file_exists($fullPath)) {
        $userAvatar = $potentialPath;
    }
}
    
 
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    


    <title><?= htmlspecialchars($canvas['name']) ?> - Έξυπνες Σημειώσεις</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .note-container {
            position: absolute;
            width: 300px;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            background: #fff9c4;
            cursor: move !important;
            pointer-events: auto !important;
            z-index: 1000;
        }
        .note-container.dragging {
            opacity: 0.7;
            z-index: 1001;
            transform: rotate(2deg);
        }
        .rich-note {
            position: absolute;
            width: 350px;
            border-radius: 8px;
            background: white;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            cursor: move;
            z-index: 1000;
        }
        .rich-note.dragging {
            opacity: 0.7;
            z-index: 1001;
            transform: rotate(2deg);
        }
        .media-item {
            position: absolute;
            border-radius: 8px;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            cursor: move;
            max-width: 300px;
            z-index: 1000;
        }
        .media-item.dragging {
            opacity: 0.7;
            z-index: 1001;
            transform: rotate(2deg);
        }
        /* ΕΝΕΡΓΟΠΟΙΗΣΗ LINKS ΜΟΝΟ ΓΙΑ ΚΑΤΕΒΑΣΜΑ */
        .media-item a,
        .rich-note a,
        .note-container a {
            pointer-events: auto;
        }
        .owner-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        .avatar-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 50%;
        }
        .canvas-container {
            width: 100%;
            height: 70vh;
            position: relative;
            overflow: auto;
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            margin: 20px 0;
            min-height: 500px;
        }
        .media-item img,
        .media-item video {
            max-width: 100%;
            max-height: 200px;
            object-fit: contain;
        }
        .media-item .card,
        .rich-note .card,
        .note-container .card {
            border: none;
            margin: 0;
        }
        .rich-note-content {
            padding: 15px;
            max-height: 400px;
            overflow-y: auto;
        }
        .rich-note-content h1,
        .rich-note-content h2,
        .rich-note-content h3 {
            margin-top: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .rich-note-content ul,
        .rich-note-content ol {
            padding-left: 1.5rem;
        }
        .rich-note-content blockquote {
            border-left: 4px solid #dee2e6;
            padding-left: 1rem;
            margin-left: 0;
            color: #6c757d;
        }
        .rich-note-content table {
            width: 100%;
            border-collapse: collapse;
        }
        .rich-note-content table, 
        .rich-note-content th, 
        .rich-note-content td {
            border: 1px solid #dee2e6;
        }
        .rich-note-content th,
        .rich-note-content td {
            padding: 0.5rem;
        }
        .save-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            z-index: 10000;
            display: none;
        }
    </style>
</head>
<body>
    <div class="save-indicator" id="saveIndicator">
        <i class="bi bi-check-circle"></i> Η θέση αποθηκεύτηκε!
    </div>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1"><?= htmlspecialchars($canvas['name']) ?></h1>
                <div class="owner-info">
                    <img src="<?= $userAvatar ?>" 
                         class="avatar-img" 
                         alt="<?= htmlspecialchars($canvas['username']) ?>">
                    <span>Δημιουργός: <?= htmlspecialchars($canvas['username']) ?></span>
                </div>
            </div>
            <a href="public_canvases.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Πίσω
            </a>
        </div>

        <div class="canvas-container" id="notesBoard">
            <!-- Canvas content will be loaded here -->
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>

        document.addEventListener('DOMContentLoaded', function() {
            displayCanvasContent();
            initDragAndDrop();
        });

        function displayCanvasContent() {
            const canvas = document.getElementById('notesBoard');
            if (!canvas) {
                console.error('Canvas container not found');
                return;
            }

            // Clear canvas
            canvas.innerHTML = '';

            // Add notes from PHP
            <?php foreach($notes as $note): ?>
                try {
                    const noteElement = createNoteElement(<?= json_encode($note) ?>);
                    canvas.appendChild(noteElement);
                } catch (error) {
                    console.error('Error creating note element:', error);
                }
            <?php endforeach; ?>

            // Add media from PHP
            <?php foreach($media as $mediaItem): ?>
                try {
                    const mediaElement = createMediaElement(<?= json_encode($mediaItem) ?>);
                    canvas.appendChild(mediaElement);
                } catch (error) {
                    console.error('Error creating media element:', error);
                }
            <?php endforeach; ?>

            // Show message if no content
            if (canvas.children.length === 0) {
                const message = document.createElement('div');
                message.style.cssText = 'position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; color: #6c757d;';
                message.innerHTML = 'Δεν υπάρχουν σημειώσεις ή πολυμέσα σε αυτόν τον πίνακα';
                canvas.appendChild(message);
            }
        }
        
    function createNoteElement(note) {
    const div = document.createElement('div');
    div.className = 'note-container draggable';

    div.style.left = (note.position_x || 50) + 'px';
    div.style.top = (note.position_y || 50) + 'px';
    div.style.backgroundColor = note.color || '#fff9c4';
    div.setAttribute('data-note-id', note.note_id || note.id);
    div.setAttribute('data-type', 'note');

    // 1. Προετοιμασία TAG
    let tagSection = '';
    if (note.tag && note.tag.trim() !== '') {
        tagSection = `<span class="badge bg-dark mb-2" style="font-size: 0.7rem;">
                        <i class="bi bi-tag-fill me-1"></i>${escapeHtml(note.tag)}
                      </span>`;
    }

    // 2. Προετοιμασία ICON (Εδώ είναι η προσθήκη που ζήτησες)
    let iconSection = '';
    if (note.icon && note.icon.trim() !== '' && note.icon !== 'NULL') {
        // Αν η τιμή στη βάση ΔΕΝ ξεκινάει από bi-, το προσθέτουμε εμείς
        let iconClass = note.icon.startsWith('bi-') ? note.icon : 'bi-' + note.icon;
        iconSection = `<i class="bi ${escapeHtml(iconClass)} me-2 fs-4 text-dark"></i>`;
    }
    // 3. Προετοιμασία Deadline
    let deadlineSection = '';
    if (note.due_date && note.due_date !== '0000-00-00 00:00:00') {
        const dDate = new Date(note.due_date);
        const formattedDeadline = dDate.toLocaleString('el-GR', {
            day: '2-digit', month: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });
        const isExpired = new Date() > dDate;
        const badgeClass = isExpired ? 'bg-danger' : 'bg-warning text-dark';
        deadlineSection = `
            <div class="mt-2 p-1 rounded ${badgeClass}" style="font-size: 0.75rem; text-align: center; font-weight: bold;">
                <i class="bi bi-alarm-fill"></i> Λήξη: ${formattedDeadline}
            </div>`;
    }

    const hasHTML = note.content && /<[a-z][\s\S]*>/i.test(note.content);

    // ΤΕΛΙΚΗ ΔΟΜΗ HTML
    div.innerHTML = `
        <div class="note-content d-flex flex-column h-100">
            ${tagSection}

            <div class="main-body d-flex align-items-start mb-2">
                ${iconSection} <div class="text-content" style="flex-grow: 1; min-width: 0;">
                    ${hasHTML ? 
                        `<div class="rich-note-content p-0">${note.content}</div>` : 
                        `<p class="mb-0" style="white-space: pre-wrap; word-wrap: break-word;">${escapeHtml(note.content || '')}</p>`
                    }
                </div>
            </div>

            <div class="mt-auto">
                ${deadlineSection}
                <div class="mt-2 d-flex justify-content-end align-items-center border-top pt-1">
                    <span class="badge ${hasHTML ? 'bg-primary' : 'bg-secondary'}" style="font-size: 0.6rem;">
                        ${hasHTML ? 'Rich' : 'Simple'}
                    </span>
                </div>
            </div>
        </div>
    `;
    return div;
}
       
// Συνάρτηση που ανανεώνει το περιεχόμενο χωρίς refresh
async function refreshCanvasContent() {
    try {
        // Καλούμε ένα API ή το ίδιο το αρχείο για να πάρουμε τα φρέσκα δεδομένα
        const response = await fetch(`get_canvas_data.php?id=<?= $canva_id ?>`);
        const data = await response.json();

        if (data.notes) {
            data.notes.forEach(note => {
                // Βρίσκουμε τη σημείωση στην οθόνη μέσω του ID
                const existingNote = document.querySelector(`[data-note-id="${note.note_id || note.id}"]`);
                if (existingNote) {
                    // Αν υπάρχει, την αντικαθιστούμε με τη νέα έκδοση (που έχει το νέο due_date)
                    const newElement = createNoteElement(note);
                    existingNote.replaceWith(newElement);
                }
            });
        }
    } catch (error) {
        console.error("Σφάλμα στην αυτόματη ανανέωση:", error);
    }
}

// Εκτέλεση ελέγχου κάθε 5 δευτερόλεπτα (5000ms)
setInterval(refreshCanvasContent, 5000);

       function createMediaElement(media) {
    const div = document.createElement('div');
    
    // Έλεγχος αν είναι Rich Note
    const isRichNote = media.type === 'rich_note' || 
                      (media.data && media.data.includes('<') && 
                       (media.data.includes('<p>') || media.data.includes('<div')));

    div.className = isRichNote ? 'rich-note draggable' : 'media-item draggable';
    div.style.left = (media.position_x || 100) + 'px';
    div.style.top = (media.position_y || 100) + 'px';
    div.setAttribute('data-media-id', media.id);
    div.setAttribute('data-type', media.type);

    

    // --- Τμήμα Σχολίων/Περιγραφής (Εμφανίζεται κάτω από το media) ---
    // Αν το media.data περιέχει κείμενο που δεν είναι το URL του αρχείου
    let descriptionSection = '';
    if (media.comment && media.comment.trim() !== '') {
        descriptionSection = `
            <div class="p-2 border-top bg-light small" style="font-style: italic; color: #555;">
                <i class="bi bi-chat-left-text-fill text-primary"></i> 
                <strong>Σχόλιο:</strong> ${escapeHtml(media.comment)}
            </div>`;
    }

    if (isRichNote) {
        div.innerHTML = `
            <div class="card shadow border-0">
                <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-sticky-fill text-primary"></i> Σημείωση</h6>
                    <span class="badge bg-primary">Rich</span>
                </div>
                <div class="rich-note-content p-3">
                    ${media.data || 'Δεν υπάρχει περιεχόμενο'}
                </div>
            </div>`;
    } else {
        let mediaContent = '';
        const mediaId = media.id;
        
        switch (media.type) {
            case 'image':
                mediaContent = `
                    <div class="card shadow border-0">
                        <img src="${escapeHtml(media.data || '')}" class="card-img-top img-fluid" style="max-height: 200px; object-fit: cover;">
                        <div class="card-body p-2">
                            <p class="card-text small text-truncate mb-1">${escapeHtml(media.original_filename || 'Εικόνα')}</p>
                            <a href="/noteapp/api/canva/download.php?id=${mediaId}" class="btn btn-sm btn-outline-primary w-100">
                                <i class="bi bi-download"></i>
                            </a>
                        </div>
                        ${descriptionSection}
                    </div>`;
                break;
                
            case 'video':
                if (media.data.includes('youtube.com') || media.data.includes('youtu.be')) {
                    const videoId = extractYouTubeId(media.data);
                    mediaContent = `
                        <div class="card shadow border-0">
                            <div class="ratio ratio-16x9">
                                <iframe src="https://www.youtube.com/embed/${videoId}" frameborder="0" allowfullscreen></iframe>
                            </div>
                            ${descriptionSection}
                        </div>`;
                } else {
                    const videoPath = media.data.startsWith('/') ? media.data : '/noteapp' + media.data;
                    mediaContent = `
                        <div class="card shadow border-0">
                            <video controls class="w-100" style="max-height: 200px;">
                                <source src="${escapeHtml(videoPath)}" type="video/mp4">
                            </video>
                            ${descriptionSection}
                        </div>`;
                }
                break;
                
            case 'file':
                const icon = typeof getFileIcon === 'function' ? getFileIcon(media.original_filename) : 'bi-file-earmark';
                mediaContent = `
                    <div class="card shadow border-0 text-center">
                        <div class="card-body p-3">
                            <i class="bi ${icon} fs-1 text-primary"></i>
                            <p class="small text-truncate mt-2">${escapeHtml(media.original_filename || 'Αρχείο')}</p>
                            <a href="/noteapp/api/canva/download.php?id=${mediaId}" class="btn btn-sm btn-primary">Λήψη</a>
                        </div>
                        ${descriptionSection}
                    </div>`;
                break;
                
            case 'text':
                mediaContent = `
                    <div class="card shadow border-0">
                        <div class="card-body p-3">
                            <p class="small" style="white-space: pre-wrap;">${escapeHtml(media.data || '')}</p>
                        </div>
                    </div>`;
                break;
        }
        div.innerHTML = mediaContent;
    }
    
    return div;
}

        function createFallbackMediaContent(media, typeText) {
            const mediaId = media.id;
            return `
                <div class="card shadow border-0">
                    <div class="card-body">
                        <div class="alert alert-warning mb-2">
                            ${escapeHtml(typeText)}: ${escapeHtml(media.type || '')}
                        </div>
                        <p class="small mb-1">${escapeHtml(media.original_filename || 'Χωρίς όνομα')}</p>
                        <a href="/noteapp/api/canva/download.php?id=${mediaId}" 
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-download me-1"></i>Κατέβασμα
                        </a>
                    </div>
                </div>
            `;
        }

        function initDragAndDrop() {
            const canvas = document.getElementById('notesBoard');
            let draggedElement = null;
            let offsetX = 0;
            let offsetY = 0;

            canvas.addEventListener('mousedown', function(e) {
                const draggableItem = e.target.closest('.media-item, .rich-note, .note-container');
                if (draggableItem) {
                    draggedElement = draggableItem;
                    const rect = draggableItem.getBoundingClientRect();
                    offsetX = e.clientX - rect.left;
                    offsetY = e.clientY - rect.top;
                    
                    draggedElement.classList.add('dragging');
                    e.preventDefault();
                }
            });

            document.addEventListener('mousemove', function(e) {
                if (draggedElement) {
                    const canvasRect = canvas.getBoundingClientRect();
                    const x = e.clientX - canvasRect.left - offsetX;
                    const y = e.clientY - canvasRect.top - offsetY;
                    
                    // Keep within canvas bounds
                    const maxX = canvasRect.width - draggedElement.offsetWidth;
                    const maxY = canvasRect.height - draggedElement.offsetHeight;
                    
                    draggedElement.style.left = Math.max(0, Math.min(x, maxX)) + 'px';
                    draggedElement.style.top = Math.max(0, Math.min(y, maxY)) + 'px';
                }
            });

            document.addEventListener('mouseup', function() {
                if (draggedElement) {
                    draggedElement.classList.remove('dragging');
                    
                    // Αποθήκευση της νέας θέσης
                    savePosition(draggedElement);
                    
                    draggedElement = null;
                }
            });

            // Touch events for mobile
            canvas.addEventListener('touchstart', function(e) {
                const draggableItem = e.target.closest('.media-item, .rich-note, .note-container');
                if (draggableItem) {
                    draggedElement = draggableItem;
                    const touch = e.touches[0];
                    const rect = draggableItem.getBoundingClientRect();
                    offsetX = touch.clientX - rect.left;
                    offsetY = touch.clientY - rect.top;
                    
                    draggedElement.classList.add('dragging');
                    e.preventDefault();
                }
            });

            document.addEventListener('touchmove', function(e) {
                if (draggedElement) {
                    const touch = e.touches[0];
                    const canvasRect = canvas.getBoundingClientRect();
                    const x = touch.clientX - canvasRect.left - offsetX;
                    const y = touch.clientY - canvasRect.top - offsetY;
                    
                    const maxX = canvasRect.width - draggedElement.offsetWidth;
                    const maxY = canvasRect.height - draggedElement.offsetHeight;
                    
                    draggedElement.style.left = Math.max(0, Math.min(x, maxX)) + 'px';
                    draggedElement.style.top = Math.max(0, Math.min(y, maxY)) + 'px';
                    
                    e.preventDefault();
                }
            });

            document.addEventListener('touchend', function() {
                if (draggedElement) {
                    draggedElement.classList.remove('dragging');
                    
                    // Αποθήκευση της νέας θέσης
                    savePosition(draggedElement);
                    
                    draggedElement = null;
                }
            });
        }

        // Συνάρτηση αποθήκευσης θέσης
        async function savePosition(element) {
            try {
                const type = element.getAttribute('data-type');
                const id = element.getAttribute('data-note-id') || element.getAttribute('data-media-id');
                const positionX = parseInt(element.style.left);
                const positionY = parseInt(element.style.top);

                if (!id || !type) {
                    console.error('Missing ID or type for element:', element);
                    return;
                }

                const formData = new FormData();
                formData.append('id', id);
                formData.append('type', type);
                formData.append('position_x', positionX);
                formData.append('position_y', positionY);
                formData.append('canva_id', <?= $canva_id ?>);

                const response = await fetch('public/save_position.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    showSaveIndicator();
                } else {
                    console.error('Error saving position:', result.error);
                }
            } catch (error) {
                console.error('Error saving position:', error);
            }
        }

        // Εμφάνιση indicator για αποθήκευση
        function showSaveIndicator() {
            const indicator = document.getElementById('saveIndicator');
            indicator.style.display = 'block';
            
            setTimeout(() => {
                indicator.style.display = 'none';
            }, 2000);
        }

        function extractYouTubeId(url) {
            const regExp = /^.*((youtu.be\/)|(v\/)|(\/u\/\w\/)|(embed\/)|(watch\?))\??v?=?([^#&?]*).*/;
            const match = url.match(regExp);
            return (match && match[7].length === 11) ? match[7] : false;
        }

        function getFileIcon(filename) {
            if (!filename) return 'bi-file-earmark';
            
            const ext = filename.split('.').pop().toLowerCase();
            const iconMap = {
                'pdf': 'bi-file-earmark-pdf text-danger',
                'doc': 'bi-file-earmark-word text-primary',
                'docx': 'bi-file-earmark-word text-primary',
                'xls': 'bi-file-earmark-excel text-success',
                'xlsx': 'bi-file-earmark-excel text-success',
                'ppt': 'bi-file-earmark-ppt text-warning',
                'pptx': 'bi-file-earmark-ppt text-warning',
                'zip': 'bi-file-earmark-zip text-secondary',
                'rar': 'bi-file-earmark-zip text-secondary',
                'jpg': 'bi-file-earmark-image text-info',
                'jpeg': 'bi-file-earmark-image text-info',
                'png': 'bi-file-earmark-image text-info',
                'gif': 'bi-file-earmark-image text-info',
                'txt': 'bi-file-earmark-text text-dark',
                'html': 'bi-file-earmark-code text-warning',
                'htm': 'bi-file-earmark-code text-warning'
            };
            
            return iconMap[ext] || 'bi-file-earmark text-secondary';
        }

        function escapeHtml(unsafe) {
            if (!unsafe) return '';
            return unsafe
                .toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Στο τέλος του <script> tag στο view_public_canvas.php

function startRealTimeUpdates() {
    // Εκτέλεση κάθε 3 δευτερόλεπτα
    setInterval(async () => {
        try {
            // Προσοχή: Βεβαιώσου ότι το path get_canvas_data.php είναι σωστό
            const response = await fetch(`get_canvas_data.php?id=<?= $canva_id ?>`);
            const data = await response.json();
            
            if (data.success) {
                updateCanvasItems(data.notes, data.media);
            }
        } catch (error) {
            console.error("Σφάλμα συγχρονισμού:", error);
        }
    }, 3000);
}
function updateCanvasItems(notes, media) {
    const canvas = document.getElementById('notesBoard');

    // --- 1. ΣΥΓΧΡΟΝΙΣΜΟΣ ΣΗΜΕΙΩΣΕΩΝ ---
    const activeNoteIds = notes.map(n => n.note_id.toString());
    
    // Αφαίρεση σημειώσεων που διαγράφηκαν από τη βάση
    document.querySelectorAll('.note-container').forEach(el => {
        const id = el.getAttribute('data-note-id');
        if (!activeNoteIds.includes(id)) {
            el.remove();
        }
    });

    notes.forEach(note => {
        let el = document.querySelector(`[data-note-id="${note.note_id}"]`);
        // Δημιουργούμε ένα προσωρινό στοιχείο (πρότυπο) με τα τρέχοντα δεδομένα (Tag/Icon/Content)
        const tempNote = createNoteElement(note);

        if (el) {
            // Ενημέρωση θέσης και περιεχομένου μόνο αν δεν γίνεται dragging
            if (!el.classList.contains('dragging')) {
                el.style.left = note.position_x + 'px';
                el.style.top = note.position_y + 'px';
                
                // LIVE ΕΝΗΜΕΡΩΣΗ ΓΙΑ TAG & ICON
                // Χρήση .trim() για να αγνοηθούν τυχαία κενά στην αρχή ή το τέλος του HTML
                if (el.innerHTML.trim() !== tempNote.innerHTML.trim()) {
                    el.innerHTML = tempNote.innerHTML;
                    el.style.backgroundColor = note.color; // Ενημέρωση χρώματος αν άλλαξε
                    console.log(`Η σημείωση ${note.note_id} ενημερώθηκε (Icon/Tag/Content).`);
                }
            }
        } else {
            // Προσθήκη νέας σημείωσης αν εμφανίστηκε τώρα στη βάση
            canvas.appendChild(tempNote);
        }
    });

    // --- 2. ΣΥΓΧΡΟΝΙΣΜΟΣ ΠΟΛΥΜΕΣΩΝ (MEDIA) ---
    const activeMediaIds = media.map(m => m.id.toString());

    // Αφαίρεση πολυμέσων που διαγράφηκαν
    document.querySelectorAll('.media-item, .rich-note').forEach(el => {
        const id = el.getAttribute('data-media-id');
        if (id && !activeMediaIds.includes(id)) {
            el.remove();
        }
    });

    media.forEach(item => {
        let el = document.querySelector(`[data-media-id="${item.id}"]`);
        const tempMedia = createMediaElement(item);

        if (el) {
            if (!el.classList.contains('dragging')) {
                el.style.left = item.position_x + 'px';
                el.style.top = item.position_y + 'px';

                // Σύγκριση και ανανέωση περιεχομένου πολυμέσων
                if (el.innerHTML.trim() !== tempMedia.innerHTML.trim()) {
                    el.innerHTML = tempMedia.innerHTML;
                    console.log(`Το πολυμέσο ${item.id} ενημερώθηκε.`);
                }
            }
        } else {
            // Προσθήκη νέου πολυμέσου
            canvas.appendChild(tempMedia);
        }
    });
}
// Αρχικοποίηση
document.addEventListener('DOMContentLoaded', () => {
    displayCanvasContent();
    initDragAndDrop();
    startRealTimeUpdates(); // Ξεκινάει αμέσως ο έλεγχος για αλλαγές
});

    </script>
</body>
</html>