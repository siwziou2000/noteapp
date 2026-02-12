
<?php
session_start();

// Ελέγχουμε αν ο χρήστης είναι συνδεδεμένος και έχει ρόλο admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// Λήψη του τρέχοντος θέματος από τη session
$current_theme = isset($_SESSION['theme']) ? $_SESSION['theme'] : 'light';

// Επεξεργασία της υποβολής της φόρμας
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['theme'])) {
    // Ενημέρωση του θέματος στην session
    $_SESSION['theme'] = $_POST['theme'];
    
    // Εδώ μπορείτε να αποθηκεύσετε το θέμα στη βάση δεδομένων αν χρειάζεται
    // π.χ. για να παραμένει η επιλογή σε μελλοντικές συνδέσεις
    // saveThemeToDatabase($_SESSION['user_id'], $_POST['theme']);
    
    // Επιστροφή στον πίνακα διαχείρισης με μήνυμα επιτυχίας
    $_SESSION['theme_update_success'] = "Το θέμα ενημερώθηκε επιτυχώς!";
    header("Location: admin_dashboard.php");
    exit;
}

// Παίρνουμε το όνομα του χρήστη από τη session
$username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : htmlspecialchars($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Αλλαγή Θέματος</title>
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS for theme preview -->
    <style>
        body {
            background-color: <?= $current_theme === 'dark' ? '#343a40' : '#f8f9fa' ?>;
            color: <?= $current_theme === 'dark' ? '#ffffff' : '#212529' ?>;
            transition: background-color 0.3s, color 0.3s;
        }
        
        .theme-option {
            border: 2px solid transparent;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .theme-option:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .theme-option.selected {
            border-color: #007bff;
        }
        
        .theme-preview {
            width: 100%;
            height: 150px;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .light-theme-preview {
            background-color: #ffffff;
            color: #212529;
            border: 1px solid #dee2e6;
        }
        
        .dark-theme-preview {
            background-color: #343a40;
            color: #ffffff;
            border: 1px solid #495057;
        }
        
        .card {
            background-color: <?= $current_theme === 'dark' ? '#495057' : '#ffffff' ?>;
            border-color: <?= $current_theme === 'dark' ? '#6c757d' : '#dee2e6' ?>;
        }
        
        .btn-primary {
            background-color: <?= $current_theme === 'dark' ? '#0069d9' : '#007bff' ?>;
            border-color: <?= $current_theme === 'dark' ? '#005cbf' : '#007bff' ?>;
        }
    </style>
</head>
<body>

<div class="container mt-5">
    <div class="card">
        <div class="card-header">
            <h1 class="mb-0">Αλλαγή Θέματος Εμφάνισης</h1>
        </div>
        <div class="card-body">
            <p class="lead">Καλωσήρθατε, <?= $username; ?>. Επιλέξτε ένα θέμα για το περιβάλλον διαχείρισης.</p>
            <p>Τρέχον θέμα: <strong><?= $current_theme === 'dark' ? 'Σκούρο' : 'Φωτεινό' ?></strong></p>
            
            <form method="POST" action="">
                <div class="row">
                    <!-- Φωτεινό Θέμα -->
                    <div class="col-md-6">
                        <div class="theme-option <?= $current_theme === 'light' ? 'selected' : '' ?>" onclick="selectTheme('light')">
                            <div class="theme-preview light-theme-preview d-flex flex-column justify-content-center align-items-center">
                                <h4>Φωτεινό Θέμα</h4>
                                <div class="mt-3">
                                    <span class="badge badge-light text-dark mr-2">Καθαρό</span>
                                    <span class="badge badge-light text-dark">Επαγγελματικό</span>
                                </div>
                            </div>
                            <div class="form-check text-center">
                                <input class="form-check-input" type="radio" name="theme" id="themeLight" value="light" <?= $current_theme === 'light' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="themeLight">
                                    <strong>Φωτεινό Θέμα</strong><br>
                                    <small>Κλασική εμφάνιση με φωτεινό φόντο</small>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Σκούρο Θέμα -->
                    <div class="col-md-6">
                        <div class="theme-option <?= $current_theme === 'dark' ? 'selected' : '' ?>" onclick="selectTheme('dark')">
                            <div class="theme-preview dark-theme-preview d-flex flex-column justify-content-center align-items-center">
                                <h4>Σκούρο Θέμα</h4>
                                <div class="mt-3">
                                    <span class="badge badge-dark mr-2">Άνετο</span>
                                    <span class="badge badge-dark">Σύγχρονο</span>
                                </div>
                            </div>
                            <div class="form-check text-center">
                                <input class="form-check-input" type="radio" name="theme" id="themeDark" value="dark" <?= $current_theme === 'dark' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="themeDark">
                                    <strong>Σκούρο Θέμα</strong><br>
                                    <small>Σύγχρονη εμφάνιση με σκούρο φόντο</small>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 text-center">
                    <button type="submit" class="btn btn-primary btn-lg mr-3">
                        <i class="fas fa-palette"></i> Εφαρμογή Θέματος
                    </button>
                    <a href="admin_dashboard.php" class="btn btn-secondary btn-lg">
                        <i class="fas fa-arrow-left"></i> Πίσω στον Πίνακα Διαχείρισης
                    </a>
                </div>
            </form>
            
            <div class="mt-5">
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle"></i> Πληροφορίες:</h5>
                    <ul class="mb-2">
                        <li>Η αλλαγή θέματος θα εφαρμοστεί άμεσα σε όλες τις σελίδες του πίνακα διαχείρισης.</li>
                      
                        
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript για διαδραστική επιλογή θέματος -->
<script>
    function selectTheme(theme) {
        // Αποεπιλογή όλων των radio buttons
        document.querySelectorAll('input[name="theme"]').forEach(radio => {
            radio.checked = false;
        });
        
        // Επιλογή του σωστού radio button
        document.getElementById('theme' + theme.charAt(0).toUpperCase() + theme.slice(1)).checked = true;
        
        // Αφαίρεση της κλάσης selected από όλα τα theme-option
        document.querySelectorAll('.theme-option').forEach(option => {
            option.classList.remove('selected');
        });
        
        // Προσθήκη της κλάσης selected στο επιλεγμένο theme-option
        event.currentTarget.classList.add('selected');
        
        // Προεπισκόπηση αλλαγής θέματος (προσθήκη κλάσης στο body προσωρινά)
        document.body.classList.remove('theme-preview-light', 'theme-preview-dark');
        document.body.classList.add('theme-preview-' + theme);
    }
    
    // Προετοιμασία για προεπισκόπηση
    document.addEventListener('DOMContentLoaded', function() {
        // Προσθήκη προσωρινών κλάσεων για προεπισκόπηση
        const style = document.createElement('style');
        style.innerHTML = `
            .theme-preview-light {
                background-color: #f8f9fa !important;
                color: #212529 !important;
            }
            .theme-preview-dark {
                background-color: #343a40 !important;
                color: #ffffff !important;
            }
        `;
        document.head.appendChild(style);
    });
</script>

<!-- Font Awesome για εικονίδια -->
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>

<!-- Bootstrap JS and dependencies -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
