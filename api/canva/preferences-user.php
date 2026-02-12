<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {
    // Φόρτωμα πληροφοριών χρήστη
    $stmt = $pdo->prepare("
        SELECT *, 
        (email_verified IS NOT NULL) AS is_verified 
        FROM users 
        WHERE user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Σφάλμα βάσης: " . $e->getMessage());
}

// Επεξεργασία υποβολής φόρμας
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Μη έγκυρο αίτημα");
    }

    // Επεξεργασία avatar
    if (isset($_POST['update_avatar'])) {
        $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/noteapp/uploads/avatars/";
        $imageFileType = strtolower(pathinfo($_FILES["avatar"]["name"], PATHINFO_EXTENSION));
        $new_filename = uniqid() . "." . $imageFileType;
        $target_path = $target_dir . $new_filename;
        
        // Έλεγχοι ασφαλείας
        $check = getimagesize($_FILES["avatar"]["tmp_name"]);
        if ($check === false) {
            $_SESSION['error'] = "Μη έγκυρη εικόνα";
            header("Location: preferences-user.php");
            exit();
        }
        
        if ($_FILES["avatar"]["size"] > 2000000) {
            $_SESSION['error'] = "Μέγιστο μέγεθος αρχείου: 2MB";
            header("Location: preferences-user.php");
            exit();
        }
        
        if (!in_array($imageFileType, ["jpg", "png", "jpeg", "gif"])) {
            $_SESSION['error'] = "Μόνο JPG, JPEG, PNG & GIF επιτρέπονται";
            header("Location: preferences-user.php");
            exit();
        }
        
        if (move_uploaded_file($_FILES["avatar"]["tmp_name"], $target_path)) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE user_id = ?");
                $stmt->execute([$new_filename, $_SESSION['user_id']]);
                $_SESSION['success'] = "Επιτυχής ενημέρωση avatar!";
            } catch (PDOException $e) {
                $_SESSION['error'] = "Σφάλμα βάσης δεδομένων";
            }
        } else {
            $_SESSION['error'] = "Σφάλμα μεταφόρτωσης αρχείου";
        }
        header("Location: preferences-user.php");
        exit();
    }

    // Ενημέρωση βασικών πληροφοριών
    if (isset($_POST['email'])) {
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $notifications_pref = $_POST['notifications_pref'];
        
        // Έλεγχος για διπλό email
        try {
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $existing_user = $stmt->fetch();
            
            if ($existing_user && $existing_user['user_id'] != $_SESSION['user_id']) {
                $_SESSION['error'] = "Το email χρησιμοποιείται ήδη";
                header("Location: preferences-user.php");
                exit();
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Σφάλμα βάσης δεδομένων";
            header("Location: preferences-user.php");
            exit();
        }
        
        try {
            $pdo->beginTransaction();
            
            if ($email !== $user['email']) {
                $verification_token = bin2hex(random_bytes(32));
                $stmt = $pdo->prepare("
                    UPDATE users SET 
                    email = ?, 
                    notifications_pref = ?,
                    email_verified = NULL,
                    verification_token = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$email, $notifications_pref, $verification_token, $_SESSION['user_id']]);
                
                // Αποστολή email επαλήθευσης (Προσθέστε τον κώδικα αποστολής email)
                $verify_link = "https://noteapp/api/canvas/verify.php?token=$verification_token";
                // mail($email, "Επαλήθευση Email", "Πατήστε τον σύνδεσμο: $verify_link");
                
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users SET 
                    notifications_pref = ? 
                    WHERE user_id = ?
                ");
                $stmt->execute([$notifications_pref, $_SESSION['user_id']]);
            }
            
            $pdo->commit();
            $_SESSION['success'] = "Επιτυχής ενημέρωση προφίλ!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Σφάλμα ενημέρωσης: " . $e->getMessage();
        }
    }

    // Αλλαγή κωδικού πρόσβασης
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (password_verify($current_password, $user['password'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) < 8) {
                    $_SESSION['error'] = "Ο κωδικός πρέπει να έχει τουλάχιστον 8 χαρακτήρες";
                    header("Location: preferences-user.php");
                    exit();
                }
                
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                try {
                    $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?")
                       ->execute([$hashed_password, $_SESSION['user_id']]);
                    $_SESSION['success'] = "Επιτυχής αλλαγή κωδικού!";
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Σφάλμα βάσης δεδομένων";
                }
            } else {
                $_SESSION['error'] = "Οι κωδικοί δεν ταιριάζουν";
            }
        } else {
            $_SESSION['error'] = "Λανθασμένος τρέχων κωδικός";
        }
    }
    
    header("Location: preferences-user.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/preferences-user.css">
</head>
<body class="bg-light">
    <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/header.php'; ?>
    
    <div class="container py-5">
        <div class="row g-4">
            <!-- Πλαίσιο προφίλ -->
            <div class="col-lg-4">
                <div class="card profile-card">
                    <div class="card-body text-center">
                        <img src="/noteapp/uploads/avatars/<?= htmlspecialchars($user['avatar'] ?? 'default.png') ?>" 
                             class="avatar-preview rounded-circle mb-3 border">
                        <h4 class="mb-1"><?= htmlspecialchars($user['username']) ?></h4>
                        <div class="mb-3">
                            <?php if ($user['is_verified']): ?>
                                <span class="verified-badge">
                                    <i class="fas fa-check-circle"></i> Επαληθευμένος
                                </span>
                            <?php else: ?>
                                <span class="unverified-badge">
                                    <i class="fas fa-exclamation-triangle"></i> Μη επαληθευμένος
                                </span>
                            <?php endif; ?>
                        </div>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <div class="input-group mb-3">
                                <input type="file" name="avatar" class="form-control" accept="image/*" required>
                                <button type="submit" name="update_avatar" class="btn btn-outline-primary">
                                    <i class="fas fa-upload"></i> Αλλαγή
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Ρυθμίσεις -->
            <div class="col-lg-8">
                <div class="card profile-card">
                    <div class="card-body">
                        <h4 class="card-title mb-4">
                            <i class="fas fa-user-cog"></i> Ρυθμίσεις Προφίλ
                        </h4>

                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
                            <?php unset($_SESSION['error']); ?>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
                            <?php unset($_SESSION['success']); ?>
                        <?php endif; ?>

                        <!-- Βασικές Πληροφορίες -->
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            
                            <div class="mb-4">
                                <label class="form-label">Διεύθυνση Email</label>
                                <input type="email" name="email" 
                                       value="<?= htmlspecialchars($user['email']) ?>" 
                                       class="form-control form-control-lg" required>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Προτιμήσεις Ειδοποιήσεων</label>
                                <select name="notifications_pref" class="form-select form-select-lg" required>
                                    <option value="immediate" <?= $user['notifications_pref'] === 'immediate' ? 'selected' : '' ?>>Άμεσες ειδοποιήσεις</option>
                                    <option value="daily" <?= $user['notifications_pref'] === 'daily' ? 'selected' : '' ?>>Ημερήσια ανασκόπηση</option>
                                    <option value="weekly" <?= $user['notifications_pref'] === 'weekly' ? 'selected' : '' ?>>Εβδομαδιαία ανασκόπηση</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-save"></i> Αποθήκευση Αλλαγών
                            </button>
                        </form>

                        <hr class="my-5">

                        <!-- Αλλαγή Κωδικού Πρόσβασης -->
                        <div class="security-settings">
                            <h5 class="mb-4"><i class="fas fa-shield-alt"></i> Ασφάλεια</h5>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label">Τρέχων Κωδικός</label>
                                    <input type="password" name="current_password" 
                                           class="form-control form-control-lg" 
                                           placeholder="Εισάγετε τον τρέχοντα κωδικό" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Νέος Κωδικός</label>
                                    <input type="password" name="new_password" 
                                           class="form-control form-control-lg" 
                                           placeholder="Εισάγετε νέο κωδικό (τουλάχιστον 8 χαρακτήρες)" 
                                           minlength="8" required>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label">Επιβεβαίωση Κωδικού</label>
                                    <input type="password" name="confirm_password" 
                                           class="form-control form-control-lg" 
                                           placeholder="Επιβεβαιώστε τον νέο κωδικό" required>
                                </div>
                                
                                <button type="submit" name="change_password" 
                                        class="btn btn-warning btn-lg w-100">
                                    <i class="fas fa-key"></i> Ενημέρωση Κωδικού
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>