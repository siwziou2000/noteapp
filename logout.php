<?php
session_start();
session_unset();  // Διαγραφή όλων των μεταβλητών συνεδρίας
session_destroy();  // Καταστροφή της συνεδρίας

header("Location: index.php");  // Ανακατεύθυνση στην φόρμα σύνδεσης
exit();
?>
