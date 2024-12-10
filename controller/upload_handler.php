<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/chunk.php';
require_once __DIR__ . '/../src/embedding.php';

header('Content-Type: application/json');

try {
    // Load .env
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
    
    if (isset($_FILES['pdf_file'])) {
        $uploadedFile = $_FILES['pdf_file']['tmp_name'];
        
        $chunk = new Chunk();
        $_SESSION['chunks'] = $chunk->processPDF($uploadedFile);

        $embedding = new Embedding();
        $_SESSION['embeddings_chunks'] = $embedding->embedChunks($_SESSION['chunks']);
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Tidak ada file yang diupload']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 