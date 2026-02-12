<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Όχι εξουσιοδοτημένη πρόσβαση']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // Επαλήθευση υποχρεωτικών πεδίων
    if (!isset($input['name']) || empty(trim($input['name']))) {
        echo json_encode(['error' => 'Το όνομα του πίνακα είναι απαραίτητο']);
        exit;
    }

    $userId = $_SESSION['user_id'];
    $canvasName = htmlspecialchars(trim($input['name']));
    $category = isset($input['category']) ? htmlspecialchars($input['category']) : 'Εκπαίδευση';
    $accessType = isset($input['access']) ? htmlspecialchars($input['access']) : 'private';
    $uniqueId = bin2hex(random_bytes(16));

    // Έλεγχος έγκυρων τιμών για access_type
    $validAccessTypes = ['private', 'public', 'shared'];
    if (!in_array($accessType, $validAccessTypes)) {
        $accessType = 'private'; // default value
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO canvases 
            (owner_id, user_id, name, unique_canva_id, access_type, canva_category) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $userId,
            $canvasName,
            $uniqueId,
            $accessType,
            $category
        ]);

        echo json_encode([
            'success' => true,
            'canva_id' => $pdo->lastInsertId(),
            'unique_id' => $uniqueId,
            'access_type' => $accessType
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Σφάλμα δημιουργίας πίνακα: ' . $e->getMessage()
        ]);
    }
}
?>