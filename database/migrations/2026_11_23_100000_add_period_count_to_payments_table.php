<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-year payments (docs/architecture/subscriptions.md § Payments): how
 * many periods of the payment's interval one payment covers (yearly: 1 to
 * billing.max_years_per_payment; monthly and upgrades: always 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->unsignedSmallInteger('period_count')->default(1)->after('interval');
        });

        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_period_count_positive CHECK (period_count >= 1)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_period_count_positive');

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('period_count');
        });
    }
};
