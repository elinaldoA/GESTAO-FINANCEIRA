<?php

namespace App\Support;

class Money
{
    /**
     * Formats a number pt-BR style, showing up to $maxDecimals decimals but
     * trimmed down to no fewer than 2 — small quantities (a fractional-cent
     * dividend, a currency pair like ARS/BRL worth a few thousandths of a
     * real) would otherwise silently round to "0,00" at a flat 2 decimals.
     */
    public static function trimmed(float $value, int $maxDecimals = 6): string
    {
        $formatted = number_format($value, $maxDecimals, ',', '.');
        [$integer, $decimals] = explode(',', $formatted);
        $decimals = rtrim($decimals, '0');
        $decimals = str_pad($decimals, 2, '0');

        return "{$integer},{$decimals}";
    }
}
