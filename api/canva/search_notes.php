<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/noteapp/includes/database.php';

$query = $_POST['query'] ?? '';
$canva_id = $_POST['canva_id'] ?? 0;

if (!empty($query)) {
    // 1. Query για λήψη όλων των απαραίτητων στοιχείων
    $stmt = $pdo->prepare("SELECT note_id, content, tag, icon, color, due_date FROM notes WHERE canva_id = ? AND content LIKE ? LIMIT 15");
    $stmt->execute([$canva_id, "%$query%"]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($results) {
        foreach ($results as $row) {
            // 2. Mapping Εικονιδίων: Μετατροπή της λέξης της βάσης σε Emoji
            $iconsMap = [
                'star'  => '⭐',
                'heart' => '❤️',
                'bell'  => '🔔',
                'pin'   => '📌',
                'tag'   => '🏷️'
            ];
            
            // Αν η βάση έχει κάτι άλλο ή είναι κενή, βάζουμε 📌
            $displayIcon = isset($iconsMap[$row['icon']]) ? $iconsMap[$row['icon']] : '📌';

            // 3. Καθαρισμός περιεχομένου (Αφαίρεση HTML tags)
            $plainText = strip_tags($row['content']);
            $cleanContent = mb_substr($plainText, 0, 45);
            if (mb_strlen($plainText) > 45) $cleanContent .= "...";
            
            // 4. Χρώμα και Ημερομηνία
            $noteColor = $row['color'] ?: '#ffffff';
            $today = date('Y-m-d');
            
            echo "<div class='p-2 border-bottom search-item d-flex align-items-center hover-effect' 
                       style='cursor:pointer; border-left: 5px solid {$noteColor} !important; background: white;' 
                       onclick='focusNote({$row['note_id']})'>";
                
                // Εμφάνιση του Δυναμικού Εικονιδίου
                echo "<span class='me-2 fs-4' style='min-width: 30px; text-align: center;'>{$displayIcon}</span>";
                
                echo "<div class='flex-grow-1 overflow-hidden'>";
                    echo "<div class='fw-bold text-dark text-truncate' style='font-size: 0.9rem;'>{$cleanContent}</div>";
                    
                    if (!empty($row['tag'])) {
                        echo "<span class='badge bg-light text-secondary border' style='font-size: 0.7rem;'>" . htmlspecialchars($row['tag']) . "</span>";
                    }
                echo "</div>";

                // 5. Εμφάνιση Ημερομηνίας Λήξης με χρώμα (Κόκκινο αν έληξε)
                if ($row['due_date']) {
                    $isOverdue = $row['due_date'] < $today;
                    $dateFormatted = date('d/m', strtotime($row['due_date']));
                    $dateColor = $isOverdue ? 'text-danger fw-bold' : 'text-muted';
                    
                    echo "<div class='ms-2 text-end {$dateColor}' style='font-size: 0.75rem; min-width: 55px;'>";
                    echo "<div><i class='bi bi-calendar3'></i></div>";
                    echo "<div>{$dateFormatted}</div>";
                    echo "</div>";
                }
            echo "</div>";
        }
    } else {
        // Μήνυμα αν δεν βρεθεί τίποτα
        echo "<div class='p-4 text-center text-muted'>";
        echo "<i class='bi bi-emoji-frown fs-2 d-block mb-2'></i>";
        echo "Δεν βρέθηκαν σημειώσεις για την αναζήτηση σας.";
        echo "</div>";
    }
} else {
    echo "<div class='p-3 text-center text-muted small'>Πληκτρολογήστε κάτι για αναζήτηση...</div>";
}
?>