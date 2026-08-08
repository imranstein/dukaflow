<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Database\Seeders;

use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * Only the warehouses, which are this module's own. The stock in them depends
 * on the catalogue and on who the reps are, so it is composed in
 * Database\Seeders\TradingDemoSeeder where both are visible.
 */
class InventorySeeder extends Seeder
{
    /** @var list<array{code: string, name: string, address: string, default: bool}> */
    private const WAREHOUSES = [
        ['code' => 'WH-KAL', 'name' => 'Kality depot', 'address' => 'Kality, Addis Ababa', 'default' => true],
        ['code' => 'WH-GER', 'name' => 'Gerji store', 'address' => 'Gerji, Addis Ababa', 'default' => false],
    ];

    public function run(): void
    {
        foreach (self::WAREHOUSES as $warehouse) {
            Warehouse::query()->updateOrCreate(
                ['code' => $warehouse['code']],
                [
                    'name' => $warehouse['name'],
                    'address' => $warehouse['address'],
                    'is_default' => $warehouse['default'],
                    'is_active' => true,
                ],
            );
        }
    }
}
