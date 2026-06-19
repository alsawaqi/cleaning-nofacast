<?php

namespace App\Providers;

use App\Services\Integrations\EInvoiceIssuer;
use App\Services\Integrations\FakePaymentGateway;
use App\Services\Integrations\HtmlPdfExporter;
use App\Services\Integrations\LocalEInvoiceIssuer;
use App\Services\Integrations\LogMessagingGateway;
use App\Services\Integrations\MessagingGateway;
use App\Services\Integrations\PaymentGateway;
use App\Services\Integrations\PdfExporter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, FakePaymentGateway::class);
        $this->app->bind(MessagingGateway::class, LogMessagingGateway::class);
        $this->app->bind(EInvoiceIssuer::class, LocalEInvoiceIssuer::class);
        $this->app->bind(PdfExporter::class, HtmlPdfExporter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
