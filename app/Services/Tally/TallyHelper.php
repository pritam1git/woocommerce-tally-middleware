<?php

namespace App\Services\Tally;

class TallyHelper
{
    public static function success($response)
    {
        if (!$response) {
            return false;
        }

        return str_contains($response, '<CREATED>1</CREATED>')
            || str_contains($response, '<ALTERED>1</ALTERED>');
    }

    public static function hasError($response)
    {
        if (!$response) {
            return true;
        }

        return str_contains($response, '<LINEERROR>');
    }
}