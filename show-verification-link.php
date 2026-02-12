 <!--show-verification-link.php///-->
 
 
 <?php
session_start();
if (!isset($_SESSION['new_user_token'])) {
    header("Location: signup.php");
    exit;
}

$token = $_SESSION['new_user_token'];
$email = $_SESSION['new_user_email'];
$user_id = $_SESSION['new_user_id'];

// Clear the session after use
unset($_SESSION['new_user_token']);
unset($_SESSION['new_user_email']); 
unset($_SESSION['new_user_id']);
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Επαλήθευση Email</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-success text-white">
                        <h4>Επιτυχής Εγγραφή! 🎉</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h5>Βήμα 1: Επαληθεύστε το Email σας</h5>
                            <p>Για λόγους ασφαλείας, παρακαλώ επαληθεύστε το email σας για να ολοκληρώσετε την εγγραφή.</p>
                            
                            <div class="mt-3 p-3 bg-light rounded">
                                <h6>Σύνδεσμος Επαλήθευσης:</h6>
                                <div class="input-group mb-3">
                                    <input type="text" class="form-control" id="verificationLink" 
                                           value="https://localhost/noteapp/verify-email.php?token=<?= $token ?>" readonly>
                                    <button class="btn btn-outline-secondary" type="button" onclick="copyLink()">
                                        Αντιγραφή
                                    </button>
                                </div>
                                <a href="https://localhost/noteapp/verify-email.php?token=<?= $token ?>" 
                                   class="btn btn-primary btn-lg w-100">
                                   📧 Κάντε Κλικ Εδώ για Επαλήθευση
                                </a>
                            </div>
                        </div>

                        <div class="alert alert-warning">
                            <h5>Βήμα 2: Συνδεθείτε</h5>
                            <p>Μετά την επαλήθευση, μπορείτε να συνδεθείτε με τα στοιχεία σας.</p>
                            <a href="login.php" class="btn btn-success">Μετάβαση στη Σύνδεση</a>
                        </div>

                        <!--<div class="mt-4">
                            <h6>Πληροφορίες Λογαριασμού:</h6>
                            <ul class="list-group">
                                <li class="list-group-item">Email: <strong><?= htmlspecialchars($email) ?></strong></li>
                                <li class="list-group-item">User ID: <strong><?= $user_id ?></strong></li>
                                <li class="list-group-item">Token: <code><?= substr($token, 0, 20) ?>...</code></li>
                            </ul>
                        </div>
-->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function copyLink() {
            const linkInput = document.getElementById('verificationLink');
            linkInput.select();
            linkInput.setSelectionRange(0, 99999);
            document.execCommand('copy');
            
            // Show feedback
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '✔ Αντιγράφηκε!';
            button.classList.remove('btn-outline-secondary');
            button.classList.add('btn-success');
            
            setTimeout(() => {
                button.innerHTML = originalText;
                button.classList.remove('btn-success');
                button.classList.add('btn-outline-secondary');
            }, 2000);
        }
        
        // Auto-select the link on page load
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('verificationLink').select();
        });
    </script>
</body>
</html>