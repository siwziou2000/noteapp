<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php');

// Έλεγχος πρόσβασης - μόνο για διαχειριστές
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /noteapp/login.php");
    exit;
}

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Αρχικοποίηση μεταβλητών
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // Αριθμός εγγραφών ανά σελίδα
$offset = ($page - 1) * $limit;

try {
    // Βασικό query
    $query = "SELECT SQL_CALC_FOUND_ROWS user_id, username, fullname, email, role, isactive,avatar, email_verified 
              FROM users";
    
    // Προσθήκη αναζήτησης αν υπάρχει
    $params = [];
    if (!empty($searchQuery)) {
        $query .= " WHERE fullname LIKE :searchQuery OR email LIKE :searchQuery OR username LIKE :searchQuery";
        $params[':searchQuery'] = '%' . $searchQuery . '%';
    }
    
  
    // Εκτέλεση query
    $stmt = $pdo->prepare($query);
    
    foreach ($params as $key => &$val) {
        if ($key === ':limit' || $key === ':offset') {
            $stmt->bindParam($key, $val, PDO::PARAM_INT);
        } else {
            $stmt->bindParam($key, $val);
        }
    }
    
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $users = [];
    $totalPages = 1;
}

?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Διαχείριση Χρηστών</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.12.1/font/bootstrap-icons.min.css">
   
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="/noteapp/admin">NoteApp Admin</a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mr-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php"><i class="bi bi-house-door"></i> Αρχική</a>
                    </li>
                    <li class="nav-item active">
                        <a class="nav-link" href="users.php"><i class="bi bi-people"></i> Χρήστες</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="addusers.php"><i class="bi bi-person-plus"></i> Προσθήκη Χρήστη</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <span class="navbar-text mr-3">
                            <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['username'] ?? ''); ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i> Αποσύνδεση</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2><i class="bi bi-people"></i> Διαχείριση Χρηστών</h2>
            </div>
            
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= $error; ?></div>
        <?php endif; ?>

        <!-- Φόρμα Αναζήτησης -->
       <!-- Replace your existing search form with this one -->
<div class="card mb-4">
    <div class="card-body">
        <div class="input-group w-100">
            <input type="text" class="form-control" id="liveSearch" 
                   placeholder="Αναζήτηση με όνομα, email ή username...">
            <div class="input-group-append">
                <button class="btn btn-primary" id="searchButton">
                    <i class="bi bi-search"></i> Αναζήτηση
                </button>
            </div>
        </div>
    </div>
