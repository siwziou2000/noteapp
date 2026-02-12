<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';




if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Δημιουργία νέας ομάδας
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_group'])) {
    try {
        $group_name = filter_input(INPUT_POST, 'group_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        $stmt = $pdo->prepare("INSERT INTO groups 
            (user_id, group_name, description, created_at, updated_at) 
            VALUES (?, ?, ?, NOW(), NOW())");
        $stmt->execute([$user_id, $group_name, $description]);

        $_SESSION['success'] = "
         Ομάδα δημιουργήθηκε επιτυχώς!";
        header("Location: groups.php");
        exit();
    } catch (PDOException $e) {
        die("🚨 Σφάλμα βάσης: " . $e->getMessage());
    }
}

// Προσθήκη μέλους στην ομάδα
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_member'])) {
    try {
        $group_id = filter_input(INPUT_POST, 'group_id', FILTER_VALIDATE_INT);
        $member_user_id = filter_input(INPUT_POST, 'member_user_id', FILTER_VALIDATE_INT);

        if ($group_id && $member_user_id) {
            // Προσθήκη μέλους στην ομάδα
            $stmt = $pdo->prepare("INSERT INTO group_members (group_id, user_id) VALUES (?, ?)");
            $stmt->execute([$group_id, $member_user_id]);

            $_SESSION['success'] = "✅ Μέλος προστέθηκε στην ομάδα!";
            header("Location: groups.php");
            exit();
        }
    } catch (PDOException $e) {
        die("🚨 Σφάλμα προσθήκης μέλους: " . $e->getMessage());
    }
}

