<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Database\Factories;

use App\Modules\Inventory\Enums\ReconciliationStatus;
use App\Modules\Inventory\Models\StockReconciliation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<StockReconciliation> */
class StockReconciliationFactory extends Factory
{
    /** @var class-string<StockReconciliation> */
    protected $model = StockReconciliation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'sales_rep_id' => fake()->numberBetween(1, 20),
            'reconciled_on' => Carbon::today(),
            'status' => ReconciliationStatus::Open,
        ];
    }

    public function forRep(int $salesRepId): self
    {
        return $this->state(['sales_rep_id' => $salesRepId]);
    }

    public function closed(): self
    {
        return $this->state([
            'status' => ReconciliationStatus::Closed,
            'closed_at' => Carbon::now(),
        ]);
    }
}
