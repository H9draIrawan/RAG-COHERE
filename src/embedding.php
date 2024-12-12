<?php

class Embedding {
    private $apiKey;
    private $model;
    private $batchSize = 96; // Batas maksimum per panggilan API
    private $waitTime = 60; // Jeda dalam detik antara batch
    private $maxRetries = 3; // Jumlah maksimum percobaan ulang
    private $retryDelay = 65; // Waktu tunggu dalam detik sebelum mencoba ulang

    public function __construct() {
        $this->apiKey = $_ENV['COHERE_API_KEY'];
        $this->model = $_ENV['COHERE_EMBEDDING_MODEL'];
    }

    public function embedChunks($chunks) {
        set_time_limit(0); // Hindari timeout untuk proses panjang
        
        $batchChunks = array_chunk($chunks, $this->batchSize);
        $allEmbeddings = [];
        $allTexts = []; 

        foreach ($batchChunks as $index => $batch) {
            $retryCount = 0;
            $success = false;

            while (!$success && $retryCount < $this->maxRetries) {
                try {
                    $response = $this->callCohereAPI($batch);
                    $allEmbeddings = array_merge($allEmbeddings, $response['embeddings']);
                    $allTexts = array_merge($allTexts, $response['texts']);
                    $success = true;
                } catch (\Exception $e) {
                    $retryCount++;
                    sleep(60);
                }
            }
        }

        // Simpan hasil embedding
        $this->saveEmbeddings($allEmbeddings, $allTexts, 'chunks');
    }


    public function embedQuestions($question) {
        $logFile = __DIR__ . "/../logs/question_embedding.log";
        
        // Pastikan direktori log ada
        if (!file_exists(dirname($logFile))) {
            mkdir(dirname($logFile), 0777, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] Memproses pertanyaan: $question\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);

        $allEmbeddings = [];
        $allTexts = [];
        
        try {
            // Kirim pertanyaan sebagai array dengan satu elemen
            $response = $this->callCohereAPI([$question]);

            $allEmbeddings = array_merge($allEmbeddings, $response['embeddings']);
            $allTexts = array_merge($allTexts, $response['texts']);
            
            $this->saveEmbeddings($allEmbeddings, $allTexts, 'questions');
            
            $logMessage = "[$timestamp] Berhasil membuat embedding untuk pertanyaan\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
            
            return $allEmbeddings;

        } catch (\Exception $e) {
            $errorMessage = "[$timestamp] Error saat membuat embedding pertanyaan: " . $e->getMessage() . "\n";
            file_put_contents($logFile, $errorMessage, FILE_APPEND);
            throw $e;
        }
    }

    private function callCohereAPI($texts) {
        // Pastikan semua teks menggunakan encoding UTF-8 yang benar
        $sanitizedTexts = array_map(function($text) {
            // Hapus karakter non-UTF8 dan konversi ke UTF-8
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
            // Bersihkan karakter yang tidak valid
            $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text);
            return $text;
        }, $texts);

        $client = new \GuzzleHttp\Client();
        $response = $client->post('https://api.cohere.com/v2/embed', [
            'headers' => [
                'Authorization' => "Bearer " . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => [
                'model' => $this->model,
                'texts' => $sanitizedTexts,
                'input_type' => 'search_document',
                'embedding_types' => ['float']
            ]
        ]);

        return json_decode($response->getBody(), true);
    }

    private function saveEmbeddings($embeddings, $texts, $type) {
        // Set path untuk menyimpan file embeddings
        $filename = __DIR__ . "/../database/embeddings_" . $type . ".json";
        
        // Pastikan direktori ada
        if (!file_exists(dirname($filename))) {
            mkdir(dirname($filename), 0777, true);
        }

        file_put_contents($filename, json_encode(
            [
                'texts' => $texts,
                "embeddings" => $embeddings
            ],
            JSON_PRETTY_PRINT
        ));
    }

}
?>