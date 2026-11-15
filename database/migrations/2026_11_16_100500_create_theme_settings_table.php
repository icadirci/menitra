<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-theme customisation of a tenant (docs/architecture/themes.md): one
     * row per tenant and theme, so switching back to a theme restores its
     * settings. `settings` is validated by MenuTheme::settingsRules(); a
     * missing row means the theme's defaults. No backfill: existing tenants
     * keep rendering with defaults until they save.
     */
    public function up(): void
    {
        Schema::create('theme_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('theme', 32);
            $table->jsonb('settings');
            $table->timestamps();

            $table->unique(['tenant_id', 'theme']);
            $table->unique(['tenant_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_settings');
    }
};
