<?php
header('Content-Type: application/json');

// Ρυθμίσεις βάσης δεδομένων
$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'noteapp',
    'username' => 'root',
    'password' => ''
];

try {
    $conn = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4",
        $dbConfig['username'],
        $dbConfig['password']
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['success' => false, 'error' => 'Σφάλμα σύνδεσης: ' . $e->getMessage()]));
}

// Διαχείριση πολυμέσου
$mediaPath = null;
if (!empty($_FILES['media']['tmp_name'])) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'application/pdf'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    if (!in_array($_FILES['media']['type'], $allowedTypes)) {
        echo json_encode(['success' => false, 'error' => 'Μη επιτρεπτός τύπος αρχείου']);
        exit;
    }

    if ($_FILES['media']['size'] > $maxSize) {
        echo json_encode(['success' => false, 'error' => 'Το αρχείο είναι πολύ μεγάλο (>5MB)']);
        exit;
    }

    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $fileName = uniqid() . '_' . basename($_FILES['media']['name']);
    $targetPath = $uploadDir . $fileName;

    if (move_uploaded_file($_FILES['media']['tmp_name'], $targetPath)) {
        $mediaPath = $targetPath;
    }
}

// Αποθήκευση στη βάση
try {
    $stmt = $conn->prepare("
        INSERT INTO notess (note_text, media_path, tag, icon)
        VALUES (:note_text, :media_path, :tag, :icon)
    ");
    $stmt->execute([
        ':note_text' => $_POST['note_text'] ?? null,
        ':media_path' => $mediaPath,
        ':tag' => $_POST['tag'] ?? null,
        ':icon' => $_POST['icon'] ?? null
    ]);

    echo json_encode([
        'success' => true,
        'id' => $conn->lastInsertId(),
        'media_path' => $mediaPath
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Σφάλμα βάσης: ' . $e->getMessage()]);
}