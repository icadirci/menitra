<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // No WithoutModelEvents: DemoSeeder relies on model events (ULIDs, tenant_id, QR codes).
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([PlanSeeder::class, MarketingFaqSeeder::class]);

        // Showcase data for local development only (see DemoSeeder).
        if (! app()->isProduction()) {
            $this->call(DemoSeeder::class);
        }
    }
}
