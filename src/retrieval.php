<?php
class Retrieval {
    private $embeddingsPath;
    private $maxResults = 10;
    private $logFile;

    public function __construct() {
        $this->embeddingsPath = __DIR__ . "/../database/embeddings_chunks.json";
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

            if (!file_exists($this->embeddingsPath)) {
                throw new Exception("File embeddings chunks tidak ditemukan");
            }

            $chunksData = json_decode(file_get_contents($this->embeddingsPath), true);
            
            if (!isset($chunksData['embeddings']) || !isset($chunksData['texts'])) {
                throw new Exception("Format data chunks tidak valid");
            }

            $similarities = [];
            
            // Preprocessing question vector
            $questionVector = $this->preprocessVector($questionEmbedding['float'][0]);
            
            for ($i = 0; $i < count($chunksData['embeddings']['float']); $i++) {
                // Preprocessing chunk vector
                $chunkVector = $this->preprocessVector($chunksData['embeddings']['float'][$i]);
                
                $similarity = $this->cosineSimilarity($questionVector, $chunkVector);

                $similarities[] = [
                    'text' => $chunksData['texts'][$i],
                    'score' => $similarity
                ];
            }

            usort($similarities, function($a, $b) {
                return $b['score'] <=> $a['score'];
            });

            $results = array_slice($similarities, 0, $this->maxResults);
            
            $this->logMessage("Berhasil menemukan " . count($results) . " chunk relevan");
            return $results;

        } catch (Exception $e) {
            $this->logMessage("Error: " . $e->getMessage());
            throw $e;
        }
    }

    private function cosineSimilarity($vector1, $vector2) {
        if (!is_array($vector1) || !is_array($vector2) || count($vector1) !== count($vector2)) {
            $this->logMessage("Error: Vector tidak valid untuk cosine similarity");
            return 0;
        }

        try {
            $dotProduct = 0;
            $magnitude1 = 0;
            $magnitude2 = 0;

            foreach ($vector1 as $i => $val1) {
                $val2 = $vector2[$i];
                
                if (!is_numeric($val1) || !is_numeric($val2)) {
                    throw new Exception("Nilai non-numerik ditemukan dalam vector");
                }

                $dotProduct += $val1 * $val2;
                $magnitude1 += $val1 * $val1;
                $magnitude2 += $val2 * $val2;
            }

            $magnitude1 = sqrt($magnitude1);
            $magnitude2 = sqrt($magnitude2);

            if ($magnitude1 == 0 || $magnitude2 == 0) {
                throw new Exception("Magnitude vector adalah nol");
            }

            $similarity = $dotProduct / ($magnitude1 * $magnitude2);
            
            return max(0, $similarity);

        } catch (Exception $e) {
            $this->logMessage("Error dalam perhitungan cosine similarity: " . $e->getMessage());
            return 0;
        }
    }

    public function search($questionEmbedding) {
        try {
            $this->logMessage("Mulai pencarian untuk pertanyaan baru");
            
            // Cari chunk yang relevan
            $similarChunks = $this->findSimilarChunks($questionEmbedding);
            
            // Format hasil untuk ditampilkan
            $results = [];
            foreach ($similarChunks as $chunk) {
                $results[] = [
                    'text' => $this->cleanText($chunk['text']),
                    'score' => round($chunk['score'], 4),
                    'relevance' => $this->getRelevanceLabel($chunk['score'])
                ];
            }
            
            $this->logMessage("Pencarian selesai dengan " . count($results) . " hasil");
            return $results;

        } catch (Exception $e) {
            $this->logMessage("Error dalam pencarian: " . $e->getMessage());
            throw $e;
        }
    }

    private function cleanText($text) {
        // Bersihkan text dari karakter yang tidak diinginkan
        $text = preg_replace('/\s+/', ' ', $text); // Gabungkan multiple spaces
        $text = trim($text); // Hapus whitespace di awal dan akhir
        return $text;
    }

    private function getRelevanceLabel($score) {
        if ($score >= 0.4) return "Sangat Relevan";
        if ($score >= 0.3) return "Relevan"; 
        if ($score >= 0.2) return "Cukup Relevan";
        return "Kurang Relevan";
    }

    private function preprocessText($text) {
        $text = strtolower($text); // Konversi ke lowercase
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text); // Hapus karakter khusus
        $text = preg_replace('/\s+/', ' ', $text); // Normalisasi spasi
        return trim($text);
    }

    private function preprocessVector($vector) {
        // Menghilangkan nilai yang terlalu kecil (noise)
        $threshold = 0.001;
        return array_map(function($val) use ($threshold) {
            return abs($val) < $threshold ? 0 : $val;
        }, $vector);
    }
}
?>
