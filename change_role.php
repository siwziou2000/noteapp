[file name]: change_role.php
[file content begin]
<?php
session_start();

// Ελέγχουμε αν ο χρήστης είναι συνδεδεμένος και έχει ρόλο admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// Λήψη του τρέχοντος θέματος
$current_theme = isset($_SESSION['theme']) ? $_SESSION['theme'] : 'light';

// Περιλαμβάνουμε τη σύνδεση με τη βάση δεδομένων
include 'includes/database.php';

$error_message = '';
$success_message = '';

// Αρχικοποίηση μεταβλητών
$users = [];
$available_roles = []; // Διαθέσιμοι ρόλοι

// Απόκτηση των χρηστών από τη βάση δεδομένων
try {
    $sql = "SELECT user_id, username, email, role FROM users ORDER BY username";
    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Σφάλμα κατά την ανάκτηση χρηστών: " . $e->getMessage();
}

// Ορισμός των διαθέσιμων ρόλων - βάσει των ρόλων που έχεις ήδη
$available_roles = [
    'admin' => [
        'name' => 'admin',
        'description' => 'Διαχειριστής συστήματος - Πλήρης πρόσβαση σε όλες τις λειτουργίες'
    ],
    'teacher' => [
        'name' => 'teacher',
        'description' => 'Καθηγητής - Μπορεί να δημιουργεί και να διαχειρίζεται μαθήματα και αναθέσεις'
    ],
    'student' => [
        'name' => 'student', 
        'description' => 'Φοιτητής - Μπορεί να δημιουργεί και να διαχειρίζεται σημειώσεις, να παρακολουθεί μαθήματα'
    ],
    'guest' => [
        'name' => 'guest',
        'description' => 'Επισκέπτης - Περιορισμένη πρόσβαση, μόνο ανάγνωση'
    ]
];

