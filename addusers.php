<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = $_POST['fullname'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Έλεγχος αν το email υπάρχει ήδη
    $query = "SELECT * FROM users WHERE email = :email";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $_SESSION['message'] = "Το email υπάρχει ήδη στη βάση δεδομένων.";
    } else {
        $query = "INSERT INTO users (fullname, username, email, role, password_hash) 
                  VALUES (:fullname, :username, :email, :role, :password_hash)";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':fullname', $fullname);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':role', $role);
        $stmt->bindParam(':password_hash', $password);

        if ($stmt->execute()) {
            $_SESSION['message'] = "Ο χρήστης προστέθηκε επιτυχώς!";
        } else {
            $_SESSION['message'] = "Σφάλμα κατά την προσθήκη του χρήστη.";
        }
    }

    header('Location: addusers.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Προσθήκη Νέου Χρήστη</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>Προσθήκη Νέου Χρήστη</h2>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-info">
            <?= htmlspecialchars($_SESSION['message']) ?>
            <?php unset($_SESSION['message']); ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label for="fullname">Όνομα</label>
            <input type="text" class="form-control" id="fullname" name="fullname" required>
        </div>

        <div class="form-group">
            <label for="username">Όνομα Χρήστη</label>
            <input type="text" class="form-control" id="username" name="username" required>
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" class="form-control" id="email" name="email" required>
        </div>

        <div class="form-group">
            <label for="role">Ρόλος</label>
            <select class="form-control" id="role" name="role">
                <option value="admin">Διαχειριστής</option>
                <option value="user">Χρήστης</option>
                <option value="guest">Επισκέπτης</option>
                <option value="teacher">Καθηγητής</option>
                <option value="student">Μαθητής</option>
            </select>
        </div>

        <div class="form-group">
            <label for="password">Κωδικός</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>

        <button type="submit" class="btn btn-primary">Προσθήκη Χρήστη</button>
        <a href="users.php" class="btn btn-secondary ml-2">Πίσω στη Λίστα Χρηστών</a>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
