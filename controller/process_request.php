<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/generate.php';
require_once __DIR__ . '/../src/retrieval.php';

header('Content-Type: application/json');

try {
    // Load .env
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
    
    $generate = new Generate();
    $retrieval = new Retrieval();
    
    if (isset($_POST['action'])) {
        $_SESSION['results'] = $retrieval->search();
        
        $response = null;
        switch($_POST['action']) {
            case 'chat':
                if (isset($_POST['question'])) {
                    $response = $generate->chat($_POST['question'], $_SESSION['results']);
                }
                break;
            case 'summary':
                $response = $generate->generateSummary($_SESSION['results']);
                break;
            case 'exercises':
                $response = $generate->generateExercises($_SESSION['results']);
                break;
            case 'notes':
                $response = $generate->generateNotes($_SESSION['results']);
                if ($response && isset($response['answer'])) {
                    $_SESSION['last_notes'] = $response['answer'];
                }
                break;
        }
        
        if ($response && isset($response['answer'])) {
            echo json_encode([
                'success' => true,
                'answer' => $response['answer'],
                'canDownload' => $_POST['action'] === 'notes'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Tidak ada respons dari sistem'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Action tidak valid'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 