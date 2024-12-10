<?php
session_start();
use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

require 'vendor/autoload.php';
require 'chunk.php';
require 'embedding.php';
require 'retrieval.php';
require 'generate.php';

// Fungsi untuk menangani upload file
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['pdf_file'])) {
        $uploadedFile = $_FILES['pdf_file']['tmp_name'];
        
        $chunk = new Chunk();
        $_SESSION['chunks'] = $chunk->processPDF($uploadedFile);
        $_SESSION['embeddings_chunks'] = $embedding->embedChunks($_SESSION['chunks']);
    }

    if (isset($_POST['question'])) {
        $embedding = new Embedding();
        $question = $_POST['question'];
        $_SESSION['embeddings_questions'] = $embedding->embedQuestions($question);

        $retrieval = new Retrieval();
        $_SESSION['results'] = $retrieval->search();
        
        $generate = new Generate();
        $_SESSION['answer'] = $generate->chat($question, $_SESSION['results']);

    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>PDF Result</title>
    <style>
        .chunk {
            margin: 10px;
            padding: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .chat-response {
            margin: 20px 0;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 5px;
        }
        
        .chat-container {
            margin: 20px 0;
            max-width: 800px;
        }
        
        .chat-form {
            margin: 20px 0;
            display: flex;
            gap: 10px;
        }
        
        .chat-form input[type="text"] {
            flex: 1;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        
        .chat-history {
            margin-top: 20px;
        }
        
        .chat-message {
            padding: 10px;
            margin: 5px 0;
            background: #f0f0f0;
            border-radius: 4px;
        }
        
        .timestamp {
            font-size: 0.8em;
            color: #666;
        }
        
        .results {
            margin-top: 10px;
            padding: 10px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .results h4 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .result-item {
            padding: 8px;
            margin: 5px 0;
            background: #f8f8f8;
            border-left: 3px solid #007bff;
        }
        
        .result-item p {
            margin: 0 0 5px 0;
        }
        
        .result-item small {
            color: #666;
        }
        
        .answer {
            margin: 15px 0;
            padding: 15px;
            background: #e3f2fd;
            border-radius: 5px;
            border-left: 4px solid #2196f3;
        }
        
        .answer h4 {
            margin: 0 0 10px 0;
            color: #1976d2;
        }
        
        .answer p {
            margin: 0;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <h1>RAG</h1>
    
    <!-- Form upload PDF -->
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="pdf_file" accept=".pdf" required>
        <button type="submit">Process PDF</button>
    </form>

    <!-- Tampilkan error jika ada -->
    <?php if (isset($_SESSION['error'])): ?>
        <div style="color: red;">
            <?php 
            echo $_SESSION['error'];
            unset($_SESSION['error']);
            ?>
        </div>
    <?php endif; ?>

    <!-- Tambahkan form chat -->
    <div class="chat-container">
        <form method="POST" class="chat-form">
            <input type="text" name="question" placeholder="Ketik pertanyaan Anda di sini..." required>
            <button type="submit">Kirim</button>
        </form>
    </div>

</body>
</html>
