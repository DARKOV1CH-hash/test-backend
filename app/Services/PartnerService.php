<?php

namespace App\Services;

use App\Models\Postback;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PartnerService
{
    private const MAX_ATTEMPTS = 3;
    private const RETRY_STATUSES = [429, 500, 502, 503, 504];

    public function send(Postback $postback): void
    {
        $url     = config('services.partner.url');
        $attempt = 0;
        $lastError = null;

        while ($attempt < self::MAX_ATTEMPTS) {
            $attempt++;

            try {
                $response = Http::timeout(10)->post($url, [
                    'click_id' => $postback->click_id,
                    'event'    => $postback->event,
                    'amount'   => round((float) $postback->amount, 2),
                ]);

                $postback->attempts = $attempt;
                $postback->partner_response = $response->body();

                if ($response->successful()) {
                    $postback->status = 'sent';
                    $postback->save();
                    return;
                }

                $status = $response->status();
                Log::warning('Partner API error', [
                    'postback_id' => $postback->id,
                    'attempt'     => $attempt,
                    'status'      => $status,
                    'body'        => $response->body(),
                ]);

                // Don't retry on 4xx (except 429)
                if ($status >= 400 && $status < 500 && $status !== 429) {
                    break;
                }

                $lastError = "HTTP {$status}";

            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $lastError = $e->getMessage();
                Log::warning('Partner API connection error', [
                    'postback_id' => $postback->id,
                    'attempt'     => $attempt,
                    'error'       => $lastError,
                ]);
            }
        }

        $postback->attempts = $attempt;
        $postback->status   = 'failed';
        $postback->partner_response = $lastError ?? 'Unknown error';
        $postback->save();

        Log::error('Partner API failed after all attempts', [
            'postback_id' => $postback->id,
            'attempts'    => $attempt,
            'last_error'  => $lastError,
        ]);
    }
}
