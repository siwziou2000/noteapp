<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

header('Content-Type: application/json');

// CSRF Protection
if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    die(json_encode(['error' => 'Μη έγκυρο αίτημα!', 'error_code' => 'invalid_csrf']));
}

// Λήψη δεδομένων JSON
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    die(json_encode(['error' => 'Δεν ελήφθησαν δεδομένα']));
}

$canva_id = (int)$data['canva_id'];
$email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
$permission = in_array($data['permission'], ['view', 'edit']) ? $data['permission'] : 'view';

try {
    // 1. Βρείτε το user_id από το email
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(404);
        throw new Exception('Ο χρήστης με αυτό το email δεν βρέθηκε');
    }
    
    $collab_user_id = $user['user_id'];
    
    // 2. Έλεγχος αν ο χρήστης είναι ο ιδιοκτήτης του πίνακα
    $stmtOwner = $pdo->prepare("SELECT owner_id FROM canvases WHERE canva_id = ?");
    $stmtOwner->execute([$canva_id]);
    $canvas = $stmtOwner->fetch();
    if ($canvas && $canvas['owner_id'] == $collab_user_id) {
        http_response_code(400);
        throw new Exception('Δεν μπορείτε να προσθέσετε τον ιδιοκτήτη ως συνεργάτη');
    }
    
    // 3. Έλεγχος αν ο χρήστης είναι ήδη συνεργάτης
    $stmtCheck = $pdo->prepare("SELECT * FROM canvas_collaborators WHERE canva_id = ? AND user_id = ?");
    $stmtCheck->execute([$canva_id, $collab_user_id]);
    
    if ($stmtCheck->fetch()) {
        // Εδώ στέλνουμε το κλειδί που περιμένει η JavaScript σου
        http_response_code(409); // Conflict
        echo json_encode([
            'success' => false,
            'error' => 'Ο χρήστης είναι ήδη συνεργάτης σε αυτόν τον πίνακα',
            'error_code' => 'already_exists'
        ]);
        exit;
    }
    
    // 4. Προσθήκη συνεργάτη
    $stmtInsert = $pdo->prepare("INSERT INTO canvas_collaborators (canva_id, user_id, permission, status) VALUES (?, ?, ?, 'accepted')");
    $stmtInsert->execute([$canva_id, $collab_user_id, $permission]);
    
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // Αν δεν έχει οριστεί ήδη κωδικός σφάλματος HTTP
    if (http_response_code() == 200) http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'error_code' => 'server_error'
    ]);
}