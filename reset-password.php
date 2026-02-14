<?php
// reset-password.php
include('includes/header.php');
include('includes/navbar.php');

use Dotenv\Dotenv;
require 'vendor/autoload.php';

// 1. Φόρτωση ρυθμίσεων .env
try {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
} catch (Exception $e) {
    die("Σφάλμα περιβάλλοντος.");
}

// 2. Σύνδεση με τη βάση (Χρήση $_ENV) 
try {
    $dsn = "mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'] . ";charset=utf8";
    $connection = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS']);
    $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Σφάλμα σύνδεσης: " . $e->getMessage());
}

// Αν δεν υπάρχει το token στην URL
if (!isset($_GET['token'])) {
    header('Location: login.php');
    exit();
}

$token = $_GET['token'];

// 3. Έλεγχος αν το token είναι έγκυρο στη βάση 
$stmt = $connection->prepare("SELECT * FROM password_resets WHERE token = :token LIMIT 1");
$stmt->bindParam(':token', $token);
$stmt->execute();
$reset_record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reset_record) {
    echo "<div class='container mt-5'><div class='alert alert-danger text-center'>Λάθος ή ληγμένος σύνδεσμος επαναφοράς.</div></div>";
    include('includes/footer.php');
    exit();
}

// 4. Επεξεργασία Αλλαγής Κωδικού
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'];
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $email = $reset_record['email'];

    try {
        $connection->beginTransaction();

        // ΕΝΗΜΕΡΩΣΗ: Χρήση του σωστού ονόματος στήλης 'password_hash' 
        $update_stmt = $connection->prepare("UPDATE users SET password_hash = :password WHERE email = :email");
        $update_stmt->bindParam(':password', $hashed_password);
        $update_stmt->bindParam(':email', $email);
        $update_stmt->execute();

        // Διαγραφή του token
        $delete_stmt = $connection->prepare("DELETE FROM password_resets WHERE token = :token");
        $delete_stmt->bindParam(':token', $token);
        $delete_stmt->execute();

        $connection->commit();

        // ΑΥΤΟΜΑΤΗ ΑΝΑΚΑΤΕΥΘΥΝΣΗ ΣΤΟ LOGIN
        header("Location: login.php?message=password_updated");
        exit();

    } catch (Exception $e) {
        $connection->rollBack();
        echo "<div class='alert alert-danger'>Κάτι πήγε στραβά: " . $e->getMessage() . "</div>";
    }
}
?>

<div class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Ορισμός Νέου Κωδικού</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-group mb-3">
                                <label for="new_password">Εισάγετε τον νέο σας κωδικό πρόσβασης:</label>
                                <input type="password" name="new_password" id="new_password" class="form-control" required minlength="6">
                                <small class="text-muted">Τουλάχιστον 6 χαρακτήρες.</small>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-success">Ανανέωση Κωδικού</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('includes/footer.php'); ?>
