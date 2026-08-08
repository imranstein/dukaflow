<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Seeders;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    /**
     * A slice of the kind of catalogue an Ethiopian FMCG distributor carries:
     * bottled water, soft drinks, cooking oil, pasta and household staples.
     *
     * @var list<array{sku: string, name: string, description: string}>
     */
    private const PRODUCTS = [
        ['sku' => 'AMB-W-1000', 'name' => 'Ambo Mineral Water 1L', 'description' => 'Sparkling mineral water, 12 bottles per case.'],
        ['sku' => 'AMB-W-0500', 'name' => 'Ambo Mineral Water 500ml', 'description' => 'Sparkling mineral water, 24 bottles per case.'],
        ['sku' => 'MOH-C-0300', 'name' => 'Moha Cola 300ml', 'description' => 'Returnable glass bottle, 24 per crate.'],
        ['sku' => 'MOH-O-0300', 'name' => 'Moha Orange 300ml', 'description' => 'Returnable glass bottle, 24 per crate.'],
        ['sku' => 'HAY-M-1000', 'name' => 'Hayat Milk 1L', 'description' => 'UHT full cream milk, 12 cartons per case.'],
        ['sku' => 'SHE-O-3000', 'name' => 'Shemu Sunflower Oil 3L', 'description' => 'Refined sunflower cooking oil, 6 jerrycans per case.'],
        ['sku' => 'SHE-O-5000', 'name' => 'Shemu Sunflower Oil 5L', 'description' => 'Refined sunflower cooking oil, 4 jerrycans per case.'],
        ['sku' => 'ALS-P-0500', 'name' => 'Alsan Macaroni 500g', 'description' => 'Dry pasta, 20 packets per case.'],
        ['sku' => 'ALS-S-0500', 'name' => 'Alsan Spaghetti 500g', 'description' => 'Dry pasta, 20 packets per case.'],
        ['sku' => 'DIR-S-1000', 'name' => 'Dire Sugar 1kg', 'description' => 'White refined sugar, 25 packets per sack.'],
        ['sku' => 'ETH-S-1000', 'name' => 'Ethio Salt 1kg', 'description' => 'Iodised table salt, 25 packets per sack.'],
        ['sku' => 'YER-C-0500', 'name' => 'Yirgacheffe Ground Coffee 500g', 'description' => 'Medium roast ground coffee, 20 packets per case.'],
        ['sku' => 'LUX-S-0125', 'name' => 'Lux Soap Bar 125g', 'description' => 'Bath soap, 48 bars per case.'],
        ['sku' => 'OMO-D-1000', 'name' => 'Omo Washing Powder 1kg', 'description' => 'Laundry detergent powder, 12 packets per case.'],
        ['sku' => 'ROL-T-0010', 'name' => 'Rol Tissue 10-pack', 'description' => 'Toilet tissue, 10 rolls per pack, 6 packs per case.'],
    ];

    public function run(): void
    {
        foreach (self::PRODUCTS as $product) {
            Product::query()->updateOrCreate(
                ['sku' => $product['sku']],
                $product + ['is_active' => true],
            );
        }
    }
}
