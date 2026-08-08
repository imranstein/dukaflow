<?php

declare(strict_types=1);

namespace App\Modules\Orders\Database\Factories;

use App\Modules\Orders\Enums\PaymentMethod;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderPayment;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<OrderPayment> */
class OrderPaymentFactory extends Factory
{
    /** @var class-string<OrderPayment> */
    protected $model = OrderPayment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'method' => PaymentMethod::Cash,
            'amount_minor' => fake()->numberBetween(100_00, 5000_00),
            'received_on' => Carbon::today(),
        ];
    }

    public function of(string $amount, PaymentMethod $method = PaymentMethod::Cash): self
    {
        return $this->state([
            'amount_minor' => Money::fromDecimal($amount)->minorUnits,
            'method' => $method,
        ]);
    }
}
