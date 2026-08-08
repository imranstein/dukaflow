<?php

declare(strict_types=1);

namespace App\Modules\Distribution\Database\Seeders;

use App\Modules\Distribution\Enums\DayOfWeek;
use App\Modules\Distribution\Enums\OutletType;
use App\Modules\Distribution\Models\Customer;
use App\Modules\Distribution\Models\Route;
use App\Modules\Distribution\Models\SalesRep;
use App\Modules\Distribution\Models\VisitSchedule;
use Illuminate\Database\Seeder;

/**
 * Demo data is fixed rather than generated. A demo that looks different on
 * every reset is hard to write documentation or screenshots against.
 */
class DistributionSeeder extends Seeder
{
    /** @var list<array{code: string, name: string, phone: string}> */
    private const REPS = [
        ['code' => 'REP-01', 'name' => 'Dawit Tesfaye', 'phone' => '+251911234567'],
        ['code' => 'REP-02', 'name' => 'Meron Abebe', 'phone' => '+251911234568'],
        ['code' => 'REP-03', 'name' => 'Yohannes Girma', 'phone' => '+251911234569'],
        ['code' => 'REP-04', 'name' => 'Tigist Mulugeta', 'phone' => '+251911234570'],
    ];

    /** @var list<array{code: string, name: string, description: string, rep: string}> */
    private const ROUTES = [
        ['code' => 'RT-01', 'name' => 'Bole beat', 'description' => 'Bole Medhanialem through to Gerji.', 'rep' => 'REP-01'],
        ['code' => 'RT-02', 'name' => 'Merkato beat', 'description' => 'Merkato and the wholesale market.', 'rep' => 'REP-02'],
        ['code' => 'RT-03', 'name' => 'Piassa beat', 'description' => 'Piassa, Arat Kilo and Kazanchis.', 'rep' => 'REP-03'],
        ['code' => 'RT-04', 'name' => 'Ayat beat', 'description' => 'Ayat, Megenagna and CMC.', 'rep' => 'REP-04'],
    ];

