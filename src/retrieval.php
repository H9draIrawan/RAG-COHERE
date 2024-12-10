<?php
class Retrieval {
    private $embeddingsPath;
    private $questionsPath;
    private $maxResults = 10; // Jumlah maksimum hasil yang dikembalikan

    public function __construct() {
        $this->embeddingsPath = __DIR__ . "/../database/embeddings_chunks.json";
        $this->questionsPath = __DIR__ . "/../database/embeddings_questions.json";
    }

    public function findSimilarChunks() {
        // Load embeddings
        $chunksData = json_decode(file_get_contents($this->embeddingsPath), true);
        $questionData = json_decode(file_get_contents($this->questionsPath), true);

        if (!$chunksData || !$questionData) {
            throw new Exception("Tidak dapat membaca file embeddings");
        }

        $questionEmbedding = $questionData['embeddings']['float'][0];
        $similarities = [];

        // Hitung cosine similarity untuk setiap chunk
        foreach ($chunksData['embeddings']['float'] as $index => $chunkEmbedding) {
            // Pastikan kedua embedding adalah array numerik
            if (is_array($questionEmbedding) && is_array($chunkEmbedding)) {
                $similarity = $this->cosineSimilarity($questionEmbedding, $chunkEmbedding);
                
                // Hanya tambahkan jika skor di atas threshold
                $similarities[] = [
                    'index' => $index,
                    'text' => $chunksData['texts'][$index],
                    'score' => $similarity,
                    // Tambahkan metadata tambahan
                    'metadata' => [
                        'length' => strlen($chunksData['texts'][$index]),
                        'keywords' => $this->extractKeywords($chunksData['texts'][$index])
                    ]
                ];
            }
        }

        // Urutkan berdasarkan skor dan faktor lain
        usort($similarities, function($a, $b) {
            // Berikan bobot untuk panjang teks (lebih panjang = lebih lengkap)
            $lengthWeight = 0.1;
            $lengthScoreA = min(1, $a['metadata']['length'] / 1000) * $lengthWeight;
            $lengthScoreB = min(1, $b['metadata']['length'] / 1000) * $lengthWeight;
            
            // Kombinasikan skor similarity dengan faktor lain
            $finalScoreA = $a['score'] + $lengthScoreA;
            $finalScoreB = $b['score'] + $lengthScoreB;
            
            return $finalScoreB <=> $finalScoreA;
        });

        // Ambil hasil terbaik
        $results = array_slice($similarities, 0, $this->maxResults);
        
        // Normalisasi skor
        $maxScore = max(array_column($results, 'score'));
        foreach ($results as &$result) {
            $result['score'] = round($result['score'] / $maxScore, 4);
        }

        return $results;
    }

    private function cosineSimilarity($vector1, $vector2) {
        $dotProduct = 0;
        $norm1 = 0;
        $norm2 = 0;

        foreach ($vector1 as $i => $value1) {
            $value2 = $vector2[$i];
            $dotProduct += $value1 * $value2;
            $norm1 += $value1 * $value1;
            $norm2 += $value2 * $value2;
        }

        $norm1 = sqrt($norm1);
        $norm2 = sqrt($norm2);

        if ($norm1 == 0 || $norm2 == 0) {
            return 0;
        }

        return $dotProduct / ($norm1 * $norm2);
    }

    private function extractKeywords($text, $limit = 5) {
        // Hapus stopwords dan karakter khusus
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', strtolower($text));
        $words = str_word_count($text, 1); // Pisahkan kata-kata
        
        // Hitung frekuensi kata
        $wordFreq = array_count_values($words);
        
        // Urutkan berdasarkan frekuensi
        arsort($wordFreq);
        
        // Ambil kata-kata teratas
        return array_slice(array_keys($wordFreq), 0, $limit);
    }

    public function search() {
        return $this->findSimilarChunks();
    }
}
?>
