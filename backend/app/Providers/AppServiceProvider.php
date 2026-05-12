<?php

namespace App\Providers;

use App\Services\OCR\OcrService;
use App\Services\OCR\TesseractOcrDriver;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OcrService::class, function () {
            return new OcrService(new TesseractOcrDriver());
        });
    }

    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
    }
}
