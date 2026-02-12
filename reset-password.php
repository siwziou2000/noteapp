<?php
// Συμπερίληψη αρχείων header και navbar
include('includes/header.php');
include('includes/navbar.php');

// Σύνδεση με τη βάση δεδομένων
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "noteapp";

$connection = mysqli_connect($servername, $username, $password, $dbname);

if (!$connection) {
    die("Σφάλμα σύνδεσης: " . mysqli_connect_error());
}

// Αν δεν υπάρχει το token στην URL, ανακατευθύνουμε τον χρήστη στην αρχική σελίδα
if (!isset($_GET['token'])) {
    header('Location: index.php');
    exit();
}

$token = $_GET['token'];

// Έλεγχος αν το token είναι έγκυρο στη βάση δεδομένων
$query = "SELECT * FROM password_resets WHERE token = '$token' LIMIT 1";
$result = mysqli_query($connection, $query);

if (mysqli_num_rows($result) == 0) {
    echo "Λάθος σύνδεσμος επαναφοράς κωδικού.";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Αποδοχή του νέου κωδικού από τη φόρμα
    $new_password = $_POST['new_password'];

    // Κρυπτογράφηση του νέου κωδικού
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    // Βρίσκουμε το email του χρήστη από την εγγραφή του token
    $row = mysqli_fetch_assoc($result);
    $email = $row['email'];

    // Ενημέρωση του κωδικού στη βάση δεδομένων
    $update_query = "UPDATE users SET password_hash = '$hashed_password' WHERE email = '$email'";
    mysqli_query($connection, $update_query);

    // Διαγραφή του token από τον πίνακα για λόγους ασφαλείας
    $delete_query = "DELETE FROM password_resets WHERE token = '$token'";
    mysqli_query($connection, $delete_query);

    // Ανακατεύθυνση στη σελίδα σύνδεσης
    echo "Ο κωδικός σας ανανεώθηκε επιτυχώς. Μπορείτε να συνδεθείτε.";
    header("Location: login.php");
    exit();
}
?>

<div class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header">
                        <h5>Επαναφορά Κωδικού Πρόσβασης</h5>
                    </div>
                    <div class="card-body">
                        <!-- Φόρμα Επαναφοράς Κωδικού -->
                        <form method="POST">
                            <div class="form-group mb-3">
                                <label for="new_password">Νέος Κωδικός</label>
                                <input type="password" name="new_password" id="new_password" class="form-control" required>
                            </div>

                            <button type="submit" class="btn btn-primary">Ανανεώστε τον Κωδικό</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('includes/footer.php'); ?>
