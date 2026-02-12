<?php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

try {
    // Λήψη τελευταίας εικόνας με timestamp
    $stmt = $pdo->query("SELECT image, UNIX_TIMESTAMP(created_at) as timestamp FROM drawings WHERE id = 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && $row['image']) {
        echo json_encode([
            'success' => true,
            'image' => base64_encode($row['image']),
            'timestamp' => $row['timestamp']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Δεν βρέθηκε εικόνα'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Σφάλμα βάσης: ' . $e->getMessage()
    ]);
}
?>