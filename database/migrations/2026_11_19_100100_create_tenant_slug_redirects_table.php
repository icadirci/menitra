<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Former slugs of a tenant (launch plan Faz 8): public menu URLs with an
     * old slug answer 301 to the tenant's current slug, and an old slug can't
     * be taken by another tenant. Written only by TenantSlugService.
     */
    public function up(): void
    {
        Schema::create('tenant_slug_redirects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 40)->unique();
            $table->timestamp('created_at')->useCurrent();

            $table->index('tenant_id');
        });

        DB::statement('ALTER TABLE tenant_slug_redirects ADD CONSTRAINT tenant_slug_redirects_slug_lowercase CHECK (slug = lower(slug))');
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_slug_redirects');
    }
};
