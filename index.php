<?php
session_start();

require 'vendor/autoload.php';
require 'src/chunk.php';
require 'src/embedding.php';
require 'src/retrieve.php';
require 'src/rerank.php';
require 'src/generate.php';

// Load .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$chunks = new Chunk();
$embedding = new Embedding();
$retrieve = new Retrieve();
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
        $_SESSION['chunks'] = $chunks;
    } catch (Exception $e) {
        $_SESSION['error'] = "Error saat memproses file: " . $e->getMessage();
    }
}

// Handle chat question
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["question"])) {
    $question = trim($_POST["question"]);
    $embededQuestion = $embedding->embedQuestions($question);
    $results = $retrieve->search($embededQuestion);
    $rankedResults = $rerank->rerank($question, $results);
    $answer = $generate->generateChat($question, $rankedResults)['answer'];

    $chatHistory[] = [
        'question' => $question,
        'answer' => $answer
    ];
}

// Tambahkan di bagian awal file setelah session_start()
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'chat';

// Tambahkan setelah handle chat question
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["summary"])) {
        $summary = $generate->generateSummary($_SESSION['chunks'])['answer'];
        $_SESSION['summary'] = $summary;
    }
    if (isset($_POST["exercise"])) {
        $exercise = $generate->generateExercises($_SESSION['chunks'])['answer'];
        $_SESSION['exercise'] = $exercise;
    }
    if (isset($_POST["note"])) {
        $note = $generate->generateNotes($_SESSION['chunks'])['answer'];
        $_SESSION['note'] = $note;
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Tutor AI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: #f5f6fa;
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        h1 {
            color: #2d3436;
            text-align: center;
            margin: 30px 0;
            font-size: 2.5em;
        }

        .upload-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .upload-container p {
            margin-bottom: 15px;
            color: #636e72;
        }

        input[type="file"] {
            display: block;
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 2px dashed #b2bec3;
            border-radius: 8px;
            cursor: pointer;
        }

        input[type="submit"] {
            background: #0984e3;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.3s ease;
        }

        input[type="submit"]:hover {
            background: #0769b5;
        }

        .chat-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .chat-container h2 {
            color: #2d3436;
            margin-bottom: 20px;
            font-size: 1.5em;
        }

        textarea {
            width: 100%;
            padding: 15px;
            border: 1px solid #dfe6e9;
            border-radius: 8px;
            resize: vertical;
            margin-bottom: 15px;
            font-size: 1em;
        }

        .chat-history {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-height: 500px;
            overflow-y: auto;
        }

        .chat-item {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }

        .user-question {
            background: #f1f2f6;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            color: #2d3436;
            font-weight: 500;
        }

        .assistant-answer {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 10px;
            color: #2d3436;
            line-height: 1.6;
        }

        .error {
            background: #ff7675;
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .success {
            background: #00b894;
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            h1 {
                font-size: 2em;
            }

            .upload-container,
            .chat-container,
            .chat-history {
                padding: 20px;
            }
        }

        .tabs {
            display: flex;
            margin-bottom: 20px;
            background: white;
            padding: 10px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 5px;
            margin-right: 10px;
            color: #636e72;
            transition: all 0.3s ease;
        }
        
        .tab.active {
            background: #0984e3;
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .summary-content,
        .exercise-content,
        .note-content {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .generate-btn {
            background: #00b894;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 15px;
        }

        .quiz-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .quiz-item {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .quiz-question {
            margin-bottom: 20px;
        }

        .question-number {
            display: inline-block;
            background: #0984e3;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .quiz-options {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .option {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .option:hover {
            border-color: #0984e3;
            background: #f8f9fa;
        }

        .option input[type="radio"] {
            margin-right: 12px;
        }

        .option label {
            cursor: pointer;
            flex: 1;
        }

        .answer-explanation {
            margin-top: 20px;
            padding: 15px;
            border-radius: 8px;
            background: #e3f2fd;
        }

        .answer-explanation.hidden {
            display: none;
        }

        .correct-answer {
            margin-bottom: 10px;
            color: #2d3436;
        }

        .explanation {
            color: #636e72;
            line-height: 1.6;
        }

        .quiz-controls {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }

        .check-answers-btn, .reset-quiz-btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .check-answers-btn {
            background: #0984e3;
            color: white;
        }

        .reset-quiz-btn {
            background: #e9ecef;
            color: #2d3436;
        }

        .check-answers-btn:hover {
            background: #0769b5;
        }

        .reset-quiz-btn:hover {
            background: #dee2e6;
        }

        .option.correct {
            border-color: #00b894;
            background: #e6fff9;
        }

        .option.wrong {
            border-color: #ff7675;
            background: #ffe9e9;
        }
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

    <div class="upload-container">
        <form method="post" enctype="multipart/form-data">
            <p>Upload file PDF untuk diproses:</p>
            <input type="file" name="pdfFile" accept=".pdf" required>
            <input type="submit" value="Upload & Proses" name="submit">
        </form>
    </div>

    <div class="tabs">
        <div class="tab <?php echo $activeTab === 'chat' ? 'active' : ''; ?>" 
             onclick="location.href='?tab=chat'">Chat</div>
        <div class="tab <?php echo $activeTab === 'summary' ? 'active' : ''; ?>" 
             onclick="location.href='?tab=summary'">Ringkasan</div>
        <div class="tab <?php echo $activeTab === 'exercise' ? 'active' : ''; ?>" 
             onclick="location.href='?tab=exercise'">Latihan</div>
        <div class="tab <?php echo $activeTab === 'note' ? 'active' : ''; ?>" 
             onclick="location.href='?tab=note'">Catatan</div>
    </div>

    <!-- Chat Tab -->
    <div class="tab-content <?php echo $activeTab === 'chat' ? 'active' : ''; ?>">
        <div class="chat-container">
            <h2>Tanya tentang Dokumen</h2>
            <form method="post" action="">
                <textarea name="question" rows="3" placeholder="Tulis pertanyaan Anda tentang dokumen di sini..." required></textarea>
                <input type="submit" value="Kirim Pertanyaan">
            </form>
        </div>
        <div class="chat-history">
            <h2>Riwayat Chat</h2>
            <?php if (!empty($chatHistory)): ?>
                <?php foreach ($chatHistory as $chat): ?>
                    <div class="chat-item">
                        <div class="user-question"><?php echo htmlspecialchars($chat['question']); ?></div>
                        <div class="assistant-answer"><?php echo nl2br(htmlspecialchars($chat['answer'])); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: #636e72; text-align: center;">Belum ada riwayat chat</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Summary Tab -->
    <div class="tab-content <?php echo $activeTab === 'summary' ? 'active' : ''; ?>">
        <div class="summary-content">
            <?php echo nl2br(htmlspecialchars($_SESSION['summary'])); ?>
        </div>
    </div>

    <!-- Exercise Tab -->
    <div class="tab-content <?php echo $activeTab === 'exercise' ? 'active' : ''; ?>">
        <div class="exercise-content">
            <?php if (!isset($_SESSION['exercise'])): ?>
                <form method="post">
                    <button type="submit" name="exercise" class="generate-btn">Generate Latihan Soal</button>
                </form>
            <?php else: ?>
                <div class="quiz-container">
                    <?php
                    $exercises = $_SESSION['exercise']['soal'];
                    foreach ($exercises as $exercise): ?>
                        <div class="quiz-item">
                            <div class="quiz-question">
                                <span class="question-number">Soal <?php echo $exercise['nomor']; ?></span>
                                <p><?php echo htmlspecialchars($exercise['pertanyaan']); ?></p>
                            </div>
                            
                            <div class="quiz-options">
                                <?php foreach ($exercise['pilihan'] as $index => $pilihan): ?>
                                    <div class="option">
                                        <input type="radio" 
                                                name="question<?php echo $exercise['nomor']; ?>" 
                                                id="q<?php echo $exercise['nomor'].'_'.$index; ?>"
                                                value="<?php echo $index; ?>">
                                        <label for="q<?php echo $exercise['nomor'].'_'.$index; ?>">
                                            <?php echo $index . '. ' . htmlspecialchars($pilihan); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="answer-explanation hidden">
                                <div class="correct-answer">
                                    <strong>Jawaban Benar:</strong> 
                                    <?php echo htmlspecialchars($exercise['jawaban_benar']); ?>
                                </div>
                                <div class="explanation">
                                    <strong>Penjelasan:</strong>
                                    <?php echo htmlspecialchars($exercise['penjelasan']); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="quiz-controls">
                        <button class="check-answers-btn">Check</button>
                        <button class="reset-quiz-btn">Reset</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Note Tab -->
    <div class="tab-content <?php echo $activeTab === 'note' ? 'active' : ''; ?>">
        <div class="note-content">
            <form method="post">
                <button type="submit" name="note" class="generate-btn">Generate Catatan</button>
            </form>
            <?php if (isset($note)): ?>
                <div class="generated-content">
                    <?php echo nl2br(htmlspecialchars($note)); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const checkAnswersBtn = document.querySelector('.check-answers-btn');
        const resetQuizBtn = document.querySelector('.reset-quiz-btn');
        const explanations = document.querySelectorAll('.answer-explanation');
        
        if(checkAnswersBtn) {
            checkAnswersBtn.addEventListener('click', function() {
                // Tampilkan semua penjelasan
                explanations.forEach(exp => exp.classList.remove('hidden'));
                
                // Logika pengecekan jawaban bisa ditambahkan di sini
                // Contoh: menambahkan class correct/wrong ke option yang dipilih
            });
        }
        
        if(resetQuizBtn) {
            resetQuizBtn.addEventListener('click', function() {
                // Reset semua pilihan
                document.querySelectorAll('input[type="radio"]').forEach(radio => {
                    radio.checked = false;
                });
                
                // Sembunyikan penjelasan
                explanations.forEach(exp => exp.classList.add('hidden'));
                
                // Hapus class correct/wrong
                document.querySelectorAll('.option').forEach(opt => {
                    opt.classList.remove('correct', 'wrong');
                });
            });
        }
    });
    </script>
</body>
</html>