</div>

    <!-- Πίνακας Χρηστών -->
    <table class="table table-bordered table-responsive-md">
    <thead>
        <tr>
            <th>ID</th>
            <th>Avatar</th> <!-- Νέα στήλη -->
            <th>Όνομα Χρήστη</th>
            <th>Όνομα</th>
            <th>Email</th>
            <th>Ρόλος</th>
            <th>Email Επαληθευμένο</th>
            <th>Πίνακας</th>
            <th>Ενέργειες</th>
            <th>Κατάσταση</th>
        </tr>
    </thead>
    <tbody>
    <tbody id="usersTableBody">
        <?php foreach ($users as $user): ?>
            <tr>
                <td><?= htmlspecialchars($user['user_id']); ?></td>

                <!-- Avatar εικόνα -->
                <td>
                    <img 
                        src="<?= !empty($user['avatar']) ? htmlspecialchars($user['avatar']) : 'uploads/default-avatar.png'; ?>" 
                        alt="Avatar" 
                        width="50" height="50" 
                        class="rounded-circle"
                    >
                </td>

                <td><?= htmlspecialchars($user['username']); ?></td>
                <td><?= htmlspecialchars($user['fullname']); ?></td>
                <td><?= htmlspecialchars($user['email']); ?></td>
                <td><?= htmlspecialchars($user['role']); ?></td>

                <td>
                    <?= ($user['email_verified'] == 1) 
                        ? '<span class="text-success">Επαληθευμένο</span>' 
                        : '<span class="text-danger">Μη Επαληθευμένο</span>'; ?>
                </td>

                <td>
                    <a href="admin/view_canvases_users.php?id=<?= $user['user_id']; ?>" class="btn btn-info btn-sm">Προβολή Πίνακα</a>
                </td>

                <td>
                    <div class="dropdown">
                        <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" id="dropdownActions" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            Ενέργειες
                        </button>
                        <div class="dropdown-menu" aria-labelledby="dropdownActions">
                            <a class="dropdown-item" href="profil.php?id=<?= $user['user_id']; ?>"><i class="bi bi-eye"></i>Προβολή Προφίλ</a>
                            <a class="dropdown-item" href="edit_user.php?id=<?= $user['user_id']; ?>"><i class="bi bi-pencil-square"></i>Επεξεργασία</a>
                            <a class="dropdown-item" href="change_password.php?id=<?= $user['user_id']; ?>"><i class="bi bi-eye-fill"></i>Αλλαγή Κωδικού</a>
                            <form action="delete_user.php" method="POST"onsubmit="return confirm('Είστε σίγουροι ότι θέλετε να διαγράψετε αυτόν τον χρήστη;')">
                            <input type="hidden" name="id" value="<?= $user['user_id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                                <button type="submit" class="dropdown-item text-danger">
                                            <i class="bi bi-trash"></i> Διαγραφή
                                            </button>
                            </form>

                    </div>
                </td>

                <td>
                    <button 
                        class="btn btn-<?= ($user['isactive'] == 1) ? 'warning' : 'success'; ?> btn-sm toggle-status" 
                        data-id="<?= $user['user_id']; ?>" 
                        data-status="<?= $user['isactive']; ?>"
                    >
                        <?= ($user['isactive'] == 1) ? 'Απενεργοποίηση' : 'Ενεργοποίηση'; ?>
                    </button>
                </td>

                <td>
                  
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
        </div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>

$('.toggle-status').click(function() {
    console.log("Button clicked!");
    var button = $(this);
    var userId = button.data('id');
    var currentStatus = button.data('status');

    console.log("User ID:", userId, "Current Status:", currentStatus);

    $.ajax({
        url: 'toggle_status.php',
        type: 'POST',
        data: {
            id: userId,
            status: currentStatus
        },
        success: function(response) {
            try {
                response = JSON.parse(response);
            } catch (e) {
                console.error("Invalid JSON response:", response);
                alert('Προέκυψε σφάλμα κατά την επεξεργασία της απάντησης.');
                return;
            }

            console.log("AJAX response:", response);
            if (response.success) {
                location.reload();
            } else {
                alert('Σφάλμα: ' + (response.message || 'Άγνωστο σφάλμα'));
            }
        },
        error: function(xhr, status, error) {
            console.error("AJAX error:", error);
            alert('Προέκυψε σφάλμα κατά την επικοινωνία με τον διακομιστή.');
        }
    });
});



$(document).ready(function() {
    // Live search functionality
    $('#liveSearch').on('keyup', function() {
        var query = $(this).val();
        performLiveSearch(query);
    });
    
    $('#searchButton').click(function() {
        var query = $('#liveSearch').val();
        performLiveSearch(query);
    });
    
    function performLiveSearch(query) {
        if (query.length > 0) {
            $.ajax({
                url: '/noteapp/live-search/search.php',
                type: 'POST',
                data: { search: query },
                success: function(data) {
                    $('#usersTableBody').html(data);
                },
                error: function(xhr, status, error) {
                    console.error("Search error:", error);$('#usersTableBody').html('<tr><td colspan="11">Προέκυψε σφάλμα κατά την αναζήτηση</td></tr>');
                }
            });
        } else {
            // If search is empty, reload the full table
            location.reload();
        }
    }
});
    



</script>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

