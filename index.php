<?php
session_start();

require 'vendor/autoload.php';
require 'src/chunk.php';
require 'src/embedding.php';
require 'src/retrieval.php';
require 'src/generate.php';

// Load .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Fungsi untuk menangani upload file
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['pdf_file'])) {
        $uploadedFile = $_FILES['pdf_file']['tmp_name'];
        
        $chunk = new Chunk();
        $_SESSION['chunks'] = $chunk->processPDF($uploadedFile);

        $embedding = new Embedding();
        $_SESSION['embeddings_chunks'] = $embedding->embedChunks($_SESSION['chunks']);
    }

    $generate = new Generate();
    $retrieval = new Retrieval();

    // Handle berbagai jenis request
    if (isset($_POST['action'])) {
        $_SESSION['results'] = $retrieval->search();
        
        switch($_POST['action']) {
            case 'chat':
                if (isset($_POST['question'])) {
                    $_SESSION['response'] = $generate->chat($_POST['question'], $_SESSION['results']);
                }
                break;
            case 'summary':
                $_SESSION['response'] = $generate->generateSummary($_SESSION['results']);
                break;
            case 'exercises':
                $_SESSION['response'] = $generate->generateExercises($_SESSION['results']);
                break;
            case 'notes':
                $_SESSION['response'] = $generate->generateNotes($_SESSION['results']);
                break;
        }
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Tutor AI</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .loading-spinner {
            border-top-color: #3498db;
            animation: spin 1s linear infinite;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <h1 class="text-3xl font-bold text-center text-gray-800 mb-8">Tutor AI</h1>
        
        <!-- Upload Section -->
        <div class="mb-8 p-6 border-2 border-dashed border-gray-300 rounded-lg text-center bg-white">
            <form id="uploadForm" method="POST" enctype="multipart/form-data">
                <input type="file" name="pdf_file" accept=".pdf" required 
                       class="mb-4 block w-full text-sm text-gray-500
                              file:mr-4 file:py-2 file:px-4
                              file:rounded-md file:border-0
                              file:text-sm file:font-semibold
                              file:bg-blue-50 file:text-blue-700
                              hover:file:bg-blue-100">
                <button type="submit" 
                        class="bg-blue-500 text-white px-6 py-2 rounded-md hover:bg-blue-600 
                               transition duration-200 font-medium">
                    Process PDF
                </button>
            </form>
            <div id="uploadLoading" class="hidden mt-4">
                <div class="loading-spinner w-8 h-8 border-4 border-gray-200 rounded-full mx-auto"></div>
                <p class="mt-2 text-gray-600">Memproses PDF...</p>
            </div>
        </div>

        <!-- Error Messages -->
        <div id="errorMessage" class="hidden p-4 mb-6 bg-red-50 border-l-4 border-red-500 text-red-700"></div>

        <!-- Tabs -->
        <div class="mb-6 border-b border-gray-200">
            <nav class="flex space-x-4" aria-label="Tabs">
                <button onclick="openTab(event, 'chat')" 
                        class="tab active px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700 
                               hover:border-gray-300 whitespace-nowrap border-b-2 border-transparent">
                    Chat
                </button>
                <button onclick="openTab(event, 'summary')" 
                        class="tab px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700 
                               hover:border-gray-300 whitespace-nowrap border-b-2 border-transparent">
                    Ringkasan
                </button>
                <button onclick="openTab(event, 'exercises')" 
                        class="tab px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700 
                               hover:border-gray-300 whitespace-nowrap border-b-2 border-transparent">
                    Latihan
                </button>
                <button onclick="openTab(event, 'notes')" 
                        class="tab px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700 
                               hover:border-gray-300 whitespace-nowrap border-b-2 border-transparent">
                    Catatan
                </button>
            </nav>
        </div>

        <!-- Tab Contents -->
        <div class="space-y-6">
            <!-- Chat Tab -->
            <div id="chat" class="tab-content">
                <form id="chatForm" class="flex gap-4">
                    <input type="hidden" name="action" value="chat">
                    <input type="text" name="question" 
                           placeholder="Ketik pertanyaan Anda di sini..." required
                           class="flex-1 p-3 border border-gray-300 rounded-md focus:ring-2 
                                  focus:ring-blue-500 focus:border-blue-500">
                    <button type="submit" 
                            class="bg-blue-500 text-white px-6 py-2 rounded-md hover:bg-blue-600 
                                   transition duration-200">
                        Kirim
                    </button>
                </form>
            </div>

            <!-- Summary Tab -->
            <div id="summary" class="tab-content hidden">
                <form id="summaryForm">
                    <input type="hidden" name="action" value="summary">
                    <button type="submit" 
                            class="w-full bg-green-500 text-white px-6 py-3 rounded-md 
                                   hover:bg-green-600 transition duration-200">
                        Generate Ringkasan
                    </button>
                </form>
            </div>

            <!-- Exercises Tab -->
            <div id="exercises" class="tab-content hidden">
                <form id="exercisesForm">
                    <input type="hidden" name="action" value="exercises">
                    <button type="submit" 
                            class="w-full bg-orange-500 text-white px-6 py-3 rounded-md 
                                   hover:bg-orange-600 transition duration-200">
                        Generate Soal Latihan
                    </button>
                </form>
            </div>

            <!-- Notes Tab -->
            <div id="notes" class="tab-content hidden">
                <form id="notesForm">
                    <input type="hidden" name="action" value="notes">
                    <button type="submit" 
                            class="w-full bg-purple-500 text-white px-6 py-3 rounded-md 
                                   hover:bg-purple-600 transition duration-200">
                        Generate Catatan
                    </button>
                </form>
            </div>
        </div>

        <!-- Loading Indicator -->
        <div id="responseLoading" class="hidden my-8 text-center">
            <div class="loading-spinner w-10 h-10 border-4 border-gray-200 rounded-full mx-auto"></div>
            <p class="mt-2 text-gray-600">Memproses permintaan...</p>
        </div>
        
        <!-- Response Area -->
        <div id="responseArea" class="hidden mt-8">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="flex justify-between items-center p-4 border-b border-gray-200">
                    <h3 class="response-title text-lg font-semibold text-gray-800"></h3>
                    <span class="response-timestamp text-sm text-gray-500"></span>
                </div>
                <div class="response-content p-6 prose max-w-none"></div>
            </div>
        </div>
    </div>

    <script>
        function openTab(evt, tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.add('hidden');
            });

            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('border-blue-500', 'text-blue-600');
                tab.classList.add('border-transparent', 'text-gray-500');
            });

            // Show selected tab content
            document.getElementById(tabName).classList.remove('hidden');
            
            // Add active class to clicked tab
            evt.currentTarget.classList.remove('border-transparent', 'text-gray-500');
            evt.currentTarget.classList.add('border-blue-500', 'text-blue-600');
        }

        $(document).ready(function() {
            // Handle file upload
            $('#uploadForm').on('submit', function(e) {
                e.preventDefault();
                var formData = new FormData(this);
                
                $('#uploadLoading').show();
                
                $.ajax({
                    url: 'controller/upload_handler.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            $('#errorMessage').hide();
                        } else {
                            $('#errorMessage').text(response.error).show();
                        }
                    },
                    error: function() {
                        $('#errorMessage').text('Terjadi kesalahan saat upload file').show();
                    },
                    complete: function() {
                        $('#uploadLoading').hide();
                    }
                });
            });

            // Handle semua form submit (chat, summary, exercises, notes)
            $('form').on('submit', function(e) {
                if (this.id === 'uploadForm') return;
                
                e.preventDefault();
                var formData = $(this).serialize();
                var action = $(this).find('input[name="action"]').val();
                
                $('#responseLoading').show();
                $('#responseArea').hide();
                
                $.ajax({
                    url: 'controller/process_request.php',
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            // Set judul berdasarkan action
                            var title = {
                                'chat': 'Chat Response',
                                'summary': 'Ringkasan Materi',
                                'exercises': 'Soal Latihan',
                                'notes': 'Catatan Pembelajaran'
                            }[action];

                            // Set class berdasarkan action
                            $('#responseArea')
                                .removeClass()
                                .addClass('response-area response-' + action);

                            // Update konten
                            $('.response-title').text(title);
                            $('.response-timestamp').text(new Date().toLocaleString('id-ID'));
                            
                            // Format konten berdasarkan jenis response
                            var formattedContent = formatResponse(response.answer, action);
                            $('.response-content').html(formattedContent);
                            
                            $('#responseArea').show();
                            $('#errorMessage').hide();
                        } else {
                            $('#errorMessage').text(response.error).show();
                        }
                    },
                    error: function() {
                        $('#errorMessage').text('Terjadi kesalahan saat memproses permintaan').show();
                    },
                    complete: function() {
                        $('#responseLoading').hide();
                    }
                });
            });

            // Fungsi untuk memformat response berdasarkan jenisnya
            function formatResponse(text, action) {
                switch(action) {
                    case 'exercises':
                        return text.split(/(?=\d+\.)/).map(function(exercise) {
                            if (!exercise.trim()) return '';
                            return `<div class="mb-8 p-6 bg-gray-50 rounded-lg">
                                      <div class="prose">${exercise}</div>
                                   </div>`;
                        }).join('');

                    case 'summary':
                        return text.split('\n').map(function(point) {
                            if (!point.trim()) return '';
                            return `<div class="flex items-start mb-4">
                                      <span class="text-green-500 mr-2">•</span>
                                      <p>${point}</p>
                                   </div>`;
                        }).join('');

                    case 'notes':
                        return text.split('\n').map(function(line) {
                            if (!line.trim()) return '';
                            if (line.startsWith('#')) {
                                return `<h3 class="text-lg font-semibold text-gray-800 mt-6 mb-3">
                                         ${line.replace('#', '')}
                                       </h3>`;
                            }
                            return `<p class="ml-4 mb-2 text-gray-600">${line}</p>`;
                        }).join('');

                    default:
                        return `<div class="prose">${text.replace(/\n/g, '<br>')}</div>`;
                }
            }
        });
    </script>
</body>
</html>
