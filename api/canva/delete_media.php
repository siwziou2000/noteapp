<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';
header('Content-Type: application/json');

// ΔΙΟΡΘΩΣΗ: isset (όχι iseet)
$isAdminMode = isset($_GET['admin']) && $_GET['admin'] == '1' && 
               isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

$userId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'student';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    die(json_encode(['error' => 'Μη έγκυρη μέθοδος']));
}
if (!isset($_GET['id'])){
    die(json_encode(['error' => 'Λείπει το id']));
}

$itemId = (int)$_GET['id'];

try {
    // ΔΙΟΡΘΩΣΗ SQL: canvas_owner_id (όχι onwer)
    $stmt = $pdo->prepare("SELECT m.id, m.owner_id, m.locked_by, m.canva_id, c.owner_id as canvas_owner_id 
                            FROM media m 
                            LEFT JOIN canvases c ON m.canva_id = c.canva_id
                            WHERE m.id = ?");
    $stmt->execute([$itemId]);
    $media = $stmt->fetch();
    
    if (!$media) {
        die(json_encode(['error' => 'Το πολυμέσο δεν βρέθηκε']));
    }

    $canDelete = false;

    // ΕΔΩ ΕΙΝΑΙ Η ΔΥΝΑΜΗ ΤΟΥ ADMIN
    if ($isAdminMode || $userRole === 'admin') {
        $canDelete = true;
    } 
    else {
        // Έλεγχος για απλούς χρήστες
        if ((int)$media['owner_id'] === $userId || (int)$media['canvas_owner_id'] === $userId) {
            if (empty($media['locked_by']) || (int)$media['locked_by'] === $userId) {
                $canDelete = true;
            } else {
                die(json_encode(['error' => 'Το πολυμέσο είναι κλειδωμένο από άλλον χρήστη.']));
            }
        }
    }

    if ($canDelete) {
        $stmtDel = $pdo->prepare("DELETE FROM media WHERE id = ?");
        $stmtDel->execute([$itemId]);
        echo json_encode(['success' => true, 'message' => 'Το πολυμέσο διαγράφηκε επιτυχώς']);
    } else {
        http_response_code(403);
        echo json_encode(['error' => 'Δεν έχετε δικαίωμα διαγραφής']);
    }

} catch (PDOException $e) {
    echo json_encode(['error' => 'Σφάλμα βάσης: ' . $e->getMessage()]);
}