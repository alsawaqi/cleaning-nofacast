<?php

namespace App\Services\Integrations;

use Illuminate\Support\Facades\Log;

class LogMessagingGateway implements MessagingGateway
{
    public function send(string $channel, string $recipient, string $template, array $context = []): void
    {
        Log::info('Phase 1 message queued for provider integration.', [
            'channel' => $channel,
            'recipient' => $recipient,
            'template' => $template,
            'context' => $context,
        ]);
    }
}
