<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * An amount of money, held as a whole number of minor units.
 *
 * Prices are never stored or calculated as floats. 0.1 + 0.2 is not 0.3 in
 * binary floating point, and a distributor reconciling a day of van sales
 * will find that out. See docs/adr/0004-money-handling.md.
 */
final readonly class Money implements JsonSerializable, Stringable
{
    public const string DEFAULT_CURRENCY = 'ETB';

    /**
     * Every currency DukaFlow supports has two decimal places. A currency with
     * a different subunit (JOD has three, JPY has none) would need this to
     * become a per-currency lookup.
     */
    private const int SUBUNIT_DIGITS = 2;

    private function __construct(
        public int $minorUnits,
        public string $currency,
    ) {}

    /**
     * @param  int  $minorUnits  santim for ETB, cents for USD, and so on
     */
    public static function ofMinor(int $minorUnits, string $currency = self::DEFAULT_CURRENCY): self
    {
        return new self($minorUnits, self::normaliseCurrency($currency));
    }

    public static function zero(string $currency = self::DEFAULT_CURRENCY): self
    {
        return new self(0, self::normaliseCurrency($currency));
    }

    /**
     * Parses a decimal string such as "12.50". Takes a string rather than a
     * float on purpose: a float argument would already have lost precision by
     * the time this method saw it.
     */
    public static function fromDecimal(string $amount, string $currency = self::DEFAULT_CURRENCY): self
    {
        $amount = trim($amount);

        if (preg_match('/^(-?)(\d+)(?:\.(\d{1,'.self::SUBUNIT_DIGITS.'}))?$/', $amount, $matches) !== 1) {
            throw new InvalidArgumentException(
                "[{$amount}] is not a decimal amount with at most ".self::SUBUNIT_DIGITS.' decimal places.'
            );
        }

        [, $sign, $whole] = $matches;
        $fraction = str_pad($matches[3] ?? '', self::SUBUNIT_DIGITS, '0');

        $minorUnits = (int) ($whole.$fraction);

        return new self($sign === '-' ? -$minorUnits : $minorUnits, self::normaliseCurrency($currency));
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits + $other->minorUnits, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits - $other->minorUnits, $this->currency);
    }

    /**
     * Quantities in this domain are whole cases, crates and pieces, so an
     * integer factor keeps multiplication exact.
     */
    public function multipliedBy(int $factor): self
    {
        return new self($this->minorUnits * $factor, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->minorUnits === 0;
    }

    public function isNegative(): bool
    {
        return $this->minorUnits < 0;
    }

    public function equals(self $other): bool
    {
        return $this->minorUnits === $other->minorUnits
            && $this->currency === $other->currency;
    }

    /** A decimal string such as "12.50". */
    public function toDecimal(): string
    {
        $sign = $this->minorUnits < 0 ? '-' : '';
        $absolute = (string) abs($this->minorUnits);
        $padded = str_pad($absolute, self::SUBUNIT_DIGITS + 1, '0', STR_PAD_LEFT);

        $whole = substr($padded, 0, -self::SUBUNIT_DIGITS);
        $fraction = substr($padded, -self::SUBUNIT_DIGITS);

        return "{$sign}{$whole}.{$fraction}";
    }

    public function format(): string
    {
        return $this->currency.' '.number_format(
            (float) $this->toDecimal(),
            self::SUBUNIT_DIGITS,
        );
    }

    /** @return array{minorUnits: int, currency: string} */
    public function jsonSerialize(): array
    {
        return [
            'minorUnits' => $this->minorUnits,
            'currency' => $this->currency,
        ];
    }

    public function __toString(): string
    {
        return $this->format();
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Cannot combine {$this->currency} with {$other->currency}."
            );
        }
    }

    private static function normaliseCurrency(string $currency): string
    {
        $currency = mb_strtoupper(trim($currency));

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException("[{$currency}] is not a three letter currency code.");
        }

        return $currency;
    }
}
