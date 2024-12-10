<?php

class Generate {
    private $apiKey;
    private $model;

    public function __construct() {
        // Periksa apakah environment variables tersedia
        if (!isset($_ENV['COHERE_API_KEY']) || empty($_ENV['COHERE_API_KEY'])) {
            throw new Exception('COHERE_API_KEY tidak ditemukan di environment variables');
        }
        
        if (!isset($_ENV['COHERE_CHAT_MODEL']) || empty($_ENV['COHERE_CHAT_MODEL'])) {
            throw new Exception('COHERE_CHAT_MODEL tidak ditemukan di environment variables');
        }

        $this->apiKey = $_ENV['COHERE_API_KEY'];
        $this->model = $_ENV['COHERE_CHAT_MODEL'];
    }

    // Fungsi helper untuk mempersiapkan dokumen
    private function prepareDocuments($retrievalResults) {
        $documents = [];
        foreach ($retrievalResults as $result) {
            if ($result['score'] > 0.5) {
                $documents[] = $result['text'];
            }
        }
        return $documents;
    }

    public function chat($question, $retrievalResults) {
        try {
            $documents = $this->prepareDocuments($retrievalResults);
            $systemPrompt = "Anda adalah asisten yang membantu menjawab pertanyaan berdasarkan dokumen yang diberikan. " .
                        "Gunakan informasi dari dokumen untuk memberikan jawaban yang akurat. " . 
                        "Jika informasi tidak tersedia dalam dokumen, katakan bahwa Anda tidak dapat menjawab berdasarkan dokumen yang ada. " .
                        "Jika pertanyaan tidak ada hubungannya dengan dokumen, katakan bahwa Anda tidak dapat menjawab pertanyaan tersebut. " .
                        "Jawab dalam bahasa Indonesia.";

            return $this->callCohereAPI($question, $documents, $systemPrompt, 'chat');
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function generateSummary($retrievalResults) {
        try {
            $documents = $this->prepareDocuments($retrievalResults);
            $systemPrompt = "Anda adalah asisten yang bertugas membuat ringkasan dari dokumen yang diberikan. " .
                        "Buatlah ringkasan yang padat dan informatif dalam format poin-poin. " .
                        "Fokus pada informasi penting dan konsep kunci. " .
                        "Gunakan bahasa Indonesia yang baik dan benar.";

            return $this->callCohereAPI("Buatkan ringkasan dari dokumen berikut", $documents, $systemPrompt, 'summary');
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function generateExercises($retrievalResults) {
        try {
            $documents = $this->prepareDocuments($retrievalResults);
            $systemPrompt = "Anda adalah seorang guru yang membuat soal latihan berdasarkan materi dalam dokumen. " .
                        "Buatlah 5 soal latihan pilihan ganda beserta jawabannya. " .
                        "Setiap soal harus mencakup konsep penting dari materi. " .
                        "Berikan juga penjelasan untuk setiap jawaban. " .
                        "Gunakan bahasa Indonesia.";

            return $this->callCohereAPI("Buatkan soal latihan dari materi berikut", $documents, $systemPrompt, 'exercises');
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function generateNotes($retrievalResults) {
        try {
            $documents = $this->prepareDocuments($retrievalResults);
            $systemPrompt = "Anda adalah asisten yang membuat catatan pembelajaran dari dokumen. " .
                        "Buatlah catatan yang terstruktur dengan format outline. " .
                        "Sertakan contoh dan penjelasan untuk konsep-konsep penting. " .
                        "Gunakan bahasa Indonesia yang mudah dipahami.";

            return $this->callCohereAPI("Buatkan catatan pembelajaran dari materi berikut", $documents, $systemPrompt, 'notes');
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function callCohereAPI($question, $documents, $systemPrompt, $task) {
        $client = new \GuzzleHttp\Client();
        $url = "https://api.cohere.ai/v2/chat";
        
        // Sesuaikan temperature berdasarkan task
        $temperature = match($task) {
            'chat' => 0.3,
            'summary' => 0.2,
            'exercises' => 0.7,
            'notes' => 0.4,
            default => 0.3
        };

        $payload = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ],
            'json' => [
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt
                    ],
                    [
                        'role' => 'user',
                        'content' => $question
                    ]
                ],
                'model' => $this->model,
                'temperature' => $temperature,
                'stream' => false,
                'documents' => array_map(function($doc) {
                    return [
                        'id' => md5($doc),
                        'data' => [
                            'content' => $doc
                        ]
                    ];
                }, $documents)
            ]
        ];

        try {
            $response = $client->post($url, $payload);
            $result = json_decode($response->getBody()->getContents(), true);
            
            if (isset($result['message']['content'][0]['text'])) {
                return [
                    'success' => true,
                    'answer' => $result['message']['content'][0]['text'],
                    'task' => $task
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Tidak ada jawaban dari API'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error API: ' . $e->getMessage()
            ];
        }
    }
}
?>
