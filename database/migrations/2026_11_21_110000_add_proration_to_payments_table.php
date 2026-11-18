<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mid-period upgrades (docs/architecture/subscriptions.md § Upgrades): the
 * unused value of the current plan that is credited against the new plan's
 * price for the remaining days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->bigInteger('credit_amount')->nullable()->after('amount');
            $table->unsignedSmallInteger('prorated_days')->nullable()->after('credit_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn(['credit_amount', 'prorated_days']);
        });
    }
};
