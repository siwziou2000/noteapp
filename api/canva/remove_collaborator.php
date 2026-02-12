<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';
session_start();

header('Content-Type: application/json');

// CSRF Protection
if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
    die(json_encode(['error' => 'Μη έγκυρο αίτημα!']));
}

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Πρέπει να συνδεθείτε!']));
}

$user_id = (int)$_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

try {
    // Έλεγχος αν ο χρήστης είναι ιδιοκτήτης του πίνακα
    $stmt = $pdo->prepare("SELECT 1 FROM canvases WHERE canva_id = ? AND owner_id = ?");
    $stmt->execute([$data['canva_id'], $user_id]);
    
    if (!$stmt->fetch()) {
        throw new Exception('Μόνο ο ιδιοκτήτης μπορεί να αφαιρέσει συνεργάτες', 403);
    }
    
    // Διαγραφή συνεργάτη
    $stmtDelete = $pdo->prepare("DELETE FROM canvas_collaborators WHERE canva_id = ? AND user_id = ?");
    $stmtDelete->execute([$data['canva_id'], $data['user_id']]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Σφάλμα βάσης δεδομένων']);
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['error' => $e->getMessage()]);
}