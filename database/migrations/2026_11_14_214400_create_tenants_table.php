<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('slug', 40)->unique();
            $table->string('status', 32)->default('active');
            $table->char('currency', 3)->default('TRY');
            $table->string('default_locale', 10)->default('tr');
            $table->string('theme', 32)->default('classic');
            $table->string('logo_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tenant_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('name');
            $table->string('tagline')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_translations');
        Schema::dropIfExists('tenants');
    }
};
