<?php
session_start();
include 'includes/database.php';

// Ελέγχει αν η παράμετρος 'id' υπάρχει στην URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('Απαιτείται αναγνωριστικό χρήστη.');
}

$userId = intval($_GET['id']); // Μετατροπή σε ακέραιο αριθμό για ασφάλεια

// Φορτώνει τα στοιχεία του χρήστη από τη βάση δεδομένων
$query = "SELECT username FROM users WHERE user_id = :userId";
$stmt = $pdo->prepare($query);
$stmt->execute(['userId' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Αν δεν βρεθεί ο χρήστης, δείχνει μήνυμα σφάλματος
if (!$user) {
    die('Ο χρηστης δεν βρεθηκε');
}

// Επεξεργασία της υποβολής της φόρμας
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Ελέγχει αν τα πεδία είναι κενά ή αν οι κωδικοί δεν ταιριάζουν
    if (empty($newPassword) || empty($confirmPassword)) {
        $error = 'Απαιτούνται και τα δύο πεδία κωδικού πρόσβασης.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Οι κωδικοι δεν ταιριαζουν.';
    } else {
        // Κωδικοποιεί τον νέο κωδικό
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        // Ενημερώνει τον κωδικό στη βάση δεδομένων
        $updateQuery = "UPDATE users SET password_hash = :password WHERE user_id = :userId";
        $updateStmt = $pdo->prepare($updateQuery);
        $updateStmt->execute([
            'password' => $hashedPassword,
            'userId' => $userId,
        ]);

        $success = 'Οι κωδικοι προσβασης ενημερωθηκαν σωστα.';
    }
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Αλλαγή Κωδικού</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>Αλλαγή Κωδικού Χρήστη: <?php echo htmlspecialchars($user['username']); ?></h2>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <form action="change_password.php?id=<?php echo $userId; ?>" method="post">
        <div class="form-group">
            <label for="new_password">Νέος Κωδικός</label>
            <input type="password" class="form-control" id="new_password" name="new_password" required>
        </div>
        <div class="form-group">
            <label for="confirm_password">Επιβεβαίωση Κωδικού</label>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
        </div>
        <button type="submit" class="btn btn-primary">Αλλαγή Κωδικού</button>
        <a href="user1.php" class="btn btn-secondary">Επιστροφή</a>
    </form>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
