<?php
session_start();

require 'vendor/autoload.php';
require 'src/chunk.php';
require 'src/embedding.php';
require 'src/retrieval.php';
require 'src/rerank.php';
require 'src/generate.php';
// Load .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$chunks = new Chunk();
$embedding = new Embedding();
$retrieval = new Retrieval();
$rerank = new Rerank();
$generate = new Generate();

// Handle file upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["pdfFile"])) {
    $pdfFile = $_FILES["pdfFile"];
    
    try {
        // Proses chunking
        $chunks = $chunks->processPDF($pdfFile["tmp_name"]);

        // Proses embedding
        $embedding->embedChunks($chunks);
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error saat memproses file: " . $e->getMessage();
    }
}

// Handle chat question
// if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["question"])) {
//     $question = trim($_POST["question"]);
//     if (!empty($question)) {
//         try {
//             // Embed pertanyaan user
//             $questionEmbedding = $embedding->embedQuestions($question);
//             // Retrieve similar chunks
//             $similarChunks = $retrieval->search($questionEmbedding);
//             // Rerank similar chunks
//             $rerankedResults = $rerank->rerank($question, $similarChunks);

//             // Generate answer
//             $answer = $generate->generateChat($question, $rerankedResults);

//             $_SESSION['chat_history'][] = [
//                 'question' => $question,
//                 'results' => $rerankedResults,
//                 'answer' => $answer['answer']
//             ];


//         } catch (Exception $e) {
//             $_SESSION['error'] = "Error saat memproses pertanyaan: " . $e->getMessage();
//         }
//     } else {
//         $_SESSION['error'] = "Pertanyaan tidak boleh kosong!";
//     }
// }
?>

<!DOCTYPE html>
<html>
<head>
    <title>Tutor AI</title>
    <style>
        .error { color: red; }
        .success { color: green; }
        .chat-container {
            margin-top: 20px;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .chat-history {
            margin-top: 20px;
            max-height: 300px;
            overflow-y: auto;
        }
        .question {
            background: #f0f0f0;
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
        }
        .timestamp {
            font-size: 0.8em;
            color: #666;
        }
        .user-question {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .results {
            margin-top: 10px;
            padding: 10px;
            background: #fff;
            border-radius: 5px;
        }
        
        .result-item {
            margin: 10px 0;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .relevance-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 0.8em;
            margin-bottom: 5px;
        }
        
        .sangat-relevan { background: #4CAF50; color: white; }
        .relevan { background: #2196F3; color: white; }
        .cukup-relevan { background: #FF9800; color: white; }
        .kurang-relevan { background: #f44336; color: white; }
    </style>
</head>
<body>
    <h1>Tutor AI</h1>
    
    <?php
    if (isset($_SESSION['error'])) {
        echo "<p class='error'>" . $_SESSION['error'] . "</p>";
        unset($_SESSION['error']);
    }
    if (isset($_SESSION['success'])) {
        echo "<p class='success'>" . $_SESSION['success'] . "</p>";
        unset($_SESSION['success']);
    }
    ?>

    <form method="post" enctype="multipart/form-data">
        <p>Upload file PDF untuk diproses:</p>
        <input type="file" name="pdfFile" accept=".pdf" required>
        <input type="submit" value="Upload & Proses" name="submit">
    </form>

    <div class="chat-container">
        <h2>Tanya tentang Dokumen</h2>
        <form method="post" action="">
            <textarea name="question" rows="3" cols="50" placeholder="Tulis pertanyaan Anda tentang dokumen di sini..." required></textarea>
            <br>
            <input type="submit" value="Kirim Pertanyaan">
        </form>
    </div>
</body>
</html>