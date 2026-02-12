<?php
session_start();


$inactive = 60; // Άλλαξέ το σε 1800 μετά τη δοκιμή
if (isset($_SESSION['timeout'])) {
    $session_life = time() - $_SESSION['timeout'];
    if ($session_life > $inactive) {
        session_unset();
        session_destroy();
        header("Location: /noteapp/login.php");
        exit();
    }
}
$_SESSION['timeout'] = time();

require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /noteapp/login.php");
    exit();
}

// Ελέγχουμε αν ο χρήστης είναι συνδεδεμένος και έχει ρόλο admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");  // Αν δεν είναι admin, τον στέλνουμε πίσω στην αρχική σελίδα
    exit;
}

// Λήψη του τρέχοντος θέματος από τη session (ή από τη βάση)
$current_theme = isset($_SESSION['theme']) ? $_SESSION['theme'] : 'light';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ενημέρωση του θέματος στην session (ή αποθήκευση στη βάση)
    $_SESSION['theme'] = $_POST['theme'];
    header("Location: admin_dashboard.php");
    exit;
}

// Παίρνουμε το όνομα του χρήστη από τη session
$username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : htmlspecialchars($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Πίνακας Διαχείρισης</title>
    <!-- Για καλύτερη εμφάνιση, προσθέτουμε Bootstrap -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<style>
    body {
        background-color: <?= $current_theme === 'dark' ? '#333' : '#fff' ?>;
        color: <?= $current_theme === 'dark' ? '#fff' : '#000' ?>;
    }
</style>

<div class="container mt-5">
    <h1>Πίνακας Διαχείρισης</h1>
    <p>Καλώς ήρθατε στο σύστημα διαχείρισης, <?= $username; ?>. Από εδώ μπορείτε να διαχειριστείτε τους χρήστες και τις ρυθμίσεις του συστήματος.</p>

    <!-- Admin Dashboard Navigation -->
    <h2>Διαχείριση Χρηστών</h2>
    <ul>
        <li><a href="users.php" class="btn btn-info btn-sm">Διαχείριση Χρηστών</a></li>
       <a href="admin_approve.php" class="btn btn-warning btn-sm">
            <i class="fas fa-user-clock"></i> Εγκρίσεις Νέων Χρηστών (Guest)
        </a>
    </li>
        
        <li><a href="change_role.php" class="btn btn-danger btn-sm">Αλλαγή Ρόλου Χρήστη</a></li>
    </ul>

    <h2>Ρυθμίσεις Συστήματος</h2>
    <ul>
        <!--<li><a href="system_settings.php" class="btn btn-secondary btn-sm">Ρυθμίσεις</a></li>-->
        <li><a href="themes.php" class="btn btn-info btn-sm">Αλλαγή Θέματος</a></li> <!-- Link για αλλαγή θέματος -->
    </ul>

    
    
    <!-- Logout Button -->
    <a href="logout.php" class="btn btn-danger mt-3">Αποσύνδεση</a>
</div>



<!-- Bootstrap JS and dependencies -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
