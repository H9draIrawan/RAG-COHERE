<?php
session_start();

if (isset($_SESSION['last_notes'])) {
    // Set header untuk download
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="catatan_pembelajaran.txt"');
    
    // Konversi ke format yang lebih baik untuk txt
    $notes = $_SESSION['last_notes'];
    
    // Output konten
    echo $notes;
    exit;
} else {
    echo "Tidak ada catatan yang tersedia untuk diunduh.";
} 