<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\PriceListFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $currency
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property bool $is_default
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PriceList extends Model
{
    /** @use HasFactory<PriceListFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'code',
        'name',
        'currency',
        'effective_from',
        'effective_to',
        'is_default',
        'is_active',
    ];

    /** @return HasMany<PriceListItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PriceListItem::class);
    }

    /** @return HasMany<PriceListAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(PriceListAssignment::class);
    }

    /**
     * Active, and in force on the given date. An open-ended list (no
     * effective_to) stays in force indefinitely.
     *
     * @param  Builder<PriceList>  $query
     */
    public function scopeEffectiveOn(Builder $query, Carbon $date): void
    {
        $query->where('is_active', true)
            ->whereDate('effective_from', '<=', $date)
            ->where(function (Builder $query) use ($date): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date);
            });
    }

    public function isEffectiveOn(Carbon $date): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->effective_from->startOfDay()->greaterThan($date->copy()->startOfDay())) {
            return false;
        }

        return $this->effective_to === null
            || $this->effective_to->startOfDay()->greaterThanOrEqualTo($date->copy()->startOfDay());
    }

    /** @return Factory<PriceList> */
    protected static function newFactory(): Factory
    {
        return PriceListFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
