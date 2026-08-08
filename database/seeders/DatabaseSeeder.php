<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Catalog\Database\Seeders\CatalogSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seeds the demo dataset. The credentials below are published in the
     * README and used by the live demo, so they are deliberately weak and
     * deliberately stable.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@dukaflow.test'],
            ['name' => 'Selam Bekele', 'password' => 'password'],
        );

        $this->call([
            CatalogSeeder::class,
        ]);
    }
}
