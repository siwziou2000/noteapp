<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "noteapp";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_POST['search'])) {
    $search = $conn->real_escape_string($_POST['search']);
    $sql = $search ?
        "SELECT user_id, username, fullname, email, role, isactive, avatar, email_verified FROM users WHERE username LIKE '%$search%' OR fullname LIKE '%$search%' OR email LIKE '%$search%'" :
        "SELECT user_id, username, fullname, email, role, isactive, avatar, email_verified FROM users";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                <td>".htmlspecialchars($row['user_id'])."</td>
                <td><img src='".(!empty($row['avatar']) ? htmlspecialchars($row['avatar']) : 'uploads/default-avatar.png')."' alt='Avatar' width='50' height='50' class='rounded-circle'></td>
                <td>".htmlspecialchars($row['username'])."</td>
                <td>".htmlspecialchars($row['fullname'])."</td>
                <td>".htmlspecialchars($row['email'])."</td>
                <td>".htmlspecialchars($row['role'])."</td>
                <td>".($row['email_verified'] == 1 ? '<span class=\"text-success\">Επαληθευμένο</span>' : '<span class=\"text-danger\">Μη Επαληθευμένο</span>')."</td>
                <td><a href=\"view_canvases_users.php?id=".$row['user_id']."\" class=\"btn btn-info btn-sm\">Προβολή Πίνακα</a></td>
                <td>
                    <div class=\"dropdown\">
                        <button class=\"btn btn-secondary btn-sm dropdown-toggle\" type=\"button\" id=\"dropdownActions\" data-toggle=\"dropdown\" aria-haspopup=\"true\" aria-expanded=\"false\">
                            Ενέργειες
                        </button>
                        <div class=\"dropdown-menu\" aria-labelledby=\"dropdownActions\">
                            <a class=\"dropdown-item\" href=\"profil.php?id=".$row['user_id']."\"><i class=\"bi bi-eye\"></i>Προβολή Προφίλ</a>
                            <a class=\"dropdown-item\" href=\"edit_user.php?id=".$row['user_id']."\"><i class=\"bi bi-pencil-square\"></i>Επεξεργασία</a>
                            <a class=\"dropdown-item\" href=\"change_password.php?id=".$row['user_id']."\"><i class=\"bi bi-eye-fill\"></i>Αλλαγή Κωδικού</a>
                            <a class=\"dropdown-item text-danger\" href=\"delete_user.php?id=".$row['user_id']."\" onclick=\"return confirm('Είστε σίγουροι ότι θέλετε να διαγράψετε αυτόν τον χρήστη?')\"><i class=\"bi bi-trash\"></i>Διαγραφή</a>
                        </div>
                    </div>
                </td>
                <td>
                    <button class=\"btn btn-".($row['isactive'] == 1 ? 'warning' : 'success')." btn-sm toggle-status\" data-id=\"".$row['user_id']."\" data-status=\"".$row['isactive']."\">
                        ".($row['isactive'] == 1 ? 'Απενεργοποίηση' : 'Ενεργοποίηση')."
                    </button>
                </td>
                <td>
                    <a href=\"users_history.php?id=".$row['user_id']."\" class=\"btn btn-secondary btn-sm\"><i class=\"bi bi-clock-history\"></i>Προβολή Ιστορικού</a>
                </td>
            </tr>";
        }
    } else {
        echo "<tr><td colspan='11'>No results found.</td></tr>";
    }
}

$conn->close();
?>
