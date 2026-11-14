<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 64);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->string('theme', 32)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('whatsapp', 32)->nullable();
            $table->string('address_line')->nullable();
            $table->string('district')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->char('country_code', 2)->default('TR');
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->string('timezone', 64)->default('Europe/Istanbul');
            $table->string('cover_path')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->unique(['tenant_id', 'id']);
        });

        DB::statement('CREATE UNIQUE INDEX locations_tenant_id_default_unique ON locations (tenant_id) WHERE is_default');

        Schema::create('location_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['location_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_translations');
        Schema::dropIfExists('locations');
    }
};
