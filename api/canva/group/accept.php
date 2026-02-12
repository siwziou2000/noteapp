<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

// 1. Έλεγχος αν ο χρήστης είναι συνδεδεμένος
if (!isset($_SESSION['user_id'])) {
    // Αν δεν είναι, τον στέλνουμε στο login και κρατάμε το token για μετά
    $_SESSION['redirect_token'] = $_GET['token'] ?? null;
    header("Location: ../../../../login.php?msg=Παρακαλώ συνδεθείτε για να αποδεχτείτε την πρόσκληση");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if (!$token) {
    die("Μη έγκυρο link πρόσκλησης.");
}

try {
    // 2. Αναζήτηση της πρόσκλησης στη βάση
    $stmt = $pdo->prepare("SELECT * FROM group_invitations WHERE token = ? AND status = 'pending'");
    $stmt->execute([$token]);
    $invitation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invitation) {
        $_SESSION['error'] = "Η πρόσκληση έχει λήξει ή είναι άκυρη.";
        header("Location: ../../../index.php");
        exit();
    }

    // 3. Έλεγχος αν η πρόσκληση αφορά τον συγκεκριμένο χρήστη (μέσω email ή user_id)
    // Αν η πρόσκληση έγινε σε υπάρχοντα χρήστη, ελέγχουμε το invited_id
    if ($invitation['invited_id'] && $invitation['invited_id'] != $current_user_id) {
        $_SESSION['error'] = "Αυτή η πρόσκληση δεν προορίζεται για αυτόν τον λογαριασμό.";
        header("Location: ../../../index.php");
        exit();
    }

    $pdo->beginTransaction();

    // 4. Προσθήκη του χρήστη στην ομάδα
    $insert_member = $pdo->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'viewer')");
    $insert_member->execute([$invitation['group_id'], $current_user_id]);

    // 5. Ενημέρωση της πρόσκλησης σε 'accepted'
    $update_invite = $pdo->prepare("UPDATE group_invitations SET status = 'accepted' WHERE token = ?");
    $update_invite->execute([$token]);

    $pdo->commit();

    $_SESSION['success'] = "Καλώς ήρθατε στην ομάδα!";
    header("Location: ../group.php?id=" . $invitation['group_id']);
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error'] = "Σφάλμα κατά την αποδοχή: " . $e->getMessage();
    header("Location: ../../../index.php");
    exit();
}