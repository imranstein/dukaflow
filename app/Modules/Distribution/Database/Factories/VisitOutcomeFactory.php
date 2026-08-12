<?php

declare(strict_types=1);

namespace App\Modules\Distribution\Database\Factories;

use App\Modules\Distribution\Enums\VisitOutcomeType;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\SalesRep;
use App\Modules\Distribution\Models\VisitOutcome;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<VisitOutcome> */
class VisitOutcomeFactory extends Factory
{
    /** @var class-string<VisitOutcome> */
    protected $model = VisitOutcome::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'client_id' => (string) Str::ulid(),
            'customer_id' => Customer::factory(),
            'sales_rep_id' => SalesRep::factory(),
            'route_id' => null,
            'outcome' => VisitOutcomeType::OrderPlaced,
            'reason' => null,
            'order_id' => null,
            'order_reference' => null,
            'occurred_at' => now(),
            'received_at' => now(),
        ];
    }

    public function noSale(string $reason = 'Shop closed'): self
    {
        return $this->state([
            'outcome' => VisitOutcomeType::NoSale,
            'reason' => $reason,
            'order_id' => null,
            'order_reference' => null,
        ]);
    }

    public function forOrder(int $orderId, string $reference): self
    {
        return $this->state([
            'outcome' => VisitOutcomeType::OrderPlaced,
            'order_id' => $orderId,
            'order_reference' => $reference,
        ]);
    }
}
