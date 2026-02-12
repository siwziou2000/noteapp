<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';
header('Content-Type: application/json');

$canva_id = isset ($_GET['id']) ? (int)$_GET['id'] : 0;

try{
    //anaktisi simeipseon
    $stmtNotes = $pdo->prepare ("SELECT * FROM notes WHERE canva_id = ?");
    $stmtNotes->execute([$canva_id]);
    $notes = $stmtNotes->fetchAll(PDO::FETCH_ASSOC);

    //ANAKTISI POLYMESON
    $stmtMedia = $pdo->prepare("SELECT * FROM media WHERE canva_id = ?");
    $stmtMedia->execute([$canva_id]);
    $media = $stmtMedia->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'notes' => $notes, 'media' => $media]);

}catch (Exception $e){
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}


?>

