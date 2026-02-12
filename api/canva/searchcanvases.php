<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';


// Check user login
if (!isset($_SESSION['user_id'])) {
    die("Πρέπει να είστε συνδεδεμένοι.");
}

$user_id = $_SESSION['user_id'];
$query = isset($_POST['query']) ? trim($_POST['query']) : '';

if (empty($query)) {
    echo "<div class='alert alert-info'>Παρακαλώ εισάγετε έναν όρο αναζήτησης.</div>";
    exit();
}

function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = [
        'y' => 'χρόνια',
        'm' => 'μήνες',
        'w' => 'εβδομάδες',
        'd' => 'μέρες',
        'h' => 'ώρες',
        'i' => 'λεπτά',
        's' => 'δευτερόλεπτα',
    ];
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v;
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? 'πριν ' . implode(', ', $string) : 'μόλις τώρα';
}




try {
    $searchTerm = "%$query%";
    $stmt = $pdo->prepare("
        SELECT canva_id, name, created_at, access_type 
        FROM canvases 
        WHERE user_id = :user_id 
        AND name LIKE :search_term
    ");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':search_term', $searchTerm, PDO::PARAM_STR);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($results)) {
        echo "<div class='alert alert-warning'>Δεν βρέθηκε κανένας καμβάς με αυτό το όνομα.</div>";
        exit();
    }

    echo "<ul class='list-group'>";
    foreach ($results as $row) {
        echo "<li class='list-group-item'>";
        echo "<h5>" . htmlspecialchars($row['name']) . "</h5>";
        echo "<small class='text-muted'>";
        echo time_elapsed_string($row['created_at']) . " | ";
        echo ($row['access_type'] === 'public' ? 'Δημόσιο' : 'Ιδιωτικό');
        echo "</small>";
        echo "</li>";
    }
    echo "</ul>";

} catch (PDOException $e) {
    error_log("Search error: " . $e->getMessage());
    echo "<div class='alert alert-danger'>Σφάλμα κατά την αναζήτηση.</div>";
}