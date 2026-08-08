<?php

declare(strict_types=1);

namespace App\Modules\Distribution\Models;

use App\Modules\Distribution\Database\Factories\VisitScheduleFactory;
use App\Modules\Distribution\Enums\DayOfWeek;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $customer_id
 * @property DayOfWeek $day_of_week
 * @property int $sequence
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Customer $customer
 */
class VisitSchedule extends Model
{
    /** @use HasFactory<VisitScheduleFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['customer_id', 'day_of_week', 'sequence', 'is_active'];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return Factory<VisitSchedule> */
    protected static function newFactory(): Factory
    {
        return VisitScheduleFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'day_of_week' => DayOfWeek::class,
            'sequence' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
