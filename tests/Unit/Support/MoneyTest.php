<?php

declare(strict_types=1);

use App\Support\Money;

it('holds minor units without rounding', function () {
    expect(Money::ofMinor(1250)->minorUnits)->toBe(1250)
        ->and(Money::ofMinor(1250)->currency)->toBe('ETB');
});

it('parses decimal strings exactly', function (string $input, int $expected) {
    expect(Money::fromDecimal($input)->minorUnits)->toBe($expected);
})->with([
    'whole number' => ['12', 1200],
    'two decimals' => ['12.50', 1250],
    'one decimal' => ['12.5', 1250],
    'trailing zeros' => ['12.00', 1200],
    'zero' => ['0', 0],
    'sub-unit only' => ['0.07', 7],
    'negative' => ['-3.25', -325],
    'large' => ['1234567.89', 123456789],
]);

it('rejects amounts it cannot represent exactly', function (string $input) {
    Money::fromDecimal($input);
})->with([
    'too many decimals' => ['12.505'],
    'not a number' => ['twelve'],
    'empty' => [''],
    'comma separator' => ['1,250.00'],
    'currency prefix' => ['ETB 12.50'],
    // Casting these would saturate at PHP_INT_MAX and quietly return a
    // different, smaller amount than the caller asked for.
    'past the integer range' => ['92233720368547758.08'],
    'far past it' => ['999999999999999999999.99'],
])->throws(InvalidArgumentException::class);

it('accepts the largest amount it can hold', function () {
    expect(Money::fromDecimal('92233720368547758.07')->minorUnits)->toBe(PHP_INT_MAX);
});

it('formats large amounts without losing santim to a float', function () {
    $money = Money::ofMinor(123456789012345678);

    expect($money->toDecimal())->toBe('1234567890123456.78')
        ->and($money->format())->toBe('ETB 1,234,567,890,123,456.78');
});

it('groups thousands', function (int $minor, string $formatted) {
    expect(Money::ofMinor($minor)->format())->toBe($formatted);
})->with([
    'sub-unit' => [7, 'ETB 0.07'],
    'units' => [1250, 'ETB 12.50'],
    'thousands' => [123450, 'ETB 1,234.50'],
    'millions' => [123456789, 'ETB 1,234,567.89'],
    'negative' => [-123450, 'ETB -1,234.50'],
    'zero' => [0, 'ETB 0.00'],
]);

it('survives the arithmetic that breaks floats', function () {
    $tenCents = Money::fromDecimal('0.10');
    $twentyCents = Money::fromDecimal('0.20');

    expect($tenCents->plus($twentyCents)->toDecimal())->toBe('0.30');

    // The same sum in floating point, for contrast.
    expect(0.1 + 0.2)->not->toBe(0.3);
});

it('adds, subtracts and multiplies', function () {
    $price = Money::fromDecimal('12.50');

    expect($price->plus(Money::fromDecimal('2.50'))->toDecimal())->toBe('15.00')
        ->and($price->minus(Money::fromDecimal('2.50'))->toDecimal())->toBe('10.00')
        ->and($price->multipliedBy(24)->toDecimal())->toBe('300.00')
        ->and($price->multipliedBy(0)->isZero())->toBeTrue();
});

it('refuses to combine different currencies', function () {
    Money::ofMinor(100, 'ETB')->plus(Money::ofMinor(100, 'USD'));
})->throws(InvalidArgumentException::class, 'Cannot combine ETB with USD.');

it('is immutable', function () {
    $original = Money::fromDecimal('10.00');
    $original->plus(Money::fromDecimal('5.00'));

    expect($original->toDecimal())->toBe('10.00');
});

it('round-trips through decimal', function (string $amount) {
    expect(Money::fromDecimal($amount)->toDecimal())->toBe($amount);
})->with(['0.00', '0.07', '12.50', '300.00', '-3.25', '1234567.89']);

it('formats with its currency', function () {
    expect(Money::fromDecimal('1234.50')->format())->toBe('ETB 1,234.50')
        ->and((string) Money::ofMinor(500, 'usd'))->toBe('USD 5.00');
});

it('normalises the currency code', function () {
    expect(Money::ofMinor(1, 'etb')->currency)->toBe('ETB')
        ->and(Money::ofMinor(1, ' usd ')->currency)->toBe('USD');
});

it('rejects codes that are not three letters', function (string $currency) {
    Money::ofMinor(1, $currency);
})->with(['ET', 'ETBB', '123', ''])->throws(InvalidArgumentException::class);

it('compares by amount and currency', function () {
    expect(Money::ofMinor(100)->equals(Money::ofMinor(100)))->toBeTrue()
        ->and(Money::ofMinor(100)->equals(Money::ofMinor(101)))->toBeFalse()
        ->and(Money::ofMinor(100, 'ETB')->equals(Money::ofMinor(100, 'USD')))->toBeFalse();
});

it('reports sign', function () {
    expect(Money::fromDecimal('-0.01')->isNegative())->toBeTrue()
        ->and(Money::zero()->isNegative())->toBeFalse()
        ->and(Money::zero()->isZero())->toBeTrue();
});
