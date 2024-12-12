<?php
use Smalot\PdfParser\Parser;

class Chunk {
    private $chunkSize;
    private $chunkOverlap;
    private $logFile;
    private $minChunkLength = 50; // Minimal panjang chunk yang valid

    public function __construct($chunkSize = 300, $chunkOverlap = 10) {
        $this->chunkSize = $chunkSize;
        $this->chunkOverlap = $chunkOverlap;
        $this->logFile = __DIR__ . "/../logs/chunk_process.log";
        
        if (!file_exists(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0777, true);
        }
    }

    private function logMessage($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }

    public function processPDF($filePath) {
        $text = $this->extractTextFromPDF($filePath);
        $text = $this->cleanText($text);
        return $this->splitTextIntoChunks($text);
    }

    private function extractTextFromPDF($filePath) {
        $parser = new Parser();
        $pdf = $parser->parseFile($filePath);
        return $pdf->getText();
    }

    private function splitTextIntoChunks($text) {
        $chunks = [];
        $length = strlen($text);
        for ($start = 0; $start < $length; $start += ($this->chunkSize - $this->chunkOverlap)) {
            $chunk = substr($text, $start, $this->chunkSize);
            $chunks[] = $chunk;
        }
        return $chunks;
    }

    private function cleanText($text) {
        // Remove non-printable characters
        $text = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $text);
        // Remove non-ASCII characters
        $text = preg_replace('/[\x80-\xFF]/', '', $text);
        // Remove newlines
        $text = preg_replace('/[\r\n]+/', ' ', $text);
        // Remove multiple spaces
        $text = preg_replace('/[ ]+/', ' ', $text);
        // Remove leading and trailing whitespace
        $text = trim($text);
        return $text;
    }


}
?>