<?php

namespace App\Services\Tally;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TallyClient
{
    public function send(string $xml, string $type = 'TALLY')
    {
        try {

            $url = config('tally.tally_url');

            Log::info($type . ' REQUEST START', [
                'url' => $url,
            ]);

            $response = Http::withHeaders([
                'Content-Type' => 'text/xml; charset=utf-8',
            ])
            ->timeout(60)
            ->connectTimeout(10)
            ->withBody($xml, 'text/xml')
            ->post($url);

            $responseBody = $response->body();

            Log::info($type . ' RAW RESPONSE', [
                'response' => $responseBody
            ]);

            /*
            |--------------------------------------------------------------------------
            | ONLY REAL TALLY ERRORS
            |--------------------------------------------------------------------------
            */

            if (str_contains($responseBody, '<LINEERROR>')) {

                preg_match('/<LINEERROR>(.*?)<\/LINEERROR>/s', $responseBody, $m);

                Log::error($type . ' FAILED', [

                    'error' => html_entity_decode($m[1] ?? 'Unknown Tally Error'),

                    'response' => $responseBody
                ]);

                return false;
            }
            if (str_contains($responseBody, '<EXCEPTIONS>1</EXCEPTIONS>')) {
                // Full XML log karo
                Log::error('TALLY EXCEPTION DETAIL', ['full_response' => $responseBody]);
            }
            /*
            |--------------------------------------------------------------------------
            | SUCCESS RESPONSE
            |--------------------------------------------------------------------------
            */

            return $responseBody;

        } catch (\Throwable $e) {

            Log::error($type . ' CONNECTION ERROR', [

                'message' => $e->getMessage(),

                'line' => $e->getLine(),

                'file' => $e->getFile(),
            ]);

            return false;
        }
    }
}