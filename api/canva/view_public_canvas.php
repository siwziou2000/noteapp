<?php
session_start();
// Χρήση απόλυτου path για το database
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

$canva_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$canva_id) {
    header('Location: /noteapp/api/canva/public_canvases.php');
    exit;
}

// ... ο υπόλοιπος κώδικας παραμένει ο ίδιος ...