// Ανάκτηση ομάδων
$page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM groups WHERE user_id = ?");
    $count_stmt->execute([$user_id]);
    $total_groups = $count_stmt->fetchColumn();

    $groups_stmt = $pdo->prepare("SELECT * FROM groups 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?");
    $groups_stmt->bindValue(1, $user_id, PDO::PARAM_INT);
    $groups_stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $groups_stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $groups_stmt->execute();
    $groups = $groups_stmt->fetchAll(PDO::FETCH_ASSOC);
} 
catch (PDOException $e) {
    die("🚨 Σφάλμα ανάκτησης: " . $e->getMessage());
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/api/canva/include/menu.php';
?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Διαχείριση Ομάδων</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
     <link href="groups.css" rel="stylesheet">
    </head>
    

  
</head>
<body>

    <div class="container py-4">
    <h1 class="mb-4 text-dark fw-light">Διαχείριση Ομάδων</h1>

    <div class="mb-4">
        <a href="groups.php" class="btn btn-outline-secondary btn-sm rounded-pill">
            <i class="bi bi-list-task me-1"></i> Εμφάνιση ομάδων
        </a>
    </div>

    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert" style="border-left: 4px solid #198754 !important;">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="card shadow-sm border-0 mb-5 overflow-hidden">
        <div class="card-header bg-primary text-white py-3">
            <h5 class="mb-0 fw-bold">
                <i class="bi bi-plus-square-fill me-2"></i>Δημιουργία Νέας Ομάδας
            </h5>
        </div>
        <div class="card-body p-4 bg-white">
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-semibold text-secondary">Όνομα Ομάδας</label>
                    <div class="input-group has-validation">
                        <span class="input-group-text bg-light text-primary border-end-0">
                            <i class="bi bi-people-fill"></i>
                        </span>
                        <input type="text" name="group_name" class="form-control border-start-0 ps-0"required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold text-secondary">Περιγραφή</label>
                    <textarea name="description" class="form-control" ></textarea>
                </div>
                
                <div class="d-grid d-md-flex justify-content-md-end">
                    <button type="submit" name="create_group" class="btn btn-primary px-5 py-2 fw-bold shadow-sm">
                        <i class="bi bi-check-lg me-1"></i> Δημιουργία
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

            
            
            
            
            

        
     


  <div class="container py-4">
    <h2> Λίστα Ομάδων</h2>
    <?php foreach ($groups as $group): ?>
        <div class="card mb-3">
            <div class="card-body">
                <h3 class="card-title"><?= htmlspecialchars($group['group_name']) ?></h3>
                <?php if(!empty($group['description'])): ?>
                    <p class="card-text"><?= nl2br(htmlspecialchars($group['description'])) ?></p>
                <?php endif; ?>
                
                <!-- Members Section -->
                <div class="mt-3">
                    <h5>Μέλη Ομάδας:</h5>
                    <ul class="list-group">
                        <?php
                        // Fetch members for this group
                        try {
                            $members_stmt = $pdo->prepare("
                                SELECT u.username, gm.role 
                                FROM group_members gm
                                JOIN users u ON gm.user_id = u.user_id
                                WHERE gm.group_id = ?
                            ");
                            $members_stmt->execute([$group['group_id']]);
                            $members = $members_stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (count($members) > 0) {
                                foreach ($members as $member) {
                                    $badge_color = $member['role'] === 'admin' ? 'bg-primary' : 'bg-secondary';
                                    echo "
                                        <li class='list-group-item d-flex justify-content-between align-items-center'>
                                            {$member['username']}
                                            <span class='badge $badge_color'>{$member['role']}</span>
                                        </li>
                                    ";
                                }
                            } else {
                                echo "<li class='list-group-item'>Δεν υπάρχουν μέλη ακόμα</li>";
                            }
                        } catch (PDOException $e) {
                            echo "<li class='list-group-item text-danger'>Σφάλμα φόρτωσης μελών: " . $e->getMessage() . "</li>";
                        }
                        ?>
                    </ul>
                </div>
                <!-- End Members Section -->

                <!-- Tasks Section -->
                <div class="mt-3">
                    <h5>Εργασίες Ομάδας:</h5>
                    <ul class="list-group">
                        <?php
                        // Fetch tasks for this group
                        try {
                            $tasks_stmt = $pdo->prepare("
                                SELECT t.id, t.title, t.due_date, t.priority, t.created_at, u.username 
                                FROM tasks t
                                JOIN users u ON t.user_id = u.user_id
                                JOIN group_tasks gt ON t.id = gt.task_id
                                WHERE gt.group_id = ?
                                ORDER BY 
                                    CASE t.priority 
                                        WHEN 'High' THEN 1 
                                        WHEN 'Medium' THEN 2 
                                        WHEN 'Low' THEN 3 
                                        ELSE 4 
                                    END,
                                    t.due_date ASC
                            ");
                            $tasks_stmt->execute([$group['group_id']]);
                            $tasks = $tasks_stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (count($tasks) > 0) {
                                foreach ($tasks as $task) {
                                    $priority_class = 'priority-' . strtolower($task['priority']);
                                    $due_date = $task['due_date'] ? date('d/m/Y', strtotime($task['due_date'])) : 'Χωρίς ημερομηνία';
                                    
                                    echo "
                                        <li class='list-group-item d-flex justify-content-between align-items-center'>
                                            <div>
                                                <strong>{$task['title']}</strong>
                                                <div class='small text-muted'>
                                                    <span class='$priority_class'>{$task['priority']}</span> | 
                                                    Προθεσμία: {$due_date} | 
                                                    Δημιουργήθηκε από: {$task['username']}
                                                </div>
                                            </div>
                                            <div>
                                                <a href='edit_tasks.php?id={$task['id']}' class='btn btn-sm btn-outline-secondary'>✏️</a>
                                                <a href='delete_tasks.php?id={$task['id']}' class='btn btn-sm btn-outline-danger'>🗑️</a>
                                            </div>
                                        </li>
                                    ";
                                }
                            } else {
                                echo "<li class='list-group-item'>Δεν υπάρχουν εργασίες ακόμα</li>";
                            }
                        } catch (PDOException $e) {
                            echo "<li class='list-group-item text-danger'>Σφάλμα φόρτωσης εργασιών: " . $e->getMessage() . "</li>";
                        }
                        ?>
                    </ul>
                    <div class="mt-2">
                        <a href="add_tasks.php?group_id=<?= $group['group_id'] ?>" class="btn btn-sm btn-primary">➕ Προσθήκη Εργασίας</a>
                    </div>
                </div>
                <!-- End Tasks Section -->

                <div class="text-muted small">
                    <div>Δημιουργήθηκε: <?= date('d/m/Y H:i', strtotime($group['created_at'])) ?></div>
                    <div>Τελευταία ενημέρωση: <?= date('d/m/Y H:i', strtotime($group['updated_at'])) ?></div>
                    <div>Κατόχος: <?= htmlspecialchars($_SESSION['username']) ?? 'Δεν βρέθηκε' ?></div>
                </div>
                <div class="mt-2">
                    <a href="group.php?id=<?= $group['group_id'] ?>" class="btn btn-sm btn-outline-secondary">✏️ Επεξεργασία</a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
        <div class="pagination">
            <?php
            $total_pages = ceil($total_groups / $limit);
            for ($i = 1; $i <= $total_pages; $i++):
                $active = $i == $page ? 'style="background:#007bff;color:white;"' : '';
            ?>
                <a href="?page=<?= $i ?>" <?= $active ?>><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>

   <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>
</body>
</html>