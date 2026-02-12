<?php
// includes/alerts.php
if (isset($_SESSION['success'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
            ' . $_SESSION['success'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            ' . $_SESSION['error'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    unset($_SESSION['error']);
}

if (isset($_SESSION['info'])) {
    echo '<div class="alert alert-info alert-dismissible fade show" role="alert">
            ' . $_SESSION['info'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
    unset($_SESSION['info']);
}
?>