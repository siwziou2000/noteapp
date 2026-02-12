<?php
session_start();

$inactive = 1800; 
if (isset($_SESSION['timeout'])) {
    $session_life = time() - $_SESSION['timeout'];
    if ($session_life > $inactive) {
        session_unset();
        session_destroy();
        header("Location: /noteapp/login.php");
        exit();
    }
}
$_SESSION['timeout'] = time();

require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /noteapp/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Pagination parameters
$itemsPerPage = 5; // Items per page for all lists

// Current pages for each section
$currentPageCanvases = isset($_GET['page_canvases']) ? max(1, intval($_GET['page_canvases'])) : 1;
$currentPageGroups = isset($_GET['page_groups']) ? max(1, intval($_GET['page_groups'])) : 1;
$currentPageTasks = isset($_GET['page_tasks']) ? max(1, intval($_GET['page_tasks'])) : 1;

// Calculate offsets
$offsetCanvases = ($currentPageCanvases - 1) * $itemsPerPage;
$offsetGroups = ($currentPageGroups - 1) * $itemsPerPage;
$offsetTasks = ($currentPageTasks - 1) * $itemsPerPage;

// Fetch user details
try {
    $stmtUser = $pdo->prepare("SELECT username, fullname, role FROM users WHERE user_id = ?");
    $stmtUser->execute([$user_id]);
    $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);
    
    if ($userData) {
        $username = htmlspecialchars($userData['username'] ?? 'Χρήστη');
        $fullname = htmlspecialchars($userData['fullname'] ?? 'Χρήστη');
        $role = htmlspecialchars($userData['role'] ?? 'Χρήστης');
    }
    
} catch (PDOException $e) {
    die("Σφάλμα βάσης: " . $e->getMessage());
}

// Fetch canvases (both owned and collaborative) with pagination
try {
    // Count total canvases
    $stmtCount = $pdo->prepare("
        SELECT COUNT(DISTINCT c.canva_id)
        FROM canvases c
        LEFT JOIN canvas_collaborators cc ON c.canva_id = cc.canva_id
        WHERE c.owner_id = ? OR cc.user_id = ?
    ");
    $stmtCount->execute([$user_id, $user_id]);
    $totalCanvases = $stmtCount->fetchColumn();
    $totalPagesCanvases = ceil($totalCanvases / $itemsPerPage);
    
    // Fetch canvases for current page
    $stmtCanvases = $pdo->prepare("
        SELECT DISTINCT c.canva_id, c.name, c.canva_category, c.access_type, c.created_at, 
               u.fullname AS creator, c.owner_id
        FROM canvases c
        JOIN users u ON c.owner_id = u.user_id
        LEFT JOIN canvas_collaborators cc ON c.canva_id = cc.canva_id
        WHERE c.owner_id = ? OR cc.user_id = ?
        ORDER BY c.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmtCanvases->bindValue(1, $user_id, PDO::PARAM_INT);
    $stmtCanvases->bindValue(2, $user_id, PDO::PARAM_INT);
    $stmtCanvases->bindValue(3, $itemsPerPage, PDO::PARAM_INT);
    $stmtCanvases->bindValue(4, $offsetCanvases, PDO::PARAM_INT);
    $stmtCanvases->execute();
    $userCanvases = $stmtCanvases->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Σφάλμα ανάκτησης καμβά: " . $e->getMessage());
    $totalCanvases = 0;
    $userCanvases = [];
}

// Fetch groups with pagination
try {
    // Count total groups
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM groups WHERE user_id = ?");
    $stmtCount->execute([$user_id]);
    $totalGroups = $stmtCount->fetchColumn();
    $totalPagesGroups = ceil($totalGroups / $itemsPerPage);
    
    // Fetch groups for current page
    $stmtGroups = $pdo->prepare("
        SELECT group_id, group_name, description, created_at 
        FROM groups 
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmtGroups->bindValue(1, $user_id, PDO::PARAM_INT);
    $stmtGroups->bindValue(2, $itemsPerPage, PDO::PARAM_INT);
    $stmtGroups->bindValue(3, $offsetGroups, PDO::PARAM_INT);
    $stmtGroups->execute();
    $userGroups = $stmtGroups->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Σφάλμα ανάκτησης ομάδων: " . $e->getMessage());
    $totalGroups = 0;
    $userGroups = [];
}

// Fetch tasks with pagination
try {
    // Count total tasks
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ?");
    $stmtCount->execute([$user_id]);
    $totalTasks = $stmtCount->fetchColumn();
    $totalPagesTasks = ceil($totalTasks / $itemsPerPage);
    
    // Fetch tasks for current page
    $stmtTasks = $pdo->prepare("
        SELECT id, title, due_date, priority, created_at 
        FROM tasks 
        WHERE user_id = ?
        ORDER BY due_date ASC
        LIMIT ? OFFSET ?
    ");
    $stmtTasks->bindValue(1, $user_id, PDO::PARAM_INT);
    $stmtTasks->bindValue(2, $itemsPerPage, PDO::PARAM_INT);
    $stmtTasks->bindValue(3, $offsetTasks, PDO::PARAM_INT);
    $stmtTasks->execute();
    $userTasks = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Σφάλμα ανάκτησης εργασιών: " . $e->getMessage());
    $totalTasks = 0;
    $userTasks = [];
}

// Συνάρτηση για χρονικό διάστημα
function time_elapsed_string($datetime) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'w' => 'βδομάδα',
        'd' => 'μέρα',
        'h' => 'ώρα',
        'i' => 'λεπτό',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 'ες' : '');
        } else {
            unset($string[$k]);
        }
    }
    
    return $string ? 'πριν ' . implode(', ', $string) : 'μόλις τώρα';
}

