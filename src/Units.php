<?php
declare(strict_types=1);
namespace Decimal;

final class Units
{
    public static function uint(string $value): string
    {
        if (!preg_match('/^(0|[1-9][0-9]*)$/D', $value) || bccomp($value, bcpow('2', '256', 0), 0) >= 0) {
            throw new \InvalidArgumentException('Expected uint256 decimal string');
        }
        return $value;
    }

    public static function parse(string $value, int $decimals = 18): string
    {
        if ($decimals < 0 || $decimals > 255 || !preg_match('/^(0|[1-9][0-9]*)(?:\.([0-9]+))?$/D', $value, $m)) {
            throw new \InvalidArgumentException('Invalid amount');
        }
        $fraction = $m[2] ?? '';
        if (strlen($fraction) > $decimals) throw new \InvalidArgumentException('Too many decimal places');
        return self::uint(ltrim($m[1] . str_pad($fraction, $decimals, '0'), '0') ?: '0');
    }

    public static function format(string $value, int $decimals = 18): string
    {
        self::uint($value);
        if ($decimals < 0 || $decimals > 255) throw new \InvalidArgumentException('Invalid decimals');
        if ($decimals === 0) return $value;
        $value = str_pad($value, $decimals + 1, '0', STR_PAD_LEFT);
        return rtrim(rtrim(substr($value, 0, -$decimals) . '.' . substr($value, -$decimals), '0'), '.');
    }

    public static function hex(string $value): string
    {
        self::uint($value);
        $hex = '';
        do { $hex = dechex((int) bcmod($value, '16', 0)) . $hex; $value = bcdiv($value, '16', 0); } while ($value !== '0');
        return '0x' . $hex;
    }

    public static function decimal(string $hex): string
    {
        if (!preg_match('/^0x(?:0|[1-9a-fA-F][0-9a-fA-F]*)$/D', $hex)) throw new \InvalidArgumentException('Invalid RPC quantity');
        $value = '0';
        foreach (str_split(substr($hex, 2)) as $digit) $value = bcadd(bcmul($value, '16', 0), (string) hexdec($digit), 0);
        return self::uint($value);
    }
}
