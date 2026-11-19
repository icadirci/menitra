<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables of a branch (table service, waiter calls). Every table has at
     * most one QR code (qr_codes.location_table_id); the code's branch must be
     * the table's branch, enforced by the composite FK
     * (tenant_id, location_id, location_table_id).
     *
     * Existing plans get a max_tables limit: Professional 30, Multi-Branch
     * unlimited, every other plan 0 (decided 2026-11-18).
     */
    public function up(): void
    {
        Schema::create('location_tables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('location_id');
            $table->string('name', 64);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'location_id', 'id']);
            $table->unique(['tenant_id', 'location_id', 'name']);

            $table->foreign(['tenant_id', 'location_id'])->references(['tenant_id', 'id'])->on('locations')->cascadeOnDelete();
        });

        Schema::table('qr_codes', function (Blueprint $table): void {
            $table->unsignedBigInteger('location_table_id')->nullable()->after('menu_id');

            // One code per table; NULLs (branch codes) don't collide.
            $table->unique('location_table_id');

            // MATCH SIMPLE: skipped while location_table_id is null.
            $table->foreign(['tenant_id', 'location_id', 'location_table_id'])
                ->references(['tenant_id', 'location_id', 'id'])
                ->on('location_tables')
                ->cascadeOnDelete();
        });

        $now = now();

        foreach (DB::table('plans')->get(['id', 'code']) as $plan) {
            DB::table('plan_limits')->insertOrIgnore([
                'plan_id' => $plan->id,
                'feature' => 'max_tables',
                'limit_value' => match ($plan->code) {
                    'pro' => 30,
                    'multi-branch' => null,
                    default => 0,
                },
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('plan_limits')->where('feature', 'max_tables')->delete();

        Schema::table('qr_codes', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id', 'location_id', 'location_table_id']);
            $table->dropUnique(['location_table_id']);
            $table->dropColumn('location_table_id');
        });

        Schema::dropIfExists('location_tables');
    }
};
