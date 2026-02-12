<?php
session_start();
require "includes/database.php";

if (isset($_SESSION['user_id'])) {
    $_SESSION['message'] = "Είστε ήδη συνδεδεμένος!";
    header("Location: api/canva/home.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $gender = $_POST['gender'];
    $username = trim($_POST['username']);
    $role = $_POST['role'];

    // Validation
    if (empty($fullname) || empty($email) || empty($password) || empty($confirm_password) || empty($username) || empty($role)) {
        $error = "Όλα τα πεδία είναι υποχρεωτικά.";
    } elseif ($password !== $confirm_password) {
        $error = "Οι κωδικοί πρόσβασης δεν ταιριάζουν.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Παρακαλώ εισάγετε ένα έγκυρο email.";
    } elseif (strlen($password) < 6) {
        $error = "Ο κωδικός πρέπει να έχει τουλάχιστον 6 χαρακτήρες.";
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            // Έλεγχος αν υπάρχει ήδη το email ή username
            $sql = "SELECT * FROM users WHERE email = :email OR username = :username";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $error = "Το email ή το όνομα χρήστη χρησιμοποιείται ήδη.";
            } else {
                // Δημιουργία των Tokens
                $token = bin2hex(random_bytes(32)); // Για το email verification
                $public_token = bin2hex(random_bytes(16)); // Το access_token για τα shared canvases
                $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

                // Εισαγωγή χρήστη (Προστέθηκε το public_token)
                $sql = "INSERT INTO users (fullname, email, password_hash, gender, username, role, avatar, public_token) 
                        VALUES (:fullname, :email, :password_hash, :gender, :username, :role, :avatar, :public_token)";
                $stmt = $pdo->prepare($sql);

                $default_avatar = "uploads/default-avatar.png";

                $stmt->bindParam(':fullname', $fullname, PDO::PARAM_STR);
                $stmt->bindParam(':email', $email, PDO::PARAM_STR);
                $stmt->bindParam(':password_hash', $password_hash, PDO::PARAM_STR);
                $stmt->bindParam(':gender', $gender, PDO::PARAM_STR);
                $stmt->bindParam(':username', $username, PDO::PARAM_STR);
                $stmt->bindParam(':role', $role, PDO::PARAM_STR);
                $stmt->bindParam(':avatar', $default_avatar, PDO::PARAM_STR);
                $stmt->bindParam(':public_token', $public_token, PDO::PARAM_STR); // Σύνδεση του νέου token

                if ($stmt->execute()) {
                    $lastInsertId = $pdo->lastInsertId();

                    // Εισαγωγή token επαλήθευσης email
                    $sql_verification = "INSERT INTO email_verifications (user_id, email, token, expires_at) 
                                        VALUES (:user_id, :email, :token, :expires_at)";
                    $stmt = $pdo->prepare($sql_verification);
                    $stmt->bindParam(':user_id', $lastInsertId, PDO::PARAM_INT);
                    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
                    $stmt->bindParam(':token', $token, PDO::PARAM_STR);
                    $stmt->bindParam(':expires_at', $expires_at, PDO::PARAM_STR);
                    
                    if ($stmt->execute()) {
                        $_SESSION['new_user_token'] = $token;
                        $_SESSION['new_user_email'] = $email;
                        $_SESSION['new_user_id'] = $lastInsertId;
                        
                        header("Location: show-verification-link.php");
                        exit;
                    } else {
                        $error = "Σφάλμα κατά τη δημιουργία token επαλήθευσης.";
                    }
                } else {
                    $error = "Προέκυψε σφάλμα κατά την εγγραφή.";
                }
            }
        } catch (PDOException $e) {
            $error = "Σφάλμα βάσης δεδομένων: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Εγγραφή</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/water.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h1>Δημιουργία Λογαριασμού</h1>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="signup.php" method="post">
            <div class="mb-3">
                <label for="fullname" class="form-label">Ονοματεπώνυμο</label>
                <input type="text" name="fullname" class="form-control" required 
                       value="<?= isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : '' ?>">
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required 
                       value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
            </div>

            <div class="mb-3">
                <label for="username" class="form-label">Όνομα χρήστη</label>
                <input type="text" name="username" class="form-control" required 
                       value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
            </div>

            <div class="mb-3">
                <label for="role" class="form-label">Ρόλος</label>
                <select name="role" class="form-control" required>
                    <option value="student" <?= (isset($_POST['role']) && $_POST['role'] == 'student') ? 'selected' : '' ?>>Φοιτητής</option>
                    <option value="teacher" <?= (isset($_POST['role']) && $_POST['role'] == 'teacher') ? 'selected' : '' ?>>Καθηγητής</option>
                    <option value="admin" <?= (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'selected' : '' ?>>Διαχειριστής</option>
                    <option value="guest" <?= (isset($_POST['role']) && $_POST['role'] == 'guest') ? 'selected' : '' ?>>Επισκέπτης</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Κωδικός πρόσβασης</label>
                <input type="password" name="password" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="confirm_password" class="form-label">Επιβεβαίωση κωδικού</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="gender" class="form-label">Φύλο</label>
                <select name="gender" class="form-control" required>
                    <option value="Female" <?= (isset($_POST['gender']) && $_POST['gender'] == 'Female') ? 'selected' : '' ?>>Γυναίκα</option>
                    <option value="Male" <?= (isset($_POST['gender']) && $_POST['gender'] == 'Male') ? 'selected' : '' ?>>Άνδρας</option>
                    <option value="Other" <?= (isset($_POST['gender']) && $_POST['gender'] == 'Other') ? 'selected' : '' ?>>Άλλο</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Εγγραφή</button>
        </form>

        <p class="mt-3">Έχετε ήδη λογαριασμό; <a href="login.php">Συνδεθείτε εδώ</a></p>
    </div>
</body>
</html>