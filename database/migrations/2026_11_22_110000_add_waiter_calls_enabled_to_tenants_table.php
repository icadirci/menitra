<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Waiter calls: the tenant-wide switch (off until the owner turns it on,
     * written only by WaiterCallService) and the waiter_call plan feature of
     * existing plans: Professional and Multi-Branch on, every other plan off
     * (decided 2026-11-18).
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->boolean('waiter_calls_enabled')->default(false);
        });

        $now = now();

        foreach (DB::table('plans')->get(['id', 'code']) as $plan) {
            DB::table('plan_limits')->insertOrIgnore([
                'plan_id' => $plan->id,
                'feature' => 'waiter_call',
                'limit_value' => in_array($plan->code, ['pro', 'multi-branch'], true) ? 1 : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('plan_limits')->where('feature', 'waiter_call')->delete();

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('waiter_calls_enabled');
        });
    }
};
