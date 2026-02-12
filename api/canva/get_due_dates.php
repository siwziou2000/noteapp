<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';
header('Content-Type: application/json');

session_start();

try {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['error' => 'Δεν είστε συνδεδεμένοι']);
        exit;
    }

    $user_id = (int)$_SESSION['user_id'];
    $canva_id = isset($_GET['canva_id']) ? (int)$_GET['canva_id'] : null;

    // ΝΕΟ SQL QUERY: Φέρνει σημειώσεις αν είσαι ο owner 
    // Η αν είσαι συνεργάτης στον πίνακα αυτόν
    $sql = "SELECT DISTINCT n.note_id, n.content, n.due_date, c.name as canva_name 
            FROM notes n
            JOIN canvases c ON n.canva_id = c.canva_id
            LEFT JOIN canvas_collaborators col ON c.canva_id = col.canva_id
            WHERE n.due_date IS NOT NULL 
            AND (c.owner_id = :user_id OR (col.user_id = :user_id_col AND col.status = 'accepted'))";

    $params = [
        ':user_id' => $user_id,
        ':user_id_col' => $user_id
    ];

    if ($canva_id) {
        $sql .= " AND n.canva_id = :canva_id";
        $params[':canva_id'] = $canva_id;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $events = [];
    foreach ($notes as $note) {
        $events[] = [
            'id' => $note['note_id'],
            'title' => mb_substr(strip_tags($note['content']), 0, 30) . '...',
            'start' => $note['due_date'],
            'allDay' => true,
            'extendedProps' => [
                'canva_name' => $note['canva_name']
            ]
        ];
    }

    echo json_encode($events);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Σφάλμα ανάκτησης σημειώσεων: ' . $e->getMessage()]);
}
?>