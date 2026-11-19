<?php

namespace App\Support;

/**
 * Integer-cents money helpers. Never uses floats: amounts are parsed from decimal strings.
 */
final class Money
{
    /**
     * @throws \InvalidArgumentException when the amount is not a non-negative decimal with up to 2 fraction digits
     */
    public static function toCents(?string $amount): int
    {
        $amount = trim((string) $amount);
        if ($amount === '') {
            return 0;
        }
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $amount)) {
            throw new \InvalidArgumentException('Malformed decimal amount: "'.$amount.'".');
        }
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole) * 100 + (int) str_pad($fraction, 2, '0');
    }

    /**
     * @throws \InvalidArgumentException when the rate is not a non-negative decimal with up to 2 fraction digits
     */
    public static function toBasisPoints(string $rate): int
    {
        if (! preg_match('/^\d+(\.\d{1,2})?$/', trim($rate))) {
            throw new \InvalidArgumentException('Malformed percentage rate: "'.$rate.'".');
        }

        return self::toCents($rate);
    }

    public static function format(int $cents): string
    {
        return number_format(intdiv($cents, 100), 0, ',', '.').','.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function csv(int $cents): string
    {
        return intdiv($cents, 100).','.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function commission(int $valueCents, string $rate): int
    {
        return intdiv($valueCents * self::toBasisPoints($rate) + 5000, 10000);
    }
}
