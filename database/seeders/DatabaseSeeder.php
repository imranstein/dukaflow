<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Catalog\Database\Seeders\CatalogSeeder;
use App\Modules\Catalog\Enums\PriceListScope;
use App\Modules\Catalog\Models\PriceList;
use App\Modules\Catalog\Models\PriceListAssignment;
use App\Modules\Distribution\Database\Seeders\DistributionSeeder;
use App\Modules\Distribution\Enums\OutletType;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\SalesRep;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Builds the demo dataset.
 *
 * This seeder sits above the modules on purpose. Wiring a Catalog price list
 * to a Distribution customer is exactly the kind of cross-module knowledge the
 * modules themselves are not allowed to hold, so the composition happens here.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Published in the README and used by the live demo, so deliberately weak
     * and deliberately stable.
     *
     * @var list<array{name: string, email: string, role: UserRole}>
     */
    private const USERS = [
        ['name' => 'Selam Bekele', 'email' => 'admin@dukaflow.test', 'role' => UserRole::Admin],
        ['name' => 'Nardos Haile', 'email' => 'manager@dukaflow.test', 'role' => UserRole::Manager],
        ['name' => 'Dawit Tesfaye', 'email' => 'rep@dukaflow.test', 'role' => UserRole::Rep],
    ];

    public function run(): void
    {
        foreach (self::USERS as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                ['name' => $user['name'], 'role' => $user['role'], 'password' => 'password'],
            );
        }

        $this->call([
            CatalogSeeder::class,
            DistributionSeeder::class,
        ]);

        $this->giveWholesalersTheirPricing();
        $this->linkRepLogin();
    }

    /**
     * Puts every wholesale outlet on the wholesale price list, so the demo
     * shows the resolver actually choosing between two lists.
     */
    private function giveWholesalersTheirPricing(): void
    {
        $wholesaleList = PriceList::query()->where('code', CatalogSeeder::WHOLESALE_LIST)->first();

        if ($wholesaleList === null) {
            return;
        }

        $wholesalers = Customer::query()
            ->where('outlet_type', OutletType::Wholesaler)
            ->pluck('id');

        foreach ($wholesalers as $customerId) {
            PriceListAssignment::query()->updateOrCreate([
                'price_list_id' => $wholesaleList->id,
                'scope' => PriceListScope::Customer,
                'scope_id' => $customerId,
            ]);
        }
    }

    /** Gives the demo rep account a matching sales rep record. */
    private function linkRepLogin(): void
    {
        $repUser = User::query()->where('email', 'rep@dukaflow.test')->first();
        $salesRep = SalesRep::query()->where('code', 'REP-01')->first();

        if ($repUser !== null && $salesRep !== null) {
            $salesRep->update(['user_id' => $repUser->id]);
        }
    }
}
