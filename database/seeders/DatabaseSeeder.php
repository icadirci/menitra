<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // No WithoutModelEvents: DemoSeeder / ShowcaseSeeder rely on model events (ULIDs, tenant_id, QR codes).
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([PlanSeeder::class, MarketingFaqSeeder::class]);

        // Demo users and teams for local development only (see DemoSeeder; it
        // also runs ShowcaseSeeder). In production ShowcaseSeeder runs on its own.
        if (! app()->isProduction()) {
            $this->call(DemoSeeder::class);
        }
    }
}
