<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Reference data: safe for every environment.
        $this->call([
            RolesAndPermissionsSeeder::class,
            PositionSeeder::class,
        ]);

        // Fictitious demo data: only when explicitly in demo mode outside production.
        if (config('nimcos.demo_mode') && ! app()->isProduction()) {
            $this->call(DemoSeeder::class);
        }
    }
}
