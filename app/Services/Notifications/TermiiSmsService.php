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
        $this->senderId = (string) config('nimcos.termii.sender_id', 'NIMCOS');
        $this->channel = (string) config('nimcos.termii.channel', 'generic');
    }

    public function isEnabled(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Send SMS to a phone number. Automatically normalises Nigerian phone numbers.
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
                return true;
            }

            Log::error('TermiiSms dispatch failed: '.$response->body(), [
                'status' => $response->status(),
                'to' => $normalised,
            ]);

            return false;
        } catch (Throwable $e) {
            Log::error('TermiiSms exception: '.$e->getMessage(), [
                'to' => $normalised,
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