    /**
     * @var list<array{code: string, name: string, type: OutletType, owner: string, area: string, lat: string, lng: string, route: string, days: list<int>}>
     */
    private const OUTLETS = [
        ['code' => 'CUS-0001', 'name' => 'Medhanialem Mini Market', 'type' => OutletType::Supermarket, 'owner' => 'Almaz Bekele', 'area' => 'Bole Medhanialem', 'lat' => '8.9950000', 'lng' => '38.7870000', 'route' => 'RT-01', 'days' => [1, 4]],
        ['code' => 'CUS-0002', 'name' => 'Rwanda Street Kiosk', 'type' => OutletType::Kiosk, 'owner' => 'Getachew Alemu', 'area' => 'Bole', 'lat' => '8.9910000', 'lng' => '38.7820000', 'route' => 'RT-01', 'days' => [1]],
        ['code' => 'CUS-0003', 'name' => 'Sunrise Cafe', 'type' => OutletType::Restaurant, 'owner' => 'Sara Haile', 'area' => 'Bole', 'lat' => '8.9880000', 'lng' => '38.7890000', 'route' => 'RT-01', 'days' => [1]],
        ['code' => 'CUS-0004', 'name' => 'Gerji Corner Shop', 'type' => OutletType::Kiosk, 'owner' => 'Mulu Teshome', 'area' => 'Gerji', 'lat' => '9.0050000', 'lng' => '38.8100000', 'route' => 'RT-01', 'days' => [4]],
        ['code' => 'CUS-0005', 'name' => 'Bole Grand Hotel', 'type' => OutletType::Hotel, 'owner' => 'Kidus Mengistu', 'area' => 'Bole', 'lat' => '8.9820000', 'lng' => '38.7990000', 'route' => 'RT-01', 'days' => [4]],
        ['code' => 'CUS-0006', 'name' => 'Atikilt Tera Wholesale', 'type' => OutletType::Wholesaler, 'owner' => 'Genet Fikru', 'area' => 'Merkato', 'lat' => '9.0330000', 'lng' => '38.7400000', 'route' => 'RT-02', 'days' => [2, 5]],
        ['code' => 'CUS-0007', 'name' => 'Dubai Tera Traders', 'type' => OutletType::Wholesaler, 'owner' => 'Samuel Worku', 'area' => 'Merkato', 'lat' => '9.0350000', 'lng' => '38.7380000', 'route' => 'RT-02', 'days' => [2, 5]],
        ['code' => 'CUS-0008', 'name' => 'Shema Retail', 'type' => OutletType::Kiosk, 'owner' => 'Aster Gebre', 'area' => 'Merkato', 'lat' => '9.0310000', 'lng' => '38.7430000', 'route' => 'RT-02', 'days' => [2]],
        ['code' => 'CUS-0009', 'name' => 'Addis Ketema Supermarket', 'type' => OutletType::Supermarket, 'owner' => 'Bereket Tilahun', 'area' => 'Addis Ketema', 'lat' => '9.0380000', 'lng' => '38.7350000', 'route' => 'RT-02', 'days' => [5]],
        ['code' => 'CUS-0010', 'name' => 'Piassa Pharmacy', 'type' => OutletType::Pharmacy, 'owner' => 'Hiwot Ayele', 'area' => 'Piassa', 'lat' => '9.0350000', 'lng' => '38.7520000', 'route' => 'RT-03', 'days' => [3]],
        ['code' => 'CUS-0011', 'name' => 'Taitu Street Grocery', 'type' => OutletType::Kiosk, 'owner' => 'Fikadu Nega', 'area' => 'Piassa', 'lat' => '9.0340000', 'lng' => '38.7500000', 'route' => 'RT-03', 'days' => [3, 6]],
        ['code' => 'CUS-0012', 'name' => 'Arat Kilo Mini Mart', 'type' => OutletType::Supermarket, 'owner' => 'Selamawit Girma', 'area' => 'Arat Kilo', 'lat' => '9.0370000', 'lng' => '38.7620000', 'route' => 'RT-03', 'days' => [3]],
        ['code' => 'CUS-0013', 'name' => 'Kazanchis Restaurant', 'type' => OutletType::Restaurant, 'owner' => 'Tesfaye Alemu', 'area' => 'Kazanchis', 'lat' => '9.0170000', 'lng' => '38.7660000', 'route' => 'RT-03', 'days' => [6]],
        ['code' => 'CUS-0014', 'name' => 'Ayat Zone 3 Shop', 'type' => OutletType::Kiosk, 'owner' => 'Rahel Desta', 'area' => 'Ayat', 'lat' => '9.0250000', 'lng' => '38.8600000', 'route' => 'RT-04', 'days' => [2]],
        ['code' => 'CUS-0015', 'name' => 'Megenagna Supermarket', 'type' => OutletType::Supermarket, 'owner' => 'Abel Tadesse', 'area' => 'Megenagna', 'lat' => '9.0200000', 'lng' => '38.8000000', 'route' => 'RT-04', 'days' => [2, 5]],
        ['code' => 'CUS-0016', 'name' => 'CMC Corner Store', 'type' => OutletType::Kiosk, 'owner' => 'Bethlehem Negash', 'area' => 'CMC', 'lat' => '9.0300000', 'lng' => '38.8300000', 'route' => 'RT-04', 'days' => [5]],
        ['code' => 'CUS-0017', 'name' => 'Summit Hotel', 'type' => OutletType::Hotel, 'owner' => 'Henok Assefa', 'area' => 'Summit', 'lat' => '9.0100000', 'lng' => '38.8500000', 'route' => 'RT-04', 'days' => [5]],
        ['code' => 'CUS-0018', 'name' => 'Lebu Roadside Kiosk', 'type' => OutletType::Kiosk, 'owner' => 'Marta Solomon', 'area' => 'Lebu', 'lat' => '8.9500000', 'lng' => '38.7100000', 'route' => 'RT-02', 'days' => [5]],
    ];

    public function run(): void
    {
        $reps = [];

        foreach (self::REPS as $rep) {
            $reps[$rep['code']] = SalesRep::query()->updateOrCreate(
                ['code' => $rep['code']],
                ['name' => $rep['name'], 'phone' => $rep['phone'], 'is_active' => true],
            )->id;
        }

        $routes = [];

        foreach (self::ROUTES as $route) {
            $routes[$route['code']] = Route::query()->updateOrCreate(
                ['code' => $route['code']],
                [
                    'name' => $route['name'],
                    'description' => $route['description'],
                    'sales_rep_id' => $reps[$route['rep']],
                    'is_active' => true,
                ],
            )->id;
        }

        foreach (self::OUTLETS as $index => $outlet) {
            $customer = Customer::query()->updateOrCreate(
                ['code' => $outlet['code']],
                [
                    'name' => $outlet['name'],
                    'outlet_type' => $outlet['type'],
                    'owner_name' => $outlet['owner'],
                    'phone' => '+2519'.str_pad((string) (11000000 + $index), 8, '0', STR_PAD_LEFT),
                    'address' => $outlet['area'].', Addis Ababa',
                    'latitude' => $outlet['lat'],
                    'longitude' => $outlet['lng'],
                    'route_id' => $routes[$outlet['route']],
                    'is_active' => true,
                ],
            );

            foreach ($outlet['days'] as $position => $day) {
                VisitSchedule::query()->updateOrCreate(
                    ['customer_id' => $customer->id, 'day_of_week' => DayOfWeek::from($day)],
                    ['sequence' => $position + 1, 'is_active' => true],
                );
            }
        }
    }
}
