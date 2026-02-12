<?php
include('includes/database.php');
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['canvas_id']) && isset($_SESSION['user_id'])) {
    $canvas_id = $_POST['canvas_id'];
    $user_id = $_SESSION['user_id'];
    $access_to_canvas = $_POST['access_to_canvas'];
    $notify_on_post = isset($_POST['notify_on_post']) ? 1 : 0;
    $show_on_dock = isset($_POST['show_on_dock']) ? 1 : 0;

    // Ενημέρωση των στοιχείων του καμβά
    $query = "UPDATE canvases SET access_to_canvas = :access_to_canvas, 
                                   notify_on_post = :notify_on_post, 
                                   show_on_dock = :show_on_dock 
              WHERE id = :canvas_id AND user_id = :user_id";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':access_to_canvas', $access_to_canvas);
    $stmt->bindValue(':notify_on_post', $notify_on_post, PDO::PARAM_INT);
    $stmt->bindValue(':show_on_dock', $show_on_dock, PDO::PARAM_INT);
    $stmt->bindValue(':canvas_id', $canvas_id, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);

    $stmt->execute();

    header("Location: canvaspreferences.php");
    exit;
}
?>
