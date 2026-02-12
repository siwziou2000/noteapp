<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/functions.php');


// Έλεγχος πρόσβασης admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /noteapp/login.php");
    exit;
}

// Λήψη user_id από GET
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($user_id <= 0) {
    die("Μη έγκυρο ID χρήστη.");
}

// Ανάκτηση στοιχείων χρήστη
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :id");
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    die("Ο χρήστης δεν βρέθηκε.");
}

// Ενημέρωση
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = $_POST['fullname'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? 'user';

    try {
        $stmt = $pdo->prepare("UPDATE users SET fullname = :fullname, email = :email, role = :role WHERE user_id = :id");
        $stmt->execute([
            ':fullname' => $fullname,
            ':email' => $email,
            ':role' => $role,
            ':id' => $user_id
        ]);
        header("Location: users.php?updated=1");
        exit;
    } catch (PDOException $e) {
        die("Σφάλμα κατά την ενημέρωση: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Επεξεργασία Χρήστη</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h3>Επεξεργασία Χρήστη</h3>
    <form method="POST" class="mt-4">

        <div class="mb-3">
            <label for="fullname" class="form-label">Ονοματεπώνυμο:</label>
            <input type="text" name="fullname" id="fullname" class="form-control" value="<?= htmlspecialchars($user['fullname']) ?>" required>
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Email:</label>
            <input type="email" name="email" id="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
        </div>

        <div class="mb-3">
            <label for="role" class="form-label">Ρόλος:</label>
            <select name="role" id="role" class="form-select">
                <?php
                $roles = ['admin' => 'Διαχειριστής', 'teacher' => 'Καθηγητής', 'student' => 'Μαθητής', 'guest' => 'Επισκέπτης'];
                foreach ($roles as $value => $label) {
                    $selected = ($user['role'] === $value) ? 'selected' : '';
                    echo "<option value=\"$value\" $selected>$label</option>";
                }
                ?>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Αποθήκευση Αλλαγών</button>
        <a href="users.php" class="btn btn-secondary">Άκυρο</a>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
