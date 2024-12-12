<?php
class Rerank {
    private $apiKey;
    private $apiEndpoint = 'https://api.cohere.ai/v2/rerank';
    private $model;
    private $maxResults = 10;
    private $logFile;

    public function __construct() {
        $this->apiKey = $_ENV['COHERE_API_KEY'];
        $this->model = 'rerank-multilingual-v3.0';
        $this->logFile = __DIR__ . "/../logs/rerank.log";
        
        if (!file_exists(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0777, true);
        }
    }

    private function logMessage($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }

    public function rerank($query, $documents) {
        try {
            $this->logMessage("Mulai proses reranking untuk query: " . $query);

            // Validasi input
            if (empty($query) || empty($documents)) {
                throw new Exception("Query dan documents tidak boleh kosong");
            }

            // Format documents sesuai kebutuhan API
            $formattedDocs = array_map(function($doc) {
                return [
                    'text' => $doc['text'],
                    'index' => isset($doc['index']) ? $doc['index'] : null
                ];
            }, $documents);

            // Siapkan data untuk request
            $data = [
                'query' => $query,
                'documents' => $formattedDocs,
                'model' => $this->model,
                'top_n' => $this->maxResults,
                'return_documents' => true
            ];

            $result = $this->callCohereAPI($data);

            $documents = [];
            foreach($result['results'] as $doc){
                $documents[] = $doc['document']['text'];
            }

            return $documents;

        } catch (Exception $e) {
            $this->logMessage("Error dalam reranking: " . $e->getMessage());
            throw $e;
        }
    }

    private function callCohereAPI($data) {
        $client = new \GuzzleHttp\Client();
        $response = $client->post($this->apiEndpoint, [
            'headers' => [
                'Authorization' => "Bearer " . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => $data
        ]);

        return json_decode($response->getBody(), true);
    }

    private function getRelevanceLabel($score) {
        if ($score >= 0.8) return "Sangat Relevan";
        if ($score >= 0.6) return "Relevan";
        if ($score >= 0.4) return "Cukup Relevan";
        return "Kurang Relevan";
    }

    public function setMaxResults($max) {
        $this->maxResults = max(1, min(100, $max)); // Batasi antara 1-100
    }
}
?>
