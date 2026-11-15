<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reserved for custom domains (later phase, docs/architecture/tenancy.md):
     * nothing reads or writes this table yet. Additive only.
     */
    public function up(): void
    {
        Schema::create('tenant_domains', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('domain', 253)->unique();
            $table->boolean('is_primary')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
        });

        // Domains are stored lowercase; at most one primary domain per tenant.
        DB::statement('ALTER TABLE tenant_domains ADD CONSTRAINT tenant_domains_domain_lowercase CHECK (domain = lower(domain))');
        DB::statement('CREATE UNIQUE INDEX tenant_domains_one_primary ON tenant_domains (tenant_id) WHERE is_primary');
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_domains');
    }
};
