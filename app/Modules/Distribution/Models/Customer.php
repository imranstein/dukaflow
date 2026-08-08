<?php

declare(strict_types=1);

namespace App\Modules\Distribution\Models;

use App\Modules\Distribution\Database\Factories\CustomerFactory;
use App\Modules\Distribution\Enums\DayOfWeek;
use App\Modules\Distribution\Enums\OutletType;
use App\Support\Events\ScopeRecordDeleted;
use App\Support\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

/**
 * An outlet: the shop the rep walks into.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property OutletType $outlet_type
 * @property string|null $owner_name
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $latitude
 * @property string|null $longitude
 * @property int|null $route_id
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Route|null $route
 */
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'code',
        'name',
        'outlet_type',
        'owner_name',
        'phone',
        'address',
        'latitude',
        'longitude',
        'route_id',
        'is_active',
    ];

    /** @return BelongsTo<Route, $this> */
    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    /** @return HasMany<VisitSchedule, $this> */
    public function visitSchedules(): HasMany
    {
        return $this->hasMany(VisitSchedule::class);
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /** @param  Builder<Customer>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Outlets due to be called on for a given day, in the order the rep walks
     * them. This is what the PWA asks for when it builds today's round.
     *
     * The ordering is the point: a round served in an arbitrary order sends
     * the rep back and forth across the city. It comes from the sequence on
     * that day's schedule row, so the join is what produces it.
     *
     * @param  Builder<Customer>  $query
     */
    public function scopeScheduledOn(Builder $query, DayOfWeek $day): void
    {
        $query
            ->whereHas('visitSchedules', function (Builder $schedules) use ($day): void {
                $schedules->where('day_of_week', $day->value)->where('is_active', true);
            })
            ->orderBy(
                VisitSchedule::query()
                    ->select('sequence')
                    ->whereColumn('visit_schedules.customer_id', 'customers.id')
                    ->where('day_of_week', $day->value)
                    ->where('is_active', true)
                    ->limit(1)
            )
            ->orderBy('customers.id');
    }

    /**
     * Other modules may hold a reference to this record by bare id, with no
     * foreign key to clean it up. Announcing the deletion lets them.
     */
    protected static function booted(): void
    {
        static::deleted(function (self $record): void {
            Event::dispatch(new ScopeRecordDeleted(Scope::Customer->value, $record->id));
        });
    }

    /** @return Factory<Customer> */
    protected static function newFactory(): Factory
    {
        return CustomerFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'outlet_type' => OutletType::class,
            'is_active' => 'boolean',
        ];
    }
}
