<?php
session_start();
$page_title = "Login Form";
include('includes/header.php');

include('includes/navbar.php');
include('includes/database.php');

$is_invalid = false;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['email'], $_POST['password'], $_POST['csrf_token'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("CSRF token validation failed.");
    }

    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    try {
        // --- ΔΙΟΡΘΩΣΗ: Προσθήκη του public_token στο SELECT query ---
        $sql = "SELECT user_id, username, password_hash, email_verified, role, public_token FROM users WHERE email = :email";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['email_verified'] != 1) {
                $is_invalid = "Το email σας δεν έχει επαληθευτεί. Παρακαλώ ελέγξτε το email σας ή <a href='resend-verification.php?email=" . urlencode($email) . "' class='alert-link'>ζητήστε νέο σύνδεσμο</a>.";
            } else {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['username'] = $user['username'];
                
                // --- ΔΙΟΡΘΩΣΗ: Αποθήκευση του public_token στο SESSION για χρήση σε shared links ---
                $_SESSION['public_token'] = $user['public_token'];

                $redirect = match($user['role']) {
                    'admin' => 'admin_dashboard.php',
                    'guest' => 'no_access.php',
                    default => 'api/canva/home.php',
                };
                header("Location: $redirect");
                exit;
            }
        } else {
            $is_invalid = "Λάθος email ή κωδικός πρόσβασης.";
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $is_invalid = "Προέκυψε σφάλμα. Παρακαλώ δοκιμάστε ξανά αργότερα.";
    }
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Σύνδεση</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .password-container { position: relative; }
        .password-container input { padding-right: 40px; }
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card shadow">
                        <div class="card-header">
                            <h5>Φόρμα Σύνδεσης</h5>
                        </div>
                        <div class="card-body">
                            <form action="login.php" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                                
                                <div class="form-group mb-3">
                                    <label>Διεύθυνση Email</label>
                                    <input type="email" name="email" class="form-control" required 
                                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                                </div>
                               
                                <div class="form-group mb-3 password-container">
                                    <label for="password">Κωδικός Πρόσβασης</label>
                                    <div class="input-group">
                                        <input type="password" name="password" class="form-control" id="password" required>
                                        <span class="input-group-text" id="togglePassword" style="cursor: pointer;">
                                            <i class="bi bi-eye-slash"></i>
                                        </span>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary w-100">Σύνδεση</button>

                                <div class="mt-3 text-center">
                                    <a href="forgot-password.php" class="text-decoration-none">Ξεχάσατε τον κωδικό σας;</a> |
                                    <a href="signup.php" class="text-decoration-none">Εγγραφή</a>
                                </div>

                                <?php if ($is_invalid): ?>
                                    <div class="alert alert-danger mt-3">
                                        <?= $is_invalid ?>
                                    </div>
                                <?php endif; ?>
                            </form>

                            <hr class="my-4">
                            <div class="text-center">
                                <p class="text-muted">Ή</p>
                                <a href="api/canva/public_canvases.php" class="btn btn-outline-info">
                                    <i class="bi bi-eye"></i> Περιηγηθείτε στους Δημόσιους Πίνακες
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('togglePassword').addEventListener('click', function (e) {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            icon.classList.toggle('bi-eye');
            icon.classList.toggle('bi-eye-slash');
        });
    </script>

    <?php include('includes/footer.php'); ?>
</body>
</html>