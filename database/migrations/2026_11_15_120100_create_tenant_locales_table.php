<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enabled locales of a tenant and their order. The default locale is
     * tenants.default_locale (single source of truth), so there is no
     * is_default column here.
     *
     * Backfill: every existing tenant (soft-deleted ones included) gets a row
     * for its current default_locale. Insert-only; tenants are not modified.
     */
    public function up(): void
    {
        Schema::create('tenant_locales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'locale']);
            $table->unique(['tenant_id', 'id']);
        });

        DB::statement(<<<'SQL'
            INSERT INTO tenant_locales (tenant_id, locale, sort_order, created_at, updated_at)
            SELECT id, default_locale, 0, NOW(), NOW() FROM tenants
            ON CONFLICT (tenant_id, locale) DO NOTHING
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_locales');
    }
};
