<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

if (!$id) {
    http_response_code(400);
    die('Μη έγκυρο ID');
}

$stmt = $pdo->prepare("SELECT data, original_filename, type FROM media WHERE id = ?");
$stmt->execute([$id]);
$file = $stmt->fetch();

if (!$file) {
    http_response_code(404);
    die('Δεν βρέθηκε το αρχείο στη βάση');
}

// Έλεγχος αν είναι YouTube URL
$isYouTube = (strpos($file['data'], 'youtube.com') !== false || strpos($file['data'], 'youtu.be') !== false);

if ($isYouTube) {
    // Ανακατεύθυνση στο YouTube για λήψη
    header('Location: ' . $file['data']);
    exit;
}

// Για local files - χρησιμοποιούμε τη σωστή διαδρομή
$isLocalFile = (strpos($file['data'], '/uploads/') === 0);

if ($isLocalFile) {
    $filePath = $_SERVER['DOCUMENT_ROOT'] . '/noteapp/api/canva' . $file['data'];
} else {
    // Αν το data είναι ήδη πλήρης διαδρομή
    $filePath = $_SERVER['DOCUMENT_ROOT'] . $file['data'];
}

// Debug
// echo "File Path: " . $filePath; exit;

if (!file_exists($filePath)) {
    http_response_code(404);
    die("Το αρχείο δεν βρέθηκε στο filesystem: " . $filePath);
}

// Κατεβάζουμε το αρχείο
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($file['original_filename']) . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;