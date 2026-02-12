<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';



if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success_message = '';

try {
    // Φόρτωμα υφιστάμενων preferences
    $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $preferences = $stmt->fetch(PDO::FETCH_ASSOC);

    // Επεξεργασία φόρμας
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Επικύρωση και φιλτράρισμα δεδομένων
        $language = filter_input(INPUT_POST, 'language', FILTER_SANITIZE_STRING) ?? 'en';
        $theme = filter_input(INPUT_POST, 'theme', FILTER_SANITIZE_STRING) ?? 'light';
        $notifications = isset($_POST['notifications']) ? 1 : 0;
        $timezone = filter_input(INPUT_POST, 'timezone', FILTER_SANITIZE_STRING) ?? 'UTC';

        // Ενημέρωση βάσης
        $stmt = $pdo->prepare("
            INSERT INTO user_preferences 
            (user_id, language, theme, notifications, timezone)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            language = VALUES(language),
            theme = VALUES(theme),
            notifications = VALUES(notifications),
            timezone = VALUES(timezone)
        ");
        
        $stmt->execute([$user_id, $language, $theme, $notifications, $timezone]);
        
        // Ανανέωση των preferences μετά την αποθήκευση
        $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $preferences = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $success_message = "Οι ρυθμίσεις εφαρμόστηκαν με επιτυχία!";
    }

} catch (PDOException $e) {
    $error = "Σφάλμα βάσης δεδομένων: " . $e->getMessage();
} catch (Exception $e) {
    $error = "Σφάλμα: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ρυθμίσεις Χρήστη</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .preferences-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background-color: #f8f9fa;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-section {
            margin-bottom: 1.5rem;
        }
        h2 {
            color: #333;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
   

    <div class="container">
        <div class="preferences-container">
            <h2>Ρυθμίσεις Χρήστη</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>

            <form method="POST">
                <!-- Γλώσσα -->
                <div class="form-section">
                    <h4>Ρυθμίσεις Γλώσσας</h4>
                    <div class="mb-3">
                        <label for="language" class="form-label">Προτιμητέα Γλώσσα</label>
                        <select class="form-select" id="language" name="language">
                            <option value="en" <?= ($preferences['language'] ?? 'en') === 'en' ? 'selected' : '' ?>>Αγγλικά</option>
                            <option value="el" <?= ($preferences['language'] ?? 'en') === 'el' ? 'selected' : '' ?>>Ελληνικά</option>
                        </select>
                    </div>
                </div>

                <!-- Θέμα -->
                <div class="form-section">
                    <h4>Εμφάνιση</h4>
                    <div class="mb-3">
                        <label class="form-label">Θέμα</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="theme" id="light-theme" value="light" 
                                <?= ($preferences['theme'] ?? 'light') === 'light' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="light-theme">Φωτεινό Θέμα</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="theme" id="dark-theme" value="dark" 
                                <?= ($preferences['theme'] ?? 'light') === 'dark' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="dark-theme">Σκοτεινό Θέμα</label>
                        </div>
                    </div>
                </div>

                <!-- Ειδοποιήσεις -->
                <div class="form-section">
                    <h4>Ειδοποιήσεις</h4>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="notifications" name="notifications" 
                            <?= isset($preferences['notifications']) && $preferences['notifications'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="notifications">Ενεργοποίηση Email Ειδοποιήσεων</label>
                    </div>
                </div>

                <!-- Ζώνη Ώρας -->
                <div class="form-section">
                    <h4>Ζώνη Ώρας</h4>
                    <div class="mb-3">
                        <label for="timezone" class="form-label">Επιλογή Ζώνης Ώρας</label>
                        <select class="form-select" id="timezone" name="timezone">
                            <?php foreach (timezone_identifiers_list() as $tz): ?>
                                <option value="<?= htmlspecialchars($tz) ?>" <?= ($preferences['timezone'] ?? 'UTC') === $tz ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tz) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Αποθήκευση Ρυθμίσεων</button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>