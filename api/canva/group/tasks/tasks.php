<?php
// Ξεκινάμε τη συνεδρία για να έχουμε πρόσβαση στη μεταβλητή $_SESSION
session_start();

$pageTitle = "ΕΡΓΑΣΙΕΣ";

require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';



// Ελέγχουμε αν ο χρήστης είναι συνδεδεμένος, αν δεν είναι, ανακατευθύνουμε σε σελίδα σύνδεσης
if (!isset($_SESSION['user_id'])) {
    header("Location: /noteapp/login.php");
    exit;
}

$user_id = $_SESSION['user_id']; // Λήψη του user_id από την session του χρήστη

// Ανάκτηση εργασιών για τον χρήστη από τη βάση δεδομένων
$stmt = $pdo->prepare("SELECT * FROM tasks WHERE user_id = ? ORDER BY due_date ASC");
$stmt->execute([$user_id]); // Εκτέλεση του query με το user_id
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/api/canva/include/menu.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<main class="container mt-5">

    <h2>ΟΙ ΕΡΓΑΣΙΕΣ ΜΟΥ</h2>

    <!-- Κουμπί προσθήκης νέας εργασίας -->
    <button class="btn btn-primary mb-3">
        <a href="add_tasks.php" class="text-white text-decoration-none">ΝΕΑ ΕΡΓΑΣΙΑ</a>
    </button>

    <!-- Λίστα Εργασιών -->
    <ul class="list-group mb-4">
        <?php
        // Ανάκτηση εργασιών από τη βάση δεδομένων
        if (count($tasks) > 0):
            foreach ($tasks as $task):
        ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <?= htmlspecialchars($task['title']); ?>
                <span class="badge bg-<?= $task['priority'] === 'High' ? 'danger' : ($task['priority'] === 'Medium' ? 'warning' : 'success'); ?>">
                    <?= htmlspecialchars($task['priority']); ?>
                </span>
            </li>
        <?php 
            endforeach;
        else: 
            echo "<li class='list-group-item'>ΔΕΝ ΒΡΕΘΗΚΑΝ ΕΡΓΑΣΙΕΣ</li>";
        endif;
        ?>
    </ul>
</main>


