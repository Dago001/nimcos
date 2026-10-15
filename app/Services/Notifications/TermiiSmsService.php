<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TermiiSmsService
{
    private string $apiKey;

    private string $baseUrl;

    private string $senderId;

    private string $channel;

    public function __construct()
    {
        $this->apiKey = (string) config('nimcos.termii.api_key');
        $this->baseUrl = rtrim((string) config('nimcos.termii.base_url', 'https://v4.api.termii.com'), '/');
        $this->senderId = (string) config('nimcos.termii.sender_id', 'OE Alert');
        $this->channel = (string) config('nimcos.termii.channel', 'dnd');
    }

    public function isEnabled(): bool
    {
        return ! empty($this->apiKey);
    }

    public function getSenderId(): string
    {
        return $this->senderId;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Send SMS to a phone number. Automatically normalises Nigerian phone numbers.
     * If the primary channel fails or is blocked by DND, automatically retries
     * on the alternative channel (generic <-> dnd).
     */
    public function send(string $phone, string $message): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $normalised = $this->normaliseNigerianPhone($phone);
        if ($normalised === null) {
            Log::warning("TermiiSms: invalid phone number provided: {$phone}");

            return false;
        }

        try {
            $response = Http::timeout(10)->post("{$this->baseUrl}/api/sms/send", [
                'to' => $normalised,
                'from' => $this->senderId,
                'sms' => $message,
                'type' => 'plain',
                'channel' => $this->channel,
                'api_key' => $this->apiKey,
            ]);

            if ($response->successful()) {
                $body = $response->json();
                if (isset($body['code']) && strtolower((string) $body['code']) === 'error') {
                    Log::warning('TermiiSms primary channel warning: '.($body['message'] ?? 'Unknown error').'. Retrying with fallback channel.', [
                        'channel' => $this->channel,
                        'to' => $normalised,
                    ]);

                    return $this->retryWithFallbackChannel($normalised, $message);
                }

                Log::info("TermiiSms dispatched to {$normalised} via {$this->channel} (Sender ID: {$this->senderId})", [
                    'message_id' => $body['message_id'] ?? null,
                ]);

                return true;
            }

            Log::warning('TermiiSms dispatch failed on primary channel ('.$response->status().'): '.$response->body().'. Retrying with fallback channel.', [
                'channel' => $this->channel,
                'to' => $normalised,
            ]);

            return $this->retryWithFallbackChannel($normalised, $message);
        } catch (Throwable $e) {
            Log::error('TermiiSms exception: '.$e->getMessage(), [
                'to' => $normalised,
            ]);

            return false;
        }
    }

    /**
     * Fallback channel delivery (swaps between generic and dnd).
     */
    private function retryWithFallbackChannel(string $normalisedPhone, string $message): bool
    {
        $fallbackChannel = $this->channel === 'generic' ? 'dnd' : 'generic';

        try {
            $response = Http::timeout(10)->post("{$this->baseUrl}/api/sms/send", [
                'to' => $normalisedPhone,
                'from' => $this->senderId,
                'sms' => $message,
                'type' => 'plain',
                'channel' => $fallbackChannel,
                'api_key' => $this->apiKey,
            ]);

            if ($response->successful()) {
                $body = $response->json();
                if (isset($body['code']) && strtolower((string) $body['code']) === 'error') {
                    Log::error('TermiiSms fallback channel failed: '.($body['message'] ?? 'Unknown error'), [
                        'channel' => $fallbackChannel,
                        'to' => $normalisedPhone,
                    ]);

                    return false;
                }

                Log::info("TermiiSms dispatched to {$normalisedPhone} via fallback channel {$fallbackChannel} (Sender ID: {$this->senderId})", [
                    'message_id' => $body['message_id'] ?? null,
                ]);

                return true;
            }

            Log::error('TermiiSms fallback channel failed ('.$response->status().'): '.$response->body(), [
                'channel' => $fallbackChannel,
                'to' => $normalisedPhone,
            ]);

            return false;
        } catch (Throwable $e) {
            Log::error('TermiiSms fallback channel exception: '.$e->getMessage(), [
                'to' => $normalisedPhone,
            ]);

            return false;
        }
    }

    /**
     * Normalise 080..., 23480..., +23480... to 23480...
     */
    public function normaliseNigerianPhone(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '234') && strlen($digits) === 13) {
            return $digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '234'.substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            return '234'.$digits;
        }

        return strlen($digits) >= 10 ? $digits : null;
    }
}
