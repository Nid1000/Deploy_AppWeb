<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\CustomerLifecycleEmailService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('customers:lifecycle-emails {--limit=100}', function (): void {
    $emails = app(CustomerLifecycleEmailService::class);
    $limit = max(1, (int) $this->option('limit'));
    $welcome = $emails->processPendingWelcomes($limit);
    $dormant = $emails->processDormantCustomers($limit);
    $reviews = $emails->processReviewRequests($limit);

    $this->info("Bienvenida: {$welcome}; reactivacion: {$dormant}; resenas: {$reviews}.");
})->purpose('Send welcome, dormant customer and review request emails');

Schedule::command('customers:lifecycle-emails --limit=100')
    ->hourly()
    ->withoutOverlapping();
