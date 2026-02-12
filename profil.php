<?php
session_start();
include 'includes/database.php';

// Έλεγχος αν ο χρήστης είναι συνδεδεμένος
if (!isset($_SESSION['user_id'])) {
    echo "Πρέπει να είστε συνδεδεμένοι για να δείτε το προφίλ σας.";
    exit;
}

// Έλεγχος για το ID χρήστη (είτε δικό του είτε άλλου)
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Μη έγκυρο ID χρήστη.");
}

$user_id = (int)$_GET['id'];

// Φέρνουμε όλα τα στοιχεία του χρήστη
$query = "SELECT * FROM users WHERE user_id = :id";
$stmt = $pdo->prepare($query);
$stmt->bindParam(':id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Ο χρήστης δεν βρέθηκε.");
}

// Ενημέρωση στοιχείων χρήστη (αν υποβλήθηκε η φόρμα)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $profile_picture = $user['avatar']; // Τρέχουσα εικόνα

    // Ανέβασμα νέας εικόνας αν υπάρχει
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
        $target_dir = "uploads/";
        $target_file = $target_dir . basename($_FILES["avatar"]["name"]);
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        if (getimagesize($_FILES["avatar"]["tmp_name"]) !== false) {
            if (move_uploaded_file($_FILES["avatar"]["tmp_name"], $target_file)) {
                $profile_picture = $target_file;
            } else {
                echo "Σφάλμα στο ανέβασμα εικόνας.";
                exit;
            }
        } else {
            echo "Το αρχείο δεν είναι έγκυρη εικόνα.";
            exit;
        }
    }

    // Ενημέρωση στη βάση
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $updateQuery = "UPDATE users SET fullname = :fullname, email = :email, password_hash = :password, avatar = :avatar, updated_at = NOW() WHERE user_id = :user_id";
        $stmt = $pdo->prepare($updateQuery);
        $stmt->bindParam(':password', $hashed_password);
    } else {
        $updateQuery = "UPDATE users SET fullname = :fullname, email = :email, avatar = :avatar, updated_at = NOW() WHERE user_id = :user_id";
        $stmt = $pdo->prepare($updateQuery);
    }

    $stmt->bindParam(':fullname', $fullname);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':avatar', $profile_picture);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();

    echo "<div class='alert alert-success'>Τα στοιχεία ενημερώθηκαν με επιτυχία.</div>";

    // Αναφόρτωση των νέων δεδομένων
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :id");
    $stmt->bindParam(':id', $user_id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Προφίλ Χρήστη</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.12.1/font/bootstrap-icons.min.css">
</head>
<body>
<div class="container mt-5">
    <h2>Προφίλ Χρήστη</h2>

    <form method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label for="fullname">Πλήρες Όνομα</label>
            <input type="text" class="form-control" name="fullname" value="<?= htmlspecialchars($user['fullname']) ?>" required>
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
        </div>
        <div class="form-group">
            <label for="password">Νέος Κωδικός (προαιρετικό)</label>
            <input type="password" class="form-control" name="password" placeholder="Αφήστε κενό για να μην αλλάξει">
        </div>
        <div class="form-group">
            <label for="avatar">Φωτογραφία Προφίλ</label>
            <input type="file" class="form-control-file" name="avatar">
            <?php if (!empty($user['avatar'])): ?>
                <img src="<?= $user['avatar'] ?>" class="img-thumbnail mt-2" style="width: 150px;">
            <?php endif; ?>
        </div>
        <button type="submit" class="btn btn-primary">Αποθήκευση Αλλαγών</button>
    </form>

    <hr>

    <h4>Πληροφορίες Λογαριασμού</h4>
    <p><strong>ID:</strong> <?= htmlspecialchars($user['user_id']) ?></p>
    <p><strong>Όνομα Χρήστη:</strong> <?= htmlspecialchars($user['username']) ?></p>
    <p><strong>Πλήρες Όνομα:</strong> <?= htmlspecialchars($user['fullname']) ?></p>
    <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
    <p><strong>Ρόλος:</strong> <?= htmlspecialchars($user['role']) ?></p>
    <p><strong>Φύλο:</strong> <?= htmlspecialchars($user['gender'] ?? '—') ?></p>
    <p><strong>Κατάσταση:</strong> <?= $user['isactive'] ? 'Ενεργός' : 'Ανενεργός' ?></p>
    <p><strong>Δημιουργήθηκε:</strong> <?= htmlspecialchars($user['created_at']) ?></p>
    <p><strong>Τελευταία Ενημέρωση:</strong> <?= htmlspecialchars($user['updated_at']) ?></p>

    <a href="users.php" class="btn btn-secondary mt-3"><i class="bi bi-arrow-left"></i> Πίσω</a>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
