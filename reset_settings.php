[file name]: reset_settings.php
[file content begin]
<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

include 'includes/database.php';

try {
    // Διαγραφή όλων των υπαρχουσών ρυθμίσεων
    $sql = "DELETE FROM system_settings";
    $pdo->exec($sql);
    
    // Προεπιλεγμένες ρυθμίσεις
    $default_settings = [
        'site_name' => 'NoteApp',
        'site_email' => 'admin@noteapp.gr',
        'allow_registration' => '1',
        'default_user_role' => 'student',
        'email_verification' => '1',
        'max_login_attempts' => '5',
        'allow_multiple_devices' => '1',
        'max_file_size' => '10',
        'allowed_file_types' => 'pdf,doc,docx,txt,jpg,png',
        'maintenance_mode' => '0',
        'enable_notifications' => '1'
    ];
    
    // Εισαγωγή προεπιλεγμένων
    foreach ($default_settings as $key => $value) {
        $sql = "INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$key, $value]);
    }
    
    $_SESSION['reset_success'] = true;
    header("Location: system_settings.php");
    exit;
    
} catch (PDOException $e) {
    die("Σφάλμα: " . $e->getMessage());
}
?>