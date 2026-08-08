<?php

declare(strict_types=1);

namespace App\Modules\Distribution\Models;

use App\Models\User;
use App\Modules\Distribution\Database\Factories\SalesRepFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $code
 * @property string $name
 * @property string|null $phone
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
class SalesRep extends Model
{
    /** @use HasFactory<SalesRepFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['user_id', 'code', 'name', 'phone', 'is_active'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Route, $this> */
    public function routes(): HasMany
    {
        return $this->hasMany(Route::class);
    }

    /** @param  Builder<SalesRep>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @return Factory<SalesRep> */
    protected static function newFactory(): Factory
    {
        return SalesRepFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