include 'include/menu.php';

?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Page - Linoit Style</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="css/home.css">
</head>
<body style="padding-top: 50px;">
    <!-- Navbar -->
 

    <!-- Main Content -->
    <div class="main-container mt-30">
        <div class="row g-20">
            <!-- Main Section - Left Column (Canvases) -->
            <div class="col-lg-8">
                <h1 class="display-10 fw-semibold">Καλώς ήρθατε, <?= $fullname ?> (<?= $role ?>)</h1>

                <div class="container py-4">
                    <section class="canvas-section">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2>Οι καμβάδες μου / Οι πίνακες μου</h2>
                            <a href="canvas.php" class="btn btn-primary btn-sm">ΔΗΜΙΟΥΡΓΙΑ ΚΑΜΒΑ</a>
                        </div>
                        
                        <?php if (!empty($userCanvases)): ?>
                            <ul class="list-group">
                                <?php foreach ($userCanvases as $canvas): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?= htmlspecialchars($canvas['name']) ?></strong>
                                            <span class="badge bg-<?= $canvas['access_type'] === 'public' ? 'success' : 'warning' ?> ms-2">
                                                <?= $canvas['access_type'] === 'public' ? 'Δημόσιο' : 'Ιδιωτικό' ?>
                                            </span>
                                            <?php if ($canvas['owner_id'] != $user_id): ?>
                                                <span class="badge bg-info ms-2">Συνεργασία</span>
                                            <?php endif; ?>
                                            <br>
                                            <small class="text-muted">
                                                Κατηγορία: <?= htmlspecialchars($canvas['canva_category']) ?> |
                                                Δημιουργός: <?= htmlspecialchars($canvas['creator']) ?> |
                                                <?= time_elapsed_string($canvas['created_at']) ?>
                                            </small>
                                        </div>
                                        <a href="board.php?id=<?= $canvas['canva_id'] ?>" class="btn btn-outline-primary btn-sm">Άνοιγμα</a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="alert alert-info">Δεν έχετε ακόμα πίνακες ή συνεργασίες.</div>
                        <?php endif; ?>
                    </section>
                    
                    <!-- Pagination for Canvases -->
                    <?php if ($totalPagesCanvases > 1): ?>
                        <nav aria-label="Page navigation for canvases" class="mt-3">
                            <ul class="pagination justify-content-center">
                                <?php if ($currentPageCanvases > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page_canvases=<?= $currentPageCanvases - 1 ?>&page_groups=<?= $currentPageGroups ?>&page_tasks=<?= $currentPageTasks ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $totalPagesCanvases; $i++): ?>
                                    <li class="page-item <?= $i == $currentPageCanvases ? 'active' : '' ?>">
                                        <a class="page-link" href="?page_canvases=<?= $i ?>&page_groups=<?= $currentPageGroups ?>&page_tasks=<?= $currentPageTasks ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($currentPageCanvases < $totalPagesCanvases): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page_canvases=<?= $currentPageCanvases + 1 ?>&page_groups=<?= $currentPageGroups ?>&page_tasks=<?= $currentPageTasks ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column (Groups and Tasks) -->
            <div class="col-lg-4">
                <div class="sticky-top" style="top: 70px;">
                    <!-- Ομάδες -->
                    <div class="container py-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4>Ομάδες</h4>
                            <a href="group/groups.php" class="btn btn-outline-primary btn-sm">Όλες οι ομάδες</a>
                        </div>
                        <div class="group-list">
                            <?php if (!empty($userGroups)): ?>
                                <?php foreach ($userGroups as $group): ?>
                                    <div class="sidebar-item mb-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?= htmlspecialchars($group['group_name']) ?></strong>
                                                <p class="text-muted mb-0"><?= htmlspecialchars($group['description']) ?></p>
                                                <small>Δημιουργήθηκε <?= time_elapsed_string($group['created_at']) ?></small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="alert alert-info">Δεν βρέθηκαν ομάδες</div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Pagination for Groups -->
                        <?php if ($totalPagesGroups > 1): ?>
                            <nav aria-label="Page navigation for groups">
                                <ul class="pagination justify-content-center pagination-sm">
                                    <?php if ($currentPageGroups > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page_canvases=<?= $currentPageCanvases ?>&page_groups=<?= $currentPageGroups - 1 ?>&page_tasks=<?= $currentPageTasks ?>" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $totalPagesGroups; $i++): ?>
                                        <li class="page-item <?= $i == $currentPageGroups ? 'active' : '' ?>">
                                            <a class="page-link" href="?page_canvases=<?= $currentPageCanvases ?>&page_groups=<?= $i ?>&page_tasks=<?= $currentPageTasks ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($currentPageGroups < $totalPagesGroups): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page_canvases=<?= $currentPageCanvases ?>&page_groups=<?= $currentPageGroups + 1 ?>&page_tasks=<?= $currentPageTasks ?>" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>

                    <!-- Εργασίες -->
                    <div class="container py-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4>Εργασίες</h4>
                            <a href="group/tasks/tasks.php" class="btn btn-outline-primary btn-sm">Όλες οι εργασίες</a>
                        </div>
                        <div class="task-list">
                            <?php if (!empty($userTasks)): ?>
                                <?php foreach ($userTasks as $task): ?>
                                    <div class="sidebar-item mb-2 p-2 border-bottom">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <strong><?= htmlspecialchars($task['title']) ?></strong>
                                                <div class="text-muted">
                                                    Προθεσμία: <?= date('d/m/Y', strtotime($task['due_date'])) ?>
                                                    <span class="badge bg-<?= 
                                                        $task['priority'] === 'High' ? 'danger' : 
                                                        ($task['priority'] === 'Medium' ? 'warning' : 'success') 
                                                    ?>">
                                                        <?= $task['priority'] ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="alert alert-info">Δεν βρέθηκαν εργασίες</div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Pagination for Tasks -->
                        <?php if ($totalPagesTasks > 1): ?>
                            <nav aria-label="Page navigation for tasks">
                                <ul class="pagination justify-content-center pagination-sm">
                                    <?php if ($currentPageTasks > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page_canvases=<?= $currentPageCanvases ?>&page_groups=<?= $currentPageGroups ?>&page_tasks=<?= $currentPageTasks - 1 ?>" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $totalPagesTasks; $i++): ?>
                                        <li class="page-item <?= $i == $currentPageTasks ? 'active' : '' ?>">
                                            <a class="page-link" href="?page_canvases=<?= $currentPageCanvases ?>&page_groups=<?= $currentPageGroups ?>&page_tasks=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($currentPageTasks < $totalPagesTasks): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page_canvases=<?= $currentPageCanvases ?>&page_groups=<?= $currentPageGroups ?>&page_tasks=<?= $currentPageTasks + 1 ?>" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        $('#searchForm').on('submit', function (e) {
            e.preventDefault();
            let searchTerm = $('#searchInput').val();
            $.ajax({
                url: 'searchcanvases.php',
                method: 'POST',
                data: { query: searchTerm },
                success: function (response) {
                    $('#searchResults').html(response);
                },
                error: function () {
                    $('#searchResults').html('<div class="alert alert-danger">Σφάλμα κατά την αναζήτηση.</div>');
                }
            });
        });
    </script>

    <?php
    require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/footer.php';
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
