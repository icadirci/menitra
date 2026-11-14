<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menus belong to the tenant and are attached to locations through
     * location_menu. Categories, items etc. come with the menu content phase.
     */
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 64);
            $table->string('type', 32);
            $table->boolean('is_published')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('menu_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['menu_id', 'locale']);
        });

        Schema::create('location_menu', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('menu_id');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'location_id', 'menu_id']);
            // Target of location_menu_hours later.
            $table->unique(['tenant_id', 'id']);

            $table->foreign(['tenant_id', 'location_id'])
                ->references(['tenant_id', 'id'])->on('locations')
                ->cascadeOnDelete();
            $table->foreign(['tenant_id', 'menu_id'])
                ->references(['tenant_id', 'id'])->on('menus')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_menu');
        Schema::dropIfExists('menu_translations');
        Schema::dropIfExists('menus');
    }
};
