<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-branch pricing: plans.included_locations (branches covered by the
     * base price) and plan_prices.extra_location_amount (kuruş per additional
     * branch and billing interval). Both nullable: null = no per-branch
     * pricing. Additive only: no existing data is changed.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->unsignedSmallInteger('included_locations')->nullable()->after('trial_days');
        });

        Schema::table('plan_prices', function (Blueprint $table): void {
            $table->bigInteger('extra_location_amount')->nullable()->after('amount');
        });

        DB::statement('ALTER TABLE plan_prices ADD CONSTRAINT plan_prices_extra_location_amount_check CHECK (extra_location_amount IS NULL OR extra_location_amount >= 0)');
    }

    public function down(): void
    {
        Schema::table('plan_prices', function (Blueprint $table): void {
            $table->dropColumn('extra_location_amount');
        });

        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn('included_locations');
        });
    }
};
