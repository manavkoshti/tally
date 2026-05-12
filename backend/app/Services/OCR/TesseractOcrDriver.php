<?php

namespace App\Services\OCR;

class TesseractOcrDriver
{
    public function extractText(string $filePath, string $fileType): string
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        if ($fileType === 'pdf') {
            return $this->extractFromPdf($filePath);
        }

        return $this->extractFromImage($filePath);
    }

    private function extractFromImage(string $filePath): string
    {
        $tesseractPath = config('ocr.tesseract_path', 'tesseract');
        $outputBase = tempnam(sys_get_temp_dir(), 'ocr_');

        $command = escapeshellcmd("{$tesseractPath} " . escapeshellarg($filePath) . " " . escapeshellarg($outputBase) . " -l eng --oem 1 --psm 6");
        exec($command, $output, $returnCode);

        $textFile = $outputBase . '.txt';
        if ($returnCode !== 0 || !file_exists($textFile)) {
            throw new \RuntimeException("Tesseract OCR failed with code: {$returnCode}");
        }

        $text = file_get_contents($textFile);
        unlink($textFile);
        if (file_exists($outputBase)) unlink($outputBase);

        return $text;
    }

    private function extractFromPdf(string $filePath): string
    {
        // Convert PDF first page to image then run OCR
        $imagePath = tempnam(sys_get_temp_dir(), 'pdf_') . '.jpg';
        $gsPath = config('ocr.ghostscript_path', 'gs');

        $command = escapeshellcmd("{$gsPath} -dNOPAUSE -dBATCH -sDEVICE=jpeg -r300 -dFirstPage=1 -dLastPage=1 -sOutputFile=" . escapeshellarg($imagePath) . " " . escapeshellarg($filePath));
        exec($command, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($imagePath)) {
            throw new \RuntimeException("PDF to image conversion failed.");
        }

        $text = $this->extractFromImage($imagePath);
        if (file_exists($imagePath)) unlink($imagePath);

        return $text;
    }
}
