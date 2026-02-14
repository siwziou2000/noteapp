<?php
// forgot-password.php
include('includes/header.php');
include('includes/navbar.php');

// Συμπερίληψη του PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

require 'vendor/autoload.php';

try {
    $dotenv = Dotenv::createImmutable(_DIR_);
    $dotenv->load();

} catch  (Exception $e) {
    die("Σφαλμα φορτωσης  ρυθμισεων  περιβαλλοντος.")
}

;

try {
    $dsn = "mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'] . ";charset=utf8";
    $connection = newPDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS']);
    $connection->setAttrtibute(PDO::ATTR_ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Σφαλμα σύνδεσης: " . $e->getMessage());
}
    
// Επεξεργασία της φόρμας
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    // Επαλήθευση της μορφής του email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<div class='alert alert-danger'>Παρακαλώ εισάγετε ένα έγκυρο email.</div>";
    } else {
        try {
            // Έλεγχος αν το email υπάρχει στη βάση
            $stmt = $connection->prepare("SELECT * FROM users WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                // Δημιουργία μοναδικού token για επαναφορά κωδικού
                $token = bin2hex(random_bytes(32));
                
                // Προσθήκη του token στη βάση δεδομένων
                $stmt = $connection->prepare("INSERT INTO password_resets (email, token, created_at) VALUES (:email, :token, NOW())");
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':token', $token);
                $stmt->execute();
                
                // Δημιουργία συνδέσμου επαναφοράς
                $reset_link = "http://localhost/noteapp/reset-password.php?token=" . $token;
                
                // Ρύθμιση του PHPMailer
                $mail = new PHPMailer(true);
                
                try {
                    // Ρυθμίσεις SMTP
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'swtiriasiwziou26@gmail.com'; // Το email σας
                    $mail->Password ='mvbu xacq vjys enti'; // Το App Password σας
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;
                    
                    // Από και προς
                    $mail->setFrom('swtiriasiwziou26@gmail.com', 'NoteApp');
                    $mail->addAddress($email);
                    
                    // Περιεχόμενο email
                    $mail->isHTML(true);
                    $mail->Subject = 'Επαναφορά Κωδικού Πρόσβασης';
                    $mail->Body = "Πατήστε τον παρακάτω σύνδεσμο για να επαναφέρετε τον κωδικό σας:<br><br><a href='$reset_link'>$reset_link</a>";
                    $mail->AltBody = "Πατήστε τον παρακάτω σύνδεσμο για να επαναφέρετε τον κωδικό σας:\n\n$reset_link";
                    
                    $mail->send();
                    echo "<div class='alert alert-success'>Το email επαναφοράς στάλθηκε. Ελέγξτε το inbox σας.</div>";
                } catch (Exception $e) {
                    echo "<div class='alert alert-danger'>Αποτυχία αποστολής email. Σφάλμα: {$mail->ErrorInfo}</div>";
                }
            } else {
                echo "<div class='alert alert-warning'>Το email δεν βρέθηκε.</div>";
            }
        } catch (PDOException $e) {
            echo "<div class='alert alert-danger'>Σφάλμα βάσης δεδομένων: " . $e->getMessage() . "</div>";
        }
    }
}
?>

<div class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header">
                        <h5>Επαναφορά Κωδικού Πρόσβασης</h5>
                    </div>
                    
                    <div class="card-body">
                        <p class="text-muted mb-4">Εισάγετε τη διεύθυνση email του λογαριασμού σας και θα σας στείλουμε ένα σύνδεσμο για επαναφορά του κωδικού σας.</p>
                        
                        <!-- Φόρμα Επαναφοράς Κωδικού -->
                        <form method="POST">
                            <div class="form-group mb-3">
                                <label for="email">Διεύθυνση Email</label>
                                <input type="email" name="email" id="email" class="form-control" required placeholder="π.χ. example@domain.com">
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Αποστολή Συνδέσμου Επαναφοράς</button>
                            </div>
                        </form>
                        
                        <hr>
                        <div class="text-center">
                            <a href="login.php" class="text-decoration-none">Επιστροφή στη σελίδα σύνδεσης</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<?php include('includes/footer.php'); ?>
