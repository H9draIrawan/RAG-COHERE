<?php

class Generate {
    private $apiKey;
    private $model;

    public function __construct() {
        $this->apiKey = getenv('COHERE_API_KEY');
        $this->model = getenv('COHERE_CHAT_MODEL');
    }


    public function chat($question, $retrievalResults) {
        try {
            // Siapkan dokumen dari hasil retrieval
            $documents = [];
            foreach ($retrievalResults as $result) {
                if ($result['score'] > 0.7) { // Filter hasil dengan similarity tinggi
                    $documents[] = $result['text'];
                }
            }

            $systemPrompt = "Anda adalah asisten yang membantu menjawab pertanyaan berdasarkan dokumen yang diberikan. " .
                        "Gunakan informasi dari dokumen untuk memberikan jawaban yang akurat. " . 
                        "Jika informasi tidak tersedia dalam dokumen, katakan bahwa Anda tidak dapat menjawab berdasarkan dokumen yang ada.".
                        "Jika pertanyaan tidak ada hubungannya dengan dokumen, katakan bahwa Anda tidak dapat menjawab pertanyaan tersebut.".
                        "Jawab dalam bahasa Indonesia.";

            return $this->callCohereAPI($question, $documents, $systemPrompt);

        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function callCohereAPI($question, $documents, $systemPrompt) {
        $client = new \GuzzleHttp\Client();
        $url = "https://api.cohere.ai/v2/chat";
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
                'temperature' => 0.3,
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
                    'answer' => $result['message']['content'][0]['text']
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
