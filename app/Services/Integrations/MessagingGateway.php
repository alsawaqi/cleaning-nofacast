<?php

namespace App\Services\Integrations;

interface MessagingGateway
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function send(string $channel, string $recipient, string $template, array $context = []): void;
}
