<?php

namespace App\Services\Tally;

class GstCalculatorService
{
    public function calculate(array $item): array
    {
        $qty = (float) ($item['qty'] ?? 1);

        $rate = (float) ($item['rate'] ?? 0);

        $baseAmount = round($qty * $rate, 2);

        $gstRate = $this->getGstRate(
            $item['hsn_code'] ?? ''
        );

        $gstAmount = round(
            ($baseAmount * $gstRate) / 100,
            2
        );

        $finalAmount = round(
            $baseAmount + $gstAmount,
            2
        );

        return [

            'gst_rate' => $gstRate,

            'base_amount' => $baseAmount,

            'gst_amount' => $gstAmount,

            'final_amount' => $finalAmount,
        ];
    }

    public function getGstRate(string $hsn): int
    {
        $map = [

            '0601' => 0,
            '0602' => 0,
            '1209' => 0,
            '2530' => 0,

            '3101' => 5,
            '5305' => 5,

            '3105' => 12,

            '3808' => 18,
            '8201' => 18,
        ];

        return $map[$hsn] ?? 0;
    }
}