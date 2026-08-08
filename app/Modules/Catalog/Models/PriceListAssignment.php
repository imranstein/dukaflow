<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\PriceListAssignmentFactory;
use App\Modules\Catalog\Enums\PriceListScope;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Attaches a price list to a customer or a route.
 *
 * `scope_id` intentionally has no foreign key: it points at a Distribution
 * table, and the modules do not depend on each other. See
 * Docs/adr/0001-module-boundaries.md.
 *
 * @property int $id
 * @property int $price_list_id
 * @property PriceListScope $scope
 * @property int $scope_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PriceList $priceList
 */
class PriceListAssignment extends Model
{
    /** @use HasFactory<PriceListAssignmentFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['price_list_id', 'scope', 'scope_id'];

    /** @return BelongsTo<PriceList, $this> */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    /** @return Factory<PriceListAssignment> */
    protected static function newFactory(): Factory
    {
        return PriceListAssignmentFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'scope' => PriceListScope::class,
            'scope_id' => 'integer',
        ];
    }
}