// Εξαγωγή των μοναδικών ρόλων από τους χρήστες (για στατιστικά)
$existing_user_roles = [];
foreach ($users as $user) {
    if (!empty($user['role'])) {
        $existing_user_roles[$user['role']] = isset($existing_user_roles[$user['role']]) ? 
                                              $existing_user_roles[$user['role']] + 1 : 1;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ελέγχουμε αν λάβαμε τα απαραίτητα δεδομένα
    if (isset($_POST['user_id'], $_POST['role']) && !empty($_POST['user_id']) && !empty($_POST['role'])) {
        $user_id = intval($_POST['user_id']);
        $new_role = $_POST['role'];
        
        // Έλεγχος αν ο ρόλος είναι έγκυρος
        if (!array_key_exists($new_role, $available_roles)) {
            $error_message = "Μη έγκυρος ρόλος: $new_role";
        } else {
            try {
                // Έλεγχος αν ο χρήστης υπάρχει
                $sql = "SELECT username, role FROM users WHERE user_id = :user_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':user_id' => $user_id]);
                $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user_data) {
                    $error_message = "Ο χρήστης δεν βρέθηκε.";
                } else {
                    $username = $user_data['username'];
                    $old_role = $user_data['role'];
                    
                    // Ενημέρωση του ρόλου του χρήστη στον πίνακα users
                    $sql = "UPDATE users SET role = :role, updated_at = NOW() WHERE user_id = :user_id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':role' => $new_role,
                        ':user_id' => $user_id
                    ]);
                    
                    $success_message = "Ο ρόλος του χρήστη <strong>$username</strong> ενημερώθηκε επιτυχώς!<br>";
                    $success_message .= "<strong>Παλιός ρόλος:</strong> $old_role → <strong>Νέος ρόλος:</strong> $new_role";
                    
                    // Ενημέρωση της session αν ο admin αλλάξει τον δικό του ρόλο
                    if ($_SESSION['user_id'] == $user_id) {
                        $_SESSION['role'] = $new_role;
                        
                        // Προειδοποίηση αν ο admin αλλάξει τον δικό του ρόλο
                        if ($new_role !== 'admin') {
                            $success_message .= "<br><div class='alert alert-warning mt-2'><strong>Προσοχή!</strong> Έχετε αλλάξει τον δικό σας ρόλο. ";
                            $success_message .= "Θα χάσετε πρόσβαση σε λειτουργίες διαχειριστή. Θα ανακατευθυνθείτε σε 5 δευτερόλεπτα...</div>";
                            header("refresh:5;url=index.php");
                        }
                    }
                    
                    // Ανανέωση της λίστας χρηστών
                    $sql = "SELECT user_id, username, email, role FROM users ORDER BY username";
                    $stmt = $pdo->query($sql);
                    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Ενημέρωση στατιστικών ρόλων
                    $existing_user_roles = [];
                    foreach ($users as $user) {
                        if (!empty($user['role'])) {
                            $existing_user_roles[$user['role']] = isset($existing_user_roles[$user['role']]) ? 
                                                                  $existing_user_roles[$user['role']] + 1 : 1;
                        }
                    }
                }
            } catch (PDOException $e) {
                $error_message = "Σφάλμα βάσης δεδομένων: " . $e->getMessage();
            }
        }
    } else {
        $error_message = "Παρακαλώ επιλέξτε χρήστη και ρόλο.";
    }
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Αλλαγή Ρόλου Χρήστη</title>
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: <?= $current_theme === 'dark' ? '#343a40' : '#f8f9fa' ?>;
            color: <?= $current_theme === 'dark' ? '#ffffff' : '#212529' ?>;
            padding: 20px;
        }
        
        .container {
            background-color: <?= $current_theme === 'dark' ? '#495057' : '#ffffff' ?>;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            margin-top: 30px;
        }
        
        .form-control, .form-control:focus {
            background-color: <?= $current_theme === 'dark' ? '#495057' : '#ffffff' ?>;
            color: <?= $current_theme === 'dark' ? '#ffffff' : '#212529' ?>;
            border-color: <?= $current_theme === 'dark' ? '#6c757d' : '#ced4da' ?>;
        }
        
        .user-info {
            background-color: <?= $current_theme === 'dark' ? '#343a40' : '#e9ecef' ?>;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        .role-badge {
            font-size: 0.75em;
            padding: 3px 8px;
            border-radius: 10px;
            margin-left: 5px;
        }
        
        .badge-admin { background-color: #dc3545; color: white; }
        .badge-teacher { background-color: #007bff; color: white; }
        .badge-student { background-color: #28a745; color: white; }
        .badge-guest { background-color: #6c757d; color: white; }
        
        .role-card {
            margin-bottom: 15px;
            border-left: 4px solid;
        }
        
        .card-admin { border-left-color: #dc3545; }
        .card-teacher { border-left-color: #007bff; }
        .card-student { border-left-color: #28a745; }
        .card-guest { border-left-color: #6c757d; }
        
        .stats-card {
            background-color: <?= $current_theme === 'dark' ? '#343a40' : '#f8f9fa' ?>;
            border: 1px solid <?= $current_theme === 'dark' ? '#495057' : '#dee2e6' ?>;
        }
    </style>
</head>
<body>

<div class="container">
    <h1 class="mb-4">Αλλαγή Ρόλου Χρήστη</h1>
    
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $success_message ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $error_message ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="user_id"><strong>Επιλογή Χρήστη:</strong></label>
                    <select name="user_id" id="user_id" class="form-control" required onchange="updateUserInfo()">
                        <option value="">-- Επιλέξτε χρήστη --</option>
                        <?php foreach ($users as $user): ?>
                            <?php 
                            $current_role = !empty($user['role']) ? $user['role'] : 'guest';
                            $role_class = 'badge-' . $current_role;
                            ?>
                            <option value="<?= htmlspecialchars($user['user_id']) ?>" 
                                data-role="<?= htmlspecialchars($current_role) ?>"
                                data-username="<?= htmlspecialchars($user['username']) ?>"
                                data-email="<?= htmlspecialchars($user['email']) ?>"
                                <?= (isset($_POST['user_id']) && $_POST['user_id'] == $user['user_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['username']) ?> 
                                (<?= htmlspecialchars($user['email']) ?>)
                                <span class="role-badge <?= $role_class ?>"><?= htmlspecialchars($current_role) ?></span>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="selectedUserInfo" class="user-info mt-2" style="display: none;">
                        <strong>Επιλεγμένος Χρήστης:</strong>
                        <div id="userDetails"></div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="form-group">
                    <label for="role"><strong>Νέος Ρόλος:</strong></label>
                    <select name="role" id="role" class="form-control" required onchange="updateRoleDescription()">
                        <option value="">-- Επιλέξτε ρόλο --</option>
                        <?php foreach ($available_roles as $role_key => $role_info): ?>
                            <?php 
                            $selected = (isset($_POST['role']) && $_POST['role'] == $role_key) ? 'selected' : '';
                            ?>
                            <option value="<?= htmlspecialchars($role_key) ?>" 
                                <?= $selected ?>
                                data-description="<?= htmlspecialchars($role_info['description']) ?>">
                                <?= htmlspecialchars(ucfirst($role_key)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="roleDescription" class="mt-2 p-2 stats-card" style="display: none;">
                        <strong>Περιγραφή ρόλου:</strong>
                        <div id="roleDescText"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Περιγραφή Ρόλων</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($available_roles as $role_key => $role_info): ?>
                                <div class="col-md-3 mb-3">
                                    <div class="card role-card card-<?= $role_key ?>">
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                <span class="role-badge badge-<?= $role_key ?>">
                                                    <?= htmlspecialchars(ucfirst($role_key)) ?>
                                                </span>
                                            </h6>
                                            <p class="card-text small"><?= htmlspecialchars($role_info['description']) ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="card stats-card">
                    <div class="card-header">
                        <h5 class="mb-0">Στατιστικά Χρηστών ανά Ρόλο</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($available_roles as $role_key => $role_info): ?>
                                <?php $count = isset($existing_user_roles[$role_key]) ? $existing_user_roles[$role_key] : 0; ?>
                                <div class="col-md-3 text-center mb-3">
                                    <div class="display-4"><?= $count ?></div>
                                    <div class="role-badge badge-<?= $role_key ?> d-inline-block">
                                        <?= htmlspecialchars(ucfirst($role_key)) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-center mt-3">
                            <strong>Σύνολο Χρηστών: <?= count($users) ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
            <div>
                <button type="submit" class="btn btn-primary btn-lg">
                 Ενημέρωση Ρόλου
                </button>
                
            </div>
            
            <div>
               
                <a href="admin_dashboard.php" class="btn btn-secondary btn-lg">
                   Πίσω
                </a>
            </div>
        </div>
    </form>
</div>

<script>
    function updateUserInfo() {
        const userSelect = document.getElementById('user_id');
        const selectedOption = userSelect.options[userSelect.selectedIndex];
        const username = selectedOption.getAttribute('data-username');
        const email = selectedOption.getAttribute('data-email');
        const currentRole = selectedOption.getAttribute('data-role');
        
        const userInfoElement = document.getElementById('selectedUserInfo');
        const userDetailsElement = document.getElementById('userDetails');
        
        if (username && currentRole) {
            userDetailsElement.innerHTML = `
                <div><strong>Όνομα:</strong> ${username}</div>
                <div><strong>Email:</strong> ${email}</div>
                <div><strong>Τρέχων ρόλος:</strong> <span class="role-badge badge-${currentRole}">${currentRole}</span></div>
            `;
            userInfoElement.style.display = 'block';
        } else {
            userInfoElement.style.display = 'none';
        }
    }
    
    function updateRoleDescription() {
        const roleSelect = document.getElementById('role');
        const selectedOption = roleSelect.options[roleSelect.selectedIndex];
        const description = selectedOption.getAttribute('data-description');
        
        const roleDescElement = document.getElementById('roleDescription');
        const roleDescTextElement = document.getElementById('roleDescText');
        
        if (description) {
            roleDescTextElement.textContent = description;
            roleDescElement.style.display = 'block';
        } else {
            roleDescElement.style.display = 'none';
        }
    }
    
    function fillDemoData() {
        // Προσθήκη demo data για εύκολη δοκιμή
        if (document.getElementById('user_id').options.length > 1) {
            document.getElementById('user_id').selectedIndex = 1;
            document.getElementById('role').selectedIndex = 1;
            updateUserInfo();
            updateRoleDescription();
            
            // Εμφάνιση μηνύματος
            alert('Demo data loaded! Select a different user and role to test the functionality.');
        }
    }
    
    // Event listeners
    document.getElementById('user_id').addEventListener('change', updateUserInfo);
    document.getElementById('role').addEventListener('change', updateRoleDescription);
    
    // Αρχικοποίηση
    document.addEventListener('DOMContentLoaded', function() {
        updateUserInfo();
        updateRoleDescription();
        
        // Αν υπάρχει επιλεγμένος χρήστης από POST, εμφάνισε τις πληροφορίες
        const userSelect = document.getElementById('user_id');
        if (userSelect.value) {
            updateUserInfo();
        }
    });
</script>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.6.0/css/fontawesome.min.css">

<!-- Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
