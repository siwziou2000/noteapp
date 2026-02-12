<?php

// selida stin opoia diaxeirizetai o diaxeiristis na apenergopoiei kai apanergopoiei toys logariasmoys xristes toy sistimatos 
session_start();

require_once ($_SERVER ['DOCUMENT_ROOT'] .'/noteapp/includes/database.php');

// elegxoe prosvasis mono gia toys diaxeiristes toy sistimatos

if (!isset ($_SESSION['user_id']) || $_SESSION ['role'] !== 'admin'){
    header ('Content-Type: application/json');
    echo json_encode(['success' => false,'message'=> 'δεν εχετε δικαιωμα προσβασης']);
    exit;
}

// CSRF Protection (προαιρετικό)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Προσθέστε CSRF ελέγχους εάν είναι απαραίτητο
    // if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    //     echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    //     exit;
    // }
}

// livi dedomenon
$userId = isset($_POST['id']) ? (int) $_POST ['id'] : 0;
$currentStatus = isset($_POST['status']) ? (int) $_POST ['status'] : 0;
$newStatus = ($currentStatus == 1) ? 0 : 1;

///elegxos egkirotitas
if($userId <= 0)
{
    echo json_encode(['success' => false,'message' => 'μη εγκυρο Id χρηστη']);
    exit;
}

try 
{
    // enimerosi tiw katastasi toy xristi
    $query = "UPDATE users SET isactive = :newStatus WHERE user_id = :userId";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':newStatus', $newStatus, PDO::PARAM_INT);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);

    if ($stmt->execute()){
        echo json_encode([
            'success' => true,
            'message' => 'Η κατασταση ενημερωθηκε με επιτυχια',
            'new_status'=> $newStatus
        ]);
    } else {
        echo json_encode(['success' => false,'message' => 'Αποτυχια ενημερωσης']);
    }
}
catch (PDOException $e) {
    error_log("toggle status error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'σφαλμα της βασης δεδομενων']);
}

?>