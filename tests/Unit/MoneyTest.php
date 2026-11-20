<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    /**
     * @return array<string, array{0: ?string, 1: int}>
     */
    public static function centsProvider(): array
    {
        return ['decimal one digit' => ['1250.5', 125050], 'float trap' => ['0.1', 10], 'integer' => ['1000', 100000], 'two digits' => ['250.50', 25050], 'null' => [null, 0], 'empty' => ['', 0], 'zero' => ['0.00', 0], 'big' => ['9999999999.99', 999999999999]];
    }

    #[DataProvider('centsProvider')]
    public function test_to_cents_parses_decimal_strings_without_floats(?string $input, int $expected): void
    {
        $this->assertSame($expected, Money::toCents($input));
    }

    public function test_format_uses_es_uy_separators(): void
    {
        $this->assertSame('1.234,56', Money::format(123456));
        $this->assertSame('0,00', Money::format(0));
        $this->assertSame('1.000.000,05', Money::format(100000005));
    }

    public function test_csv_uses_decimal_comma_without_thousands_separator(): void
    {
        $this->assertSame('1234,56', Money::csv(123456));
        $this->assertSame('1250,50', Money::csv(125050));
        $this->assertSame('0,05', Money::csv(5));
    }

    public function test_commission_rounds_half_up_once_per_trip(): void
    {
        $this->assertSame(2004, Money::commission(25050, '8.00'));
        $this->assertSame(8000, Money::commission(100000, '8.00'));
        $this->assertSame(1, Money::commission(7, '8.00'));
        $this->assertSame(0, Money::commission(6, '8.00'));
        $this->assertSame(0, Money::commission(0, '8.00'));
    }

    public function test_rate_to_basis_points(): void
    {
        $this->assertSame(800, Money::toBasisPoints('8.00'));
        $this->assertSame(1050, Money::toBasisPoints('10.5'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function malformedProvider(): array
    {
        return ['comma' => ['8,5'], 'three decimals' => ['8.125'], 'letters' => ['abc'], 'negative' => ['-1'], 'trailing dot' => ['8.'], 'spaced inside' => ['8 5']];
    }

    #[DataProvider('malformedProvider')]
    public function test_rate_parsing_is_strict(string $input): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::toBasisPoints($input);
    }

    #[DataProvider('malformedProvider')]
    public function test_amount_parsing_is_strict(string $input): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::toCents($input);
    }
}
