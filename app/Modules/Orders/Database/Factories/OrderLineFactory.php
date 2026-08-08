<?php

declare(strict_types=1);

namespace App\Modules\Orders\Database\Factories;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderLine> */
class OrderLineFactory extends Factory
{
    /** @var class-string<OrderLine> */
    protected $model = OrderLine::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $unitPrice = fake()->numberBetween(50_00, 900_00);
        $quantity = fake()->numberBetween(1, 20);

        return [
            'order_id' => Order::factory(),
            'product_id' => fake()->numberBetween(1, 500),
            'product_sku' => mb_strtoupper(fake()->unique()->bothify('???-####')),
            'product_name' => fake()->words(3, true),
            'unit_code' => fake()->randomElement(['CTN', 'CRT', 'PKT', 'SCK']),
            'quantity' => $quantity,
            'unit_price_minor' => $unitPrice,
            'line_total_minor' => $unitPrice * $quantity,
            'price_list_id' => null,
        ];
    }

    public function of(int $productId, int $quantity, string $unitPrice): self
    {
        $minor = Money::fromDecimal($unitPrice)->minorUnits;

        return $this->state([
            'product_id' => $productId,
            'quantity' => $quantity,
            'unit_price_minor' => $minor,
            'line_total_minor' => $minor * $quantity,
        ]);
    }
}
