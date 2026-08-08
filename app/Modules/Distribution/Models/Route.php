<?php

declare(strict_types=1);

namespace App\Modules\Distribution\Models;

use App\Modules\Distribution\Database\Factories\RouteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A beat: the run of outlets one rep works through.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int|null $sales_rep_id
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SalesRep|null $salesRep
 */
class Route extends Model
{
    /** @use HasFactory<RouteFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['code', 'name', 'description', 'sales_rep_id', 'is_active'];

    /** @return BelongsTo<SalesRep, $this> */
    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(SalesRep::class);
    }

    /** @return HasMany<Customer, $this> */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /** @param  Builder<Route>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @return Factory<Route> */
    protected static function newFactory(): Factory
    {
        return RouteFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
