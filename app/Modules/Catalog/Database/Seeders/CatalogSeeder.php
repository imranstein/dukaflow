<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Seeders;

use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\PriceListItem;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\UnitOfMeasure;
use App\Support\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class CatalogSeeder extends Seeder
{
    /** @var array<string, string> */
    private const UNITS = [
        'PCS' => 'Piece',
        'BOT' => 'Bottle',
        'CTN' => 'Carton',
        'CRT' => 'Crate',
        'PKT' => 'Packet',
        'SCK' => 'Sack',
        'JRC' => 'Jerrycan',
    ];

    /**
     * A slice of the catalogue an Ethiopian FMCG distributor carries. Trade
     * price is what a shop pays per selling unit; wholesale is the break the
     * larger buyers get.
     *
     * @var list<array{sku: string, name: string, unit: string, pack: int, category: string, trade: string, wholesale: string, description: string}>
     */
    private const PRODUCTS = [
        ['sku' => 'AMB-W-1000', 'name' => 'Ambo Mineral Water 1L', 'unit' => 'CTN', 'pack' => 12, 'category' => 'Water', 'trade' => '312.00', 'wholesale' => '295.00', 'description' => 'Sparkling mineral water, 12 bottles per carton.'],
        ['sku' => 'AMB-W-0500', 'name' => 'Ambo Mineral Water 500ml', 'unit' => 'CTN', 'pack' => 24, 'category' => 'Water', 'trade' => '384.00', 'wholesale' => '366.00', 'description' => 'Sparkling mineral water, 24 bottles per carton.'],
        ['sku' => 'MOH-C-0300', 'name' => 'Moha Cola 300ml', 'unit' => 'CRT', 'pack' => 24, 'category' => 'Soft drinks', 'trade' => '420.00', 'wholesale' => '399.00', 'description' => 'Returnable glass bottle, 24 per crate.'],
        ['sku' => 'MOH-O-0300', 'name' => 'Moha Orange 300ml', 'unit' => 'CRT', 'pack' => 24, 'category' => 'Soft drinks', 'trade' => '420.00', 'wholesale' => '399.00', 'description' => 'Returnable glass bottle, 24 per crate.'],
        ['sku' => 'MOH-T-0300', 'name' => 'Moha Tonic 300ml', 'unit' => 'CRT', 'pack' => 24, 'category' => 'Soft drinks', 'trade' => '432.00', 'wholesale' => '410.00', 'description' => 'Returnable glass bottle, 24 per crate.'],
        ['sku' => 'HAY-M-1000', 'name' => 'Hayat Milk 1L', 'unit' => 'CTN', 'pack' => 12, 'category' => 'Dairy', 'trade' => '660.00', 'wholesale' => '630.00', 'description' => 'UHT full cream milk, 12 cartons per case.'],
        ['sku' => 'SHE-O-3000', 'name' => 'Shemu Sunflower Oil 3L', 'unit' => 'JRC', 'pack' => 6, 'category' => 'Cooking oil', 'trade' => '1450.00', 'wholesale' => '1390.00', 'description' => 'Refined sunflower cooking oil, 6 jerrycans per case.'],
        ['sku' => 'SHE-O-5000', 'name' => 'Shemu Sunflower Oil 5L', 'unit' => 'JRC', 'pack' => 4, 'category' => 'Cooking oil', 'trade' => '1580.00', 'wholesale' => '1510.00', 'description' => 'Refined sunflower cooking oil, 4 jerrycans per case.'],
        ['sku' => 'ALS-P-0500', 'name' => 'Alsan Macaroni 500g', 'unit' => 'PKT', 'pack' => 20, 'category' => 'Pasta', 'trade' => '760.00', 'wholesale' => '725.00', 'description' => 'Dry pasta, 20 packets per case.'],
        ['sku' => 'ALS-S-0500', 'name' => 'Alsan Spaghetti 500g', 'unit' => 'PKT', 'pack' => 20, 'category' => 'Pasta', 'trade' => '760.00', 'wholesale' => '725.00', 'description' => 'Dry pasta, 20 packets per case.'],
        ['sku' => 'DIR-S-1000', 'name' => 'Dire Sugar 1kg', 'unit' => 'SCK', 'pack' => 25, 'category' => 'Staples', 'trade' => '1875.00', 'wholesale' => '1800.00', 'description' => 'White refined sugar, 25 packets per sack.'],
        ['sku' => 'ETH-S-1000', 'name' => 'Ethio Salt 1kg', 'unit' => 'SCK', 'pack' => 25, 'category' => 'Staples', 'trade' => '425.00', 'wholesale' => '405.00', 'description' => 'Iodised table salt, 25 packets per sack.'],
        ['sku' => 'YER-C-0500', 'name' => 'Yirgacheffe Ground Coffee 500g', 'unit' => 'PKT', 'pack' => 20, 'category' => 'Coffee', 'trade' => '4200.00', 'wholesale' => '4050.00', 'description' => 'Medium roast ground coffee, 20 packets per case.'],
        ['sku' => 'LUX-S-0125', 'name' => 'Lux Soap Bar 125g', 'unit' => 'CTN', 'pack' => 48, 'category' => 'Household', 'trade' => '1440.00', 'wholesale' => '1380.00', 'description' => 'Bath soap, 48 bars per carton.'],
        ['sku' => 'OMO-D-1000', 'name' => 'Omo Washing Powder 1kg', 'unit' => 'CTN', 'pack' => 12, 'category' => 'Household', 'trade' => '1620.00', 'wholesale' => '1550.00', 'description' => 'Laundry detergent powder, 12 packets per carton.'],
        ['sku' => 'ROL-T-0010', 'name' => 'Rol Tissue 10-pack', 'unit' => 'CTN', 'pack' => 6, 'category' => 'Household', 'trade' => '870.00', 'wholesale' => '830.00', 'description' => 'Toilet tissue, 10 rolls per pack, 6 packs per carton.'],
    ];

    /** Code of the price list wholesalers are put on, read by the demo data. */
    public const WHOLESALE_LIST = 'PL-WHOLESALE';

    public function run(): void
    {
        $units = $this->seedUnits();
        $products = $this->seedProducts($units);

        $trade = PriceList::query()->updateOrCreate(
            ['code' => 'PL-TRADE'],
            [
                'name' => 'Standard trade price list',
                'currency' => 'ETB',
                'effective_from' => Carbon::today()->startOfYear(),
                'effective_to' => null,
                'is_default' => true,
                'is_active' => true,
            ],
        );

        $wholesale = PriceList::query()->updateOrCreate(
            ['code' => self::WHOLESALE_LIST],
            [
                'name' => 'Wholesale price list',
                'currency' => 'ETB',
                'effective_from' => Carbon::today()->startOfYear(),
                'effective_to' => null,
                'is_default' => false,
                'is_active' => true,
            ],
        );

        foreach (self::PRODUCTS as $row) {
            $productId = $products[$row['sku']];

            $this->price($trade->id, $productId, $row['trade']);
            $this->price($wholesale->id, $productId, $row['wholesale']);
        }
    }

    /** @return array<string, int> unit code to id */
    private function seedUnits(): array
    {
        $ids = [];

        foreach (self::UNITS as $code => $name) {
            $ids[$code] = UnitOfMeasure::query()
                ->updateOrCreate(['code' => $code], ['name' => $name, 'is_active' => true])
                ->id;
        }

        return $ids;
    }

    /**
     * @param  array<string, int>  $units
     * @return array<string, int> sku to id
     */
    private function seedProducts(array $units): array
    {
        $ids = [];

        foreach (self::PRODUCTS as $row) {
            $ids[$row['sku']] = Product::query()->updateOrCreate(
                ['sku' => $row['sku']],
                [
                    'name' => $row['name'],
                    'unit_of_measure_id' => $units[$row['unit']],
                    'pack_size' => $row['pack'],
                    'category' => $row['category'],
                    'description' => $row['description'],
                    'is_active' => true,
                ],
            )->id;
        }

        return $ids;
    }

    private function price(int $priceListId, int $productId, string $amount): void
    {
        PriceListItem::query()->updateOrCreate(
            ['price_list_id' => $priceListId, 'product_id' => $productId],
            ['unit_price_minor' => Money::fromDecimal($amount)->minorUnits],
        );
    }
}
