<?php

declare(strict_types=1);

namespace App\Modules\Orders\Database\Factories;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    /** @var class-string<Order> */
    protected $model = Order::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'reference' => 'SO-'.Carbon::now()->format('Y').'-'.fake()->unique()->numerify('#####'),

            // Bare ids: Orders does not know what module these belong to.
            'customer_id' => fake()->numberBetween(1, 500),
            'sales_rep_id' => fake()->numberBetween(1, 20),
            'route_id' => fake()->numberBetween(1, 10),
            'price_list_id' => null,

            'status' => OrderStatus::Draft,
            'currency' => Money::DEFAULT_CURRENCY,
            'total_minor' => 0,
            'placed_at' => Carbon::now(),
        ];
    }

    /**
     * Not named for(), which Eloquent's Factory already uses for relations.
     */
    public function forOutlet(int $customerId, ?int $salesRepId = null, ?int $routeId = null): self
    {
        return $this->state(array_filter([
            'customer_id' => $customerId,
            'sales_rep_id' => $salesRepId,
            'route_id' => $routeId,
        ], fn (mixed $value): bool => $value !== null));
    }

    public function status(OrderStatus $status): self
    {
        return $this->state(fn (): array => [
            'status' => $status,
            'submitted_at' => in_array($status, [
                OrderStatus::Submitted, OrderStatus::Approved, OrderStatus::Fulfilled,
            ], strict: true) ? Carbon::now() : null,
            'approved_at' => in_array($status, [
                OrderStatus::Approved, OrderStatus::Fulfilled,
            ], strict: true) ? Carbon::now() : null,
            'fulfilled_at' => $status === OrderStatus::Fulfilled ? Carbon::now() : null,
            'cancelled_at' => $status === OrderStatus::Cancelled ? Carbon::now() : null,
        ]);
    }

    public function placedOn(Carbon $date): self
    {
        return $this->state(['placed_at' => $date]);
    }
}
