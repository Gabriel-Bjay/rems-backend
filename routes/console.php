<?php

use App\Services\Billing;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('billing:generate-invoices', function (Billing $billing) {
    $this->info("Issued {$billing->generateDueInvoices()} invoice(s).");
})->purpose('Issue the current period invoice for every active tenancy that lacks one');

Artisan::command('billing:mark-overdue', function (Billing $billing) {
    $this->info("Marked {$billing->markOverdue()} invoice(s) overdue.");
})->purpose('Flag unpaid and partially paid invoices past their due date');

// Daily rather than monthly: billing periods follow each tenancy's start date.
Schedule::command('billing:generate-invoices')->dailyAt('06:00');
Schedule::command('billing:mark-overdue')->dailyAt('06:30');
