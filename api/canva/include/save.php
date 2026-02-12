<?php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['image'])) {
        echo json_encode(['success' => false, 'message' => 'Δεν ελήφθησαν δεδομένα εικόνας']);
        exit;
    }

    $imageData = $data['image'];
    $imageData = str_replace('data:image/png;base64,', '', $imageData);
    $imageData = str_replace(' ', '+', $imageData);
    $binaryData = base64_decode($imageData);

    // Ενημέρωση υπάρχουσας εγγραφής ή δημιουργία νέας
    $stmt = $pdo->prepare("INSERT INTO drawings (id, image, created_at, updated_at) VALUES (1, ?, NOW(), NOW()) 
                          ON DUPLICATE KEY UPDATE image = VALUES(image), updated_at = NOW()");
    $stmt->execute([$binaryData]);

    echo json_encode([
        'success' => true,
        'message' => 'Η εικόνα αποθηκεύτηκε επιτυχώς!',
        'timestamp' => time()
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Σφάλμα: ' . $e->getMessage()
    ]);
}
?>