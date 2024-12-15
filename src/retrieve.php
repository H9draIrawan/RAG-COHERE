<?php
class Retrieve {
    private $chunksPath;
    private $questionsPath;
    private $maxResults = 10;
    private $logFile;

    public function __construct() {
        $this->chunksPath = __DIR__ . "/../database/embeddings_chunks.json";
        $this->questionsPath = __DIR__ . "/../database/embeddings_questions.json";
        $this->logFile = __DIR__ . "/../logs/retrieval.log";
        
        // Buat direktori log jika belum ada
        if (!file_exists(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0777, true);
        }
    }

    private function logMessage($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }

    public function findSimilarChunks($questionEmbedding) {
        try {
            $this->logMessage("Mulai mencari chunk yang relevan");

            if (!file_exists($this->chunksPath)) {
                throw new Exception("File embeddings chunks tidak ditemukan");
            }

            $chunksData = json_decode(file_get_contents($this->chunksPath), true);

            $chunks = $chunksData['embeddings']['float'];
            $texts = $chunksData['texts'];

            foreach ($chunks as $index => $chunk) {
                $similarity = $this->cosineSimilarity($chunk, $questionEmbedding['float'][0]);
                $similarities[] = [
                    'similarity' => $similarity,
                    'text' => $texts[$index]
                ];
            }
            
            usort($similarities, function($a, $b) { return $b['similarity'] <=> $a['similarity']; });
            return array_slice($similarities, 0, $this->maxResults);

        } catch (Exception $e) {
            $this->logMessage("Error: " . $e->getMessage());
            throw $e;
        }
    }

    private function cosineSimilarity($vector1, $vector2) {
        try {
            // Pastikan kedua vektor memiliki panjang yang sama
            if (count($vector1) !== count($vector2)) {
                throw new Exception("Dimensi vektor tidak sama");
            }
    
            // Hitung dot product
            $dotProduct = 0;
            $magnitude1 = 0;
            $magnitude2 = 0;
    
            for ($i = 0; $i < count($vector1); $i++) {
                $dotProduct += $vector1[$i] * $vector2[$i];
                $magnitude1 += $vector1[$i] * $vector1[$i];
                $magnitude2 += $vector2[$i] * $vector2[$i];
            }
    
            // Hitung magnitude (panjang) vektor
            $magnitude1 = sqrt($magnitude1);
            $magnitude2 = sqrt($magnitude2);
    
            // Hindari pembagian dengan nol
            if ($magnitude1 == 0 || $magnitude2 == 0) {
                return 0;
            }
    
            // Hitung cosine similarity
            return $dotProduct / ($magnitude1 * $magnitude2);
    
        } catch (Exception $e) {
            $this->logMessage("Error dalam perhitungan cosine similarity: " . $e->getMessage());
            throw $e;
        }
    }

    public function search($questionEmbedding) {
        try {
            $this->logMessage("Mulai pencarian untuk pertanyaan baru");
            
            // Cari chunk yang relevan
            $similarChunks = $this->findSimilarChunks($questionEmbedding);
            return $similarChunks;
            
        } catch (Exception $e) {
            $this->logMessage("Error dalam pencarian: " . $e->getMessage());
            throw $e;
        }
    }

}
?>
