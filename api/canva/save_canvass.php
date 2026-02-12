<?php
session_start();

// Αρχείο όπου θα αποθηκεύουμε τα δεδομένα σχεδίασης
$file = 'canvas_data.json';

// Αν λαμβάνουμε POST request με δεδομένα
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = file_get_contents("php://input"); 
    file_put_contents($file, $data);
    echo json_encode(["status" => "success"]);
    exit;
}

// Αν είναι GET request, επιστρέφουμε τα δεδομένα σχεδίασης
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists($file)) {
        echo file_get_contents($file);
    } else {
        echo json_encode([]);
    }
    exit;
}
?>
