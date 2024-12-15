<?php
use Smalot\PdfParser\Parser;

class Chunk {
    private $chunkSize;
    private $chunkOverlap;
    private $logFile;
    private $minChunkLength = 100;

    public function __construct($chunkSize = 1024, $chunkOverlap = 256) {
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

}
?>