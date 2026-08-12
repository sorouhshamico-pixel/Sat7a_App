<?php

namespace Database\Seeders;

use App\Domain\Maps\Models\City;
use Illuminate\Database\Seeder;

/**
 * Safe in every environment, including production — only creates/updates
 * the city catalog. Riyadh is the only launch city; the others are
 * inactive placeholders so expansion (spec §152) is a data change, not a
 * domain-logic rewrite.
 */
class CitySeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
            ['slug' => 'riyadh', 'name' => 'Riyadh', 'name_ar' => 'الرياض', 'is_active' => true],
            ['slug' => 'jeddah', 'name' => 'Jeddah', 'name_ar' => 'جدة', 'is_active' => false],
            ['slug' => 'dammam', 'name' => 'Dammam', 'name_ar' => 'الدمام', 'is_active' => false],
            ['slug' => 'makkah', 'name' => 'Makkah', 'name_ar' => 'مكة المكرمة', 'is_active' => false],
            ['slug' => 'madinah', 'name' => 'Madinah', 'name_ar' => 'المدينة المنورة', 'is_active' => false],
            ['slug' => 'taif', 'name' => 'Taif', 'name_ar' => 'الطائف', 'is_active' => false],
        ];

        foreach ($cities as $city) {
            City::query()->updateOrCreate(['slug' => $city['slug']], $city);
        }
    }
}
