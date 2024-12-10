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
                        "Ikuti format berikut untuk membuat soal dalam format JSON:\n" .
                        "{\n" .
                        "  \"soal\": [\n" .
                        "    {\n" .
                        "      \"nomor\": 1,\n" .
                        "      \"pertanyaan\": \"[pertanyaan]\",\n" .
                        "      \"pilihan\": {\n" .
                        "        \"A\": \"[pilihan A]\",\n" .
                        "        \"B\": \"[pilihan B]\",\n" .
                        "        \"C\": \"[pilihan C]\",\n" .
                        "        \"D\": \"[pilihan D]\"\n" .
                        "      },\n" .
                        "      \"jawaban_benar\": \"[A/B/C/D]\",\n" .
                        "      \"penjelasan\": \"[penjelasan jawaban]\"\n" .
                        "    }\n" .
                        "  ]\n" .
                        "}\n\n" .
                        "Buat 10 soal pilihan ganda dengan ketentuan:\n" .
                        "1. Setiap soal harus mencakup konsep penting dari materi\n" .
                        "2. Pastikan soal bervariasi dari tingkat kesulitan mudah hingga sulit\n" .
                        "3. Berikan penjelasan yang detail untuk setiap jawaban\n" .
                        "4. Response HARUS dalam format JSON yang valid\n" .
                        "Gunakan bahasa Indonesia yang formal dan mudah dipahami.";

            $response = $this->callCohereAPI(
                "Buatkan soal latihan dari materi berikut dengan format JSON yang telah ditentukan", 
                $documents, 
                $systemPrompt, 
                'exercises'
            );

            // Tambahkan validasi dan parsing JSON
            if ($response['success']) {
                try {
                    // Bersihkan string JSON dari karakter yang tidak diinginkan
                    $jsonStr = preg_replace('/```json\s*|\s*```/', '', $response['answer']);
                    $jsonStr = trim($jsonStr);
                    
                    // Parse JSON
                    $exercises = json_decode($jsonStr, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE && isset($exercises['soal'])) {
                        return [
                            'success' => true,
                            'answer' => $exercises,
                            'task' => 'exercises'
                        ];
                    } else {
                        return [
                            'success' => false,
                            'error' => 'Format JSON tidak valid: ' . json_last_error_msg()
                        ];
                    }
                } catch (Exception $e) {
                    return [
                        'success' => false,
                        'error' => 'Gagal memproses response: ' . $e->getMessage()
                    ];
                }
            }
            
            return $response;
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
            'chat' => 0.7,
            'summary' => 0.3,
            'exercises' => 0.8,
            'notes' => 0.4,
            default => 0.5
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
