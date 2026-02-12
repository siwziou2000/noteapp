<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php');
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Πρέπει να συνδεθείτε']));
}

$data = json_decode(file_get_contents('php://input'), true);
$user_id = $_SESSION['user_id'];
$canva_id = $data['canva_id'] ?? null;
$action = $data['action'] ?? '';

// 1. Διόρθωση: Πρέπει πρώτα να προετοιμάσουμε το stmt για τον έλεγχο κατόχου
$stmt = $pdo->prepare("SELECT owner_id FROM canvases WHERE canva_id = ?");
$stmt->execute([$canva_id]);
$canvas = $stmt->fetch();

if (!$canvas || $canvas['owner_id'] != $user_id) {
    die(json_encode(['error' => 'Δεν έχετε δικαίωμα!']));
}

try {
    switch ($action) {
        case 'regenerate': // Διόρθωση ορθογραφίας (ταίριασμα με JS)
            $new_token = bin2hex(random_bytes(16));
            $expires = date('Y-m-d H:i:s', strtotime('+30 days'));

            $stmt = $pdo->prepare("UPDATE canvases SET share_token = ?, token_expires_at = ? WHERE canva_id = ?");
            $stmt->execute([$new_token, $expires, $canva_id]); // Διόρθωση execute

            echo json_encode([
                'success' => true,
                'new_url' => "https://localhost/noteapp/share.php?token=" . $new_token
            ]);
            break;

        case 'update_access':
            $access_type = $data['access_type'] ?? 'view';
            $stmt = $pdo->prepare("UPDATE canvases SET token_access_type = ? WHERE canva_id = ?");
            $stmt->execute([$access_type, $canva_id]);
            echo json_encode(['success' => true]);
            break;

        case 'disable':
            // Διόρθωση ονόματος στήλης (expires αντί exprires)
            $stmt = $pdo->prepare("UPDATE canvases SET share_token = NULL, token_expires_at = NULL WHERE canva_id = ?");
            $stmt->execute([$canva_id]);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['error' => 'Άγνωστη ενέργεια: ' . $action]);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>