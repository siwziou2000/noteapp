<?php
session_start();
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

// 1. Σωστός έλεγχος Admin (Session ή URL)
$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') || 
           (isset($_GET['admin']) && $_GET['admin'] == '1');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Μη εξουσιοδοτημένη πρόσβαση.']));
}

$input = json_decode(file_get_contents('php://input'), true);
$note_id = isset($input['note_id']) ? (int)$input['note_id'] : 0;
$user_id = (int)$_SESSION['user_id'];

try {
    // Φέρνουμε την τρέχουσα κατάσταση της σημείωσης
    $stmt = $pdo->prepare("SELECT locked_by, locked_by_name, locked_at FROM notes WHERE note_id = ?");
    $stmt->execute([$note_id]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. ΑΠΟΛΥΤΗ ΠΑΡΑΚΑΜΨΗ ΓΙΑ ADMIN
    if ($isAdmin) {
        // Αν ο Admin θέλει απλά να προσπεράσει το κλείδωμα χωρίς να το αλλάξει
        echo json_encode(['success' => true, 'message' => 'Admin bypass active']);
        exit;
    }

    // 3. ΕΛΕΓΧΟΣ ΚΛΕΙΔΩΜΑΤΟΣ ΓΙΑ ΑΠΛΟΥΣ ΧΡΗΣΤΕΣ
    if ($note && $note['locked_by'] && $note['locked_by'] != $user_id) {
        // Έλεγχος αν το κλείδωμα είναι πρόσφατο (π.χ. τελευταία 5 λεπτά)
        $lockedAt = strtotime($note['locked_at']);
        if ((time() - $lockedAt) < 300) {
            http_response_code(423); // Locked
            die(json_encode([
                'success' => false, 
                'error' => 'Κλειδωμένο', 
                'locked_by_name' => $note['locked_by_name'] ?? 'Άλλος χρήστης'
            ]));
        }
    }

    // 4. ΕΝΗΜΕΡΩΣΗ ΚΛΕΙΔΩΜΑΤΟΣ
    // Παίρνουμε το όνομα του τρέχοντος χρήστη για να το γράψουμε στο locked_by_name
    $stmtName = $pdo->prepare("SELECT username FROM users WHERE user_id = ?");
    $stmtName->execute([$user_id]);
    $username = $stmtName->fetchColumn();

    $update = $pdo->prepare("UPDATE notes SET locked_by = ?, locked_by_name = ?, locked_at = NOW() WHERE note_id = ?");
    $update->execute([$user_id, $username, $note_id]);
    
    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}