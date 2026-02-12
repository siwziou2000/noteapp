<?php
// rename_canva.php

// 1. Σύνδεση με τη βάση δεδομένων (προσαρμόστε ανάλογα)
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

// 2. Έλεγχος αν έχουν σταλεί οι παράμετροι id και name
if (!isset($_GET['id']) || !isset($_GET['name'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$canva_id = $_GET['id'];
$new_name = trim($_GET['name']);

// 3. Βασικός έλεγχος (μπορείς να προσθέσεις επιπλέον validation)
if ($new_name === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Empty name']);
    exit;
}

try {
    // 4. Προετοιμασμένη δήλωση για ενημέρωση ονόματος καμβά
    $stmt = $pdo->prepare("UPDATE canvases SET name = ? WHERE canva_id = ?");
    $stmt->execute([$new_name, $canva_id]);

    if ($stmt->rowCount() > 0) {
        // Ενημέρωση επιτυχής
        echo json_encode(['success' => true]);
    } else {
        // Δεν βρέθηκε ο καμβάς ή το όνομα είναι ίδιο
        http_response_code(404);
        echo json_encode(['error' => 'Canvas not found or name unchanged']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
