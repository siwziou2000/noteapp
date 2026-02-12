<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    die(json_encode(['error' => 'Πρέπει να συνδεθείτε!']));
}

$user_id = (int)$_SESSION['user_id'];

// Λήψη παραμέτρων από POST
$keyword = isset($_POST['keyword']) ? trim($_POST['keyword']) : '';
$tag = isset($_POST['tag']) ? trim($_POST['tag']) : '';
$icon = isset($_POST['icon']) ? trim($_POST['icon']) : '';
$color = isset($_POST['color']) ? trim($_POST['color']) : '';
$date = isset($_POST['date']) ? trim($_POST['date']) : '';
$group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : null;
$canva_id = isset($_POST['canva_id']) ? (int)$_POST['canva_id'] : null;

try {
    $sql = "SELECT n.*, c.name as canvas_name, g.group_name 
            FROM notes n 
            LEFT JOIN canvases c ON n.canva_id = c.canva_id 
            LEFT JOIN groups g ON n.group_id = g.group_id 
            WHERE n.owner_id = ?";
    
    $params = [$user_id];
    
    // Προσθήκη φίλτρων
    if (!empty($keyword)) {
        $sql .= " AND (n.content LIKE ? OR n.tag LIKE ?)";
        $params[] = "%$keyword%";
        $params[] = "%$keyword%";
    }
    
    if (!empty($tag)) {
        $sql .= " AND n.tag LIKE ?";
        $params[] = "%$tag%";
    }
    
    if (!empty($icon)) {
        $sql .= " AND n.icon = ?";
        $params[] = $icon;
    }
    
    if (!empty($color) && $color !== '#ffffff') {
        $sql .= " AND n.color = ?";
        $params[] = $color;
    }
    
    if (!empty($date)) {
        $sql .= " AND DATE(n.created_at) = ?";
        $params[] = $date;
    }
    
    if (!empty($group_id)) {
        $sql .= " AND n.group_id = ?";
        $params[] = $group_id;
    }
    
    if (!empty($canva_id)) {
        $sql .= " AND n.canva_id = ?";
        $params[] = $canva_id;
    }
    
    $sql .= " ORDER BY n.created_at DESC LIMIT 100";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ομαδοποίηση αποτελεσμάτων
    $grouped_results = [
        'by_canvas' => [],
        'by_tag' => [],
        'by_date' => []
    ];
    
    foreach ($notes as $note) {
        // Κατηγορία καμβά
        $canvas_name = $note['canvas_name'] ?? 'Χωρίς πίνακα';
        if (!isset($grouped_results['by_canvas'][$canvas_name])) {
            $grouped_results['by_canvas'][$canvas_name] = [];
        }
        $grouped_results['by_canvas'][$canvas_name][] = $note;
        
        // Κατηγορία ετικέτας
        $tag_name = $note['tag'] ?? 'Χωρίς ετικέτα';
        if (!isset($grouped_results['by_tag'][$tag_name])) {
            $grouped_results['by_tag'][$tag_name] = [];
        }
        $grouped_results['by_tag'][$tag_name][] = $note;
        
        // Κατηγορία ημερομηνίας
        $date = date('d/m/Y', strtotime($note['created_at']));
        if (!isset($grouped_results['by_date'][$date])) {
            $grouped_results['by_date'][$date] = [];
        }
        $grouped_results['by_date'][$date][] = $note;
    }
    
    echo json_encode([
        'success' => true,
        'total_count' => count($notes),
        'grouped_results' => $grouped_results,
        'notes' => $notes
    ]);
    
} catch (PDOException $e) {
    error_log("Advanced search error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Σφάλμα βάσης δεδομένων'
    ]);
}
?>