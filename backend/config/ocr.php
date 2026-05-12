<?php

return [
    'driver' => env('OCR_DRIVER', 'tesseract'),
    'tesseract_path' => env('OCR_TESSERACT_PATH', 'tesseract'),
    'ghostscript_path' => env('OCR_GHOSTSCRIPT_PATH', 'gs'),
];
