<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    /**
     * @return array<string, array{string, int}>
     */
    public static function validInput(): array
    {
        return [
            'integer' => ['85', 8500],
            'dot one digit' => ['85.5', 8550],
            'dot two digits' => ['85.50', 8550],
            'comma' => ['85,50', 8550],
            'zero' => ['0', 0],
            'leading zeros' => ['007,05', 705],
            'only kuruş' => ['0.01', 1],
            'negative' => ['-2.25', -225],
            'whitespace' => ['  12,3 ', 1230],
            'largest whole' => ['9999999999999999.99', 999999999999999999],
        ];
    }

    #[Test]
    #[DataProvider('validInput')]
    public function it_parses_decimal_input_without_floats(string $input, int $minor): void
    {
        $this->assertSame($minor, Money::fromDecimalString($input)->minor);
        $this->assertTrue(Money::isValidDecimalString($input));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidInput(): array
    {
        return [
            'empty' => [''],
            'three fraction digits' => ['1.999'],
            'thousands separator' => ['1.299,00'],
            'two separators' => ['1,2,3'],
            'letters' => ['12a'],
            'currency symbol' => ['₺12'],
            'exponent' => ['1e3'],
            'trailing separator' => ['12.'],
            'leading separator' => ['.5'],
            'plus sign' => ['+5'],
            'too large' => ['99999999999999999'],
        ];
    }

    #[Test]
    #[DataProvider('invalidInput')]
    public function it_rejects_invalid_input(string $input): void
    {
        $this->assertFalse(Money::isValidDecimalString($input));

        $this->expectException(InvalidArgumentException::class);
        Money::fromDecimalString($input);
    }

    #[Test]
    public function it_renders_a_decimal_string_for_inputs(): void
    {
        $this->assertSame('85.50', Money::fromMinor(8550)->toDecimalString());
        $this->assertSame('0.05', Money::fromMinor(5)->toDecimalString());
        $this->assertSame('-0.05', Money::fromMinor(-5)->toDecimalString());
        $this->assertSame('1299.00', Money::fromMinor(129900)->toDecimalString());
    }

    #[Test]
    public function it_round_trips_between_decimal_string_and_minor_units(): void
    {
        foreach ([0, 1, 99, 100, 8550, 129900, -225] as $minor) {
            $this->assertSame($minor, Money::fromDecimalString(Money::fromMinor($minor)->toDecimalString())->minor);
        }
    }

    #[Test]
    public function it_formats_for_display_per_locale(): void
    {
        $tr = Money::fromMinor(129950)->format('tr');
        $this->assertStringContainsString('1.299,50', $tr);
        $this->assertStringContainsString('₺', $tr);

        $this->assertStringContainsString('1,299.50', Money::fromMinor(129950)->format('en'));
    }

    #[Test]
    public function it_normalises_and_validates_the_currency(): void
    {
        $this->assertSame('TRY', Money::fromMinor(1, 'try')->currency);
        $this->assertTrue(Money::fromMinor(100)->equals(Money::fromDecimalString('1')));
        $this->assertFalse(Money::fromMinor(100, 'EUR')->equals(Money::fromMinor(100)));

        $this->expectException(InvalidArgumentException::class);
        Money::fromMinor(1, 'TL');
    }

    #[Test]
    public function it_reports_sign(): void
    {
        $this->assertTrue(Money::fromMinor(-1)->isNegative());
        $this->assertTrue(Money::fromMinor(0)->isZero());
        $this->assertFalse(Money::fromMinor(1)->isNegative());
    }
}
