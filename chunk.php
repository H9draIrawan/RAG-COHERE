<?php
use Smalot\PdfParser\Parser;

class Chunk {
    private $chunkSize;
    private $chunkOverlap;

    public function __construct($chunkSize = 1000, $chunkOverlap = 200) {
        $this->chunkSize = $chunkSize;
        $this->chunkOverlap = $chunkOverlap;
    }

    public function extractTextFromPDF($filePath) {
        $parser = new Parser();
        $pdf = $parser->parseFile($filePath);
        return $pdf->getText();
    }

    public function splitTextIntoChunks($text) {
        $chunks = [];
        $length = strlen($text);

        for ($start = 0; $start < $length; $start += ($this->chunkSize - $this->chunkOverlap)) {
            $chunk = substr($text, $start, $this->chunkSize);
            $chunks[] = $chunk;
        }

        return $chunks;
    }

    public function processPDF($filePath) {
        $text = $this->extractTextFromPDF($filePath);
        return $this->splitTextIntoChunks($text);
    }
}
?>