<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

header('Content-Type: application/json');

// 1. ΕΛΕΓΧΟΣ ΓΙΑ ADMIN MODE
// Ελέγχουμε αν η κλήση έγινε με ?admin=1 ΚΑΙ αν ο χρήστης στο session είναι admin
$isAdminMode = (isset($_GET['admin']) && $_GET['admin'] == '1') && 
               (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

// 2. CSRF Protection (Ο Admin παρακάμπτει τον έλεγχο αν είναι σε Admin Mode)
if (!$isAdminMode) {
    if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        die(json_encode(['error' => 'Μη έγκυρο αίτημα CSRF']));
    }
}

// 3. Έλεγχος σύνδεσης χρήστη
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Μη εξουσιοδοτημένη πρόσβαση']));
}

$data = json_decode(file_get_contents('php://input'), true);
$media_id = isset($data['media_id']) ? (int)$data['media_id'] : null;
$canva_id = isset($data['canva_id']) ? (int)$data['canva_id'] : null;
$user_id = (int)$_SESSION['user_id'];

// Έλεγχος αν λείπουν τα απαραίτητα IDs (το σφάλμα που έβλεπες)
if (!$media_id || !$canva_id) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid canvas ID or media ID']));
}

try {
    // 4. ΑΠΟΛΥΤΗ ΠΑΡΑΚΑΜΨΗ ΓΙΑ ADMIN
    if ($isAdminMode) {
        echo json_encode([
            'success' => true, 
            'message' => 'Admin bypass: Access granted',
            'is_admin' => true
        ]);
        exit;
    }

    // 5. ΕΛΕΓΧΟΣ ΑΝ ΤΟ ΠΟΛΥΜΕΣΟ ΑΝΗΚΕΙ ΣΤΟΝ ΚΑΜΒΑ
    $stmt_check = $pdo->prepare("SELECT id FROM media WHERE id = ? AND canva_id = ?");
    $stmt_check->execute([$media_id, $canva_id]);
    if (!$stmt_check->fetch()) {
        die(json_encode(['error' => 'Το πολυμέσο δεν βρέθηκε στον συγκεκριμένο καμβά']));
    }

    // 6. ΕΛΕΓΧΟΣ ΚΛΕΙΔΩΜΑΤΟΣ (Μόνο για απλούς χρήστες)
    $stmt_lock_check = $pdo->prepare("
        SELECT locked_by, u.username AS locked_by_name 
        FROM media m
        LEFT JOIN users u ON m.locked_by = u.user_id
        WHERE m.id = ? AND m.locked_by IS NOT NULL AND m.locked_by != ?
    ");
    $stmt_lock_check->execute([$media_id, $user_id]);

    if ($existing_lock = $stmt_lock_check->fetch()) {
        die(json_encode([
            'error' => 'Κλειδωμένο',
            'locked_by' => $existing_lock['locked_by'],
            'locked_by_name' => $existing_lock['locked_by_name']
        ]));
    }

    // 7. ΕΝΗΜΕΡΩΣΗ ΚΛΕΙΔΩΜΑΤΟΣ
    $stmt_lock = $pdo->prepare("UPDATE media SET locked_by = ?, locked_at = NOW() WHERE id = ?");
    $stmt_lock->execute([$user_id, $media_id]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Σφάλμα βάσης δεδομένων: ' . $e->getMessage()]);
}