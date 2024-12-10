<?php

class Embedding {
    private $apiKey;
    private $model;
    private $batchSize = 96; // Batas maksimum per panggilan API
    private $waitTime = 1; // Jeda dalam detik

    public function __construct() {
        $this->apiKey = $_ENV['COHERE_API_KEY'];
        $this->model = $_ENV['COHERE_EMBEDDING_MODEL'];
    }

    public function embedChunks($chunks) {
        // Tambahkan set_time_limit untuk menghindari timeout
        set_time_limit(0); // 0 berarti tidak ada batas waktu
        
        $batchChunks = array_chunk($chunks, $this->batchSize);
        $allEmbeddings = [];
        $allTexts = []; 
        
        foreach ($batchChunks as $index => $batch) {
            $maxRetries = 3;
            $retryCount = 0;
            
            while ($retryCount < $maxRetries) {
                try {
                    
                    $response = $this->callCohereAPI($batch);
                    if (isset($response['embeddings'])) {
                        $embedCount = count($response['embeddings']);
                        
                        $allEmbeddings = array_merge($allEmbeddings, $response['embeddings']);
                        $allTexts = array_merge($allTexts, $response['texts']);
                        
                        if (($index + 1) % 5 == 0) {
                            $this->saveEmbeddings($allEmbeddings, $allTexts, 'chunks_progress');
                        }
                        
                        // Jika berhasil, keluar dari loop while
                        break;
                    }
                    
                    sleep(60); // Tunggu 1 menit antar batch untuk menghindari rate limit
                    
                } catch (\Exception $e) {
                    $retryCount++;
                    
                    if (strpos($e->getMessage(), '429') !== false) {
                        $waitTime = pow(2, $retryCount) * 60; // Exponential backoff dalam detik
                        sleep($waitTime);
                        continue;
                    }
                    
                    if ($retryCount >= $maxRetries) {
                        // Simpan progress jika terjadi error
                        if (count($allEmbeddings) > 0) {
                            $this->saveEmbeddings($allEmbeddings, $allTexts, 'chunks_error_backup');
                        }
                        break;
                    }
                }
            }
        }

        if (count($allEmbeddings) > 0 && count($allTexts) > 0) {
            $this->saveEmbeddings($allEmbeddings, $allTexts, 'chunks');
        }
        else{
            print_r("Error saat membuat embedding: " . $e->getMessage() . "\n");
        }
        
        return $allEmbeddings;
    }

    public function embedQuestions($question) {

        $allEmbeddings = [];
        $allTexts = [];
        try {
            // Kirim pertanyaan sebagai array dengan satu elemen
            $response = $this->callCohereAPI([$question]);

            $allEmbeddings = array_merge($allEmbeddings, $response['embeddings']);
            $allTexts = array_merge($allTexts, $response['texts']);
            
            $this->saveEmbeddings($allEmbeddings, $allTexts, 'questions');

        } catch (\Exception $e) {
            print_r("Error saat membuat embedding pertanyaan: " . $e->getMessage() . "\n");
            return null;
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