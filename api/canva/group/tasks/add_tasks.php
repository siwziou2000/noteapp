<?php

session_start ();
$pageTitle = "ΝΕΑ ΕΡΓΑΣΙΑ";
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';



// Υποβολή φόρμας - Αποθήκευση νέας εργασίας στη βάση δεδομένων
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $due_date = $_POST['due_date'];
    $priority = $_POST['priority'];
    $user_id = $_SESSION['user_id'];  // Υποθέτουμε ότι το user_id αποθηκεύεται στη session του χρήστη

    // Εισαγωγή της εργασίας στον πίνακα tasks
    $stmt = $pdo->prepare("INSERT INTO tasks (title, due_date, priority, user_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$title, $due_date, $priority, $user_id]);

    // Ανακατεύθυνση στην σελίδα tasks.php μετά την εισαγωγή
    header("Location: tasks.php");
    exit;
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/api/canva/include/menu.php';
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ΕΡΓΑΣΙΕΣ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
  </head>
  <body>
<main class="container mt-5">
    <h2>ΝΕΑ ΕΡΓΑΣΙΑ</h2>


    <div class="card card-body mb-4">
        <form method="post">
            <div class="mb-3">
                <label class="form-label">Title</label>
                <input type="text" class="form-control" name="title" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Due Date</label>
                <input type="date" class="form-control" name="due_date" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Priority</label>
                <select class="form-select" name="priority" required>
                    <option value="Low">Low</option>
                    <option value="Medium">Medium</option>
                    <option value="High">High</option>
                </select>
            </div>
            <button type="submit" class="btn btn-success">Save Task</button>
            <a href="tasks.php" class="btn btn-secondary">Back to Tasks</a>
        </form>
    </div>
</main>




<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>
  </body>
</html>
</body>