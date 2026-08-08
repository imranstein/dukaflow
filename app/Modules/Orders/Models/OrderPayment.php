<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Modules\Orders\Database\Factories\OrderPaymentFactory;
use App\Modules\Orders\Enums\PaymentMethod;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property PaymentMethod $method
 * @property int $amount_minor
 * @property Carbon $received_on
 * @property string|null $reference
 * @property string|null $notes
 * @property-read Order $order
 */
class OrderPayment extends Model
{
    /** @use HasFactory<OrderPaymentFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'order_id',
        'method',
        'amount_minor',
        'received_on',
        'reference',
        'notes',
    ];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function amount(): Money
    {
        return Money::ofMinor($this->amount_minor, $this->order->currency);
    }

    /** @return Factory<OrderPayment> */
    protected static function newFactory(): Factory
    {
        return OrderPaymentFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'amount_minor' => 'integer',
            'received_on' => 'date',
        ];
    }
}
