<?php

namespace App\Services\Tally;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TallyClient
{
    /*
    |--------------------------------------------------------------------------
    | SEND XML TO TALLY
    |--------------------------------------------------------------------------
    */

    public function send(
        string $xml,
        string $type = 'TALLY'
    ): bool {

        try {

            $url = trim(
                config('tally.tally_url')
            );

            /*
            |--------------------------------------------------------------------------
            | URL CHECK
            |--------------------------------------------------------------------------
            */

            if (empty($url)) {

                Log::error(
                    $type . ' URL MISSING'
                );

                return false;
            }

            Log::info(
                $type . ' REQUEST SENT',
                [
                    'url' => $url,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | HTTP REQUEST
            |--------------------------------------------------------------------------
            */

            $response = Http::withHeaders([

                'Content-Type' => 'text/xml',
                'Accept' => 'text/xml',

            ])->timeout(30)

              ->connectTimeout(10)

              ->withBody($xml, 'text/xml')

              ->post($url);

            /*
            |--------------------------------------------------------------------------
            | RESPONSE
            |--------------------------------------------------------------------------
            */

            $status = $response->status();

            $body = trim(
                $response->body()
            );

            Log::info(
                $type . ' RESPONSE RECEIVED',
                [
                    'status' => $status,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | HTTP FAILED
            |--------------------------------------------------------------------------
            */

            if (!$response->successful()) {

                Log::error(
                    $type . ' HTTP ERROR',
                    [

                        'status' => $status,

                        'response' => $body,
                    ]
                );

                return false;
            }

            /*
            |--------------------------------------------------------------------------
            | EMPTY RESPONSE
            |--------------------------------------------------------------------------
            */

            if (empty($body)) {

                Log::error(
                    $type . ' EMPTY RESPONSE'
                );

                return false;
            }

            /*
            |--------------------------------------------------------------------------
            | MULTIPLE LINE ERRORS
            |--------------------------------------------------------------------------
            */

            preg_match_all(
                '/<LINEERROR>(.*?)<\/LINEERROR>/is',
                $body,
                $lineErrors
            );

            if (!empty($lineErrors[1])) {

                foreach ($lineErrors[1] as $error) {

                    Log::error(
                        $type . ' LINE ERROR',
                        [

                            'message' => html_entity_decode(
                                trim(strip_tags($error))
                            ),
                        ]
                    );
                }

                return false;
            }

            /*
            |--------------------------------------------------------------------------
            | XML EXCEPTIONS
            |--------------------------------------------------------------------------
            */

            preg_match_all(
                '/<EXCEPTION>(.*?)<\/EXCEPTION>/is',
                $body,
                $xmlExceptions
            );

            if (!empty($xmlExceptions[1])) {

                foreach ($xmlExceptions[1] as $exception) {

                    Log::error(
                        $type . ' XML EXCEPTION',
                        [

                            'message' => html_entity_decode(
                                trim(strip_tags($exception))
                            ),
                        ]
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | EXCEPTION COUNT
            |--------------------------------------------------------------------------
            */

            preg_match(
                '/<EXCEPTIONS>(.*?)<\/EXCEPTIONS>/is',
                $body,
                $exceptions
            );

            $exceptionCount = (int) (
                $exceptions[1] ?? 0
            );

            if ($exceptionCount > 0) {

                Log::error(
                    $type . ' TALLY EXCEPTION',
                    [

                        'exceptions' => $exceptionCount,

                        'response' => $body,
                    ]
                );

                return false;
            }

            /*
            |--------------------------------------------------------------------------
            | CREATED / ALTERED / COMBINED
            |--------------------------------------------------------------------------
            */

            $created = $this->extractTagValue(
                $body,
                'CREATED'
            );

            $altered = $this->extractTagValue(
                $body,
                'ALTERED'
            );

            $combined = $this->extractTagValue(
                $body,
                'COMBINED'
            );

            $errors = $this->extractTagValue(
                $body,
                'ERRORS'
            );

            /*
            |--------------------------------------------------------------------------
            | SUCCESS CASES
            |--------------------------------------------------------------------------
            */

            if (

                ($created >= 1)

                ||

                ($altered >= 1)

                ||

                ($combined >= 1)

                ||

                (

                    $created == 0
                    &&
                    $altered == 0
                    &&
                    $errors == 0

                )

            ) {

                Log::info(
                    $type . ' SUCCESS',
                    [

                        'created' => $created,

                        'altered' => $altered,

                        'combined' => $combined,

                        'errors' => $errors,
                    ]
                );

                return true;
            }

            /*
            |--------------------------------------------------------------------------
            | UNKNOWN FAILURE
            |--------------------------------------------------------------------------
            */

            Log::error(
                $type . ' UNKNOWN FAILURE',
                [

                    'response' => $body,
                ]
            );

            return false;

        } catch (\Throwable $e) {

            Log::error(
                $type . ' CLIENT EXCEPTION',
                [

                    'message' => $e->getMessage(),
                ]
            );

            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | EXTRACT XML TAG VALUE
    |--------------------------------------------------------------------------
    */

    private function extractTagValue(
        string $xml,
        string $tag
    ): int {

        preg_match(
            "/<{$tag}>(.*?)<\/{$tag}>/is",
            $xml,
            $matches
        );

        return (int) (
            $matches[1] ?? 0
        );
    }
}