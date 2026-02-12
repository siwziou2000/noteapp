<?php
include('includes/database.php');
session_start();

if (!isset($_GET['id']) || !isset($_SESSION['user_id'])) {
    echo "Απροσδιόριστη πρόσβαση!";
    exit;
}

$group_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Ανάκτηση των δεδομένων της ομάδας
$query = "SELECT * FROM groups WHERE id = :group_id AND user_id = :user_id";
$stmt = $pdo->prepare($query);
$stmt->bindValue(':group_id', $group_id, PDO::PARAM_INT);
$stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();

$group = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$group) {
    echo "Η ομάδα δεν βρέθηκε!";
    exit;
}

// Έλεγχος αν η φόρμα υποβλήθηκε
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $description = $_POST['description'];

    // Ενημέρωση της ομάδας στη βάση δεδομένων
    $query = "UPDATE groups SET name = :name, description = :description, updated_at = NOW() WHERE id = :group_id AND user_id = :user_id";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':name', $name);
    $stmt->bindValue(':description', $description);
    $stmt->bindValue(':group_id', $group_id, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    
    $stmt->execute();

    // Ανακατεύθυνση στην σελίδα των ομάδων
    header("Location: groups.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Επεξεργασία Ομάδας</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container py-4">
    <h1>Επεξεργασία Ομάδας</h1>

    <form method="POST">
        <div class="mb-3">
            <label for="name" class="form-label">Όνομα Ομάδας</label>
            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($group['name']) ?>" required>
        </div>
        
        <div class="mb-3">
            <label for="description" class="form-label">Περιγραφή</label>
            <textarea class="form-control" id="description" name="description" rows="3" required><?= htmlspecialchars($group['description']) ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Αποθήκευση Αλλαγών</button>
    </form>
</div>

</body>
</html>
