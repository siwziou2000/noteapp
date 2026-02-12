<?php

//connevt the database

include 'includes/database.php';


// query the databse users table
$sql = "SELECT user_id , username , email , role FROM users";
$stmt = $pdo ->prepare($sql);
$stmt ->execute();


//display users and users roles






?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Προβολή Χρηστών</title>
</head>
<body>

<h1>Κατάλογος Χρηστών και Δικαιωμάτων</h1>

<table border="1">
    <tr>
        <th>Όνομα Χρήστη</th>
        <th>Email</th>
        <th>Ρόλος</th>
    </tr>

    <?php
    // Λήψη όλων των χρηστών από τη βάση δεδομένων
    while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($user['username']) . "</td>";
        echo "<td>" . htmlspecialchars($user['email']) . "</td>";
        echo "<td>" . htmlspecialchars($user['role']) . "</td>";  // Εμφανίζουμε τον ρόλο του χρήστη
        echo "</tr>";
    }
    ?>

</table>

</body>
</html>