<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * B4: categories of a menu. location_menu gets its pivot model (LocationMenu) in code only.
     */
    public function up(): void
    {
        Schema::create('menu_categories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('menu_id');
            $table->string('image_path')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'menu_id', 'sort_order']);

            $table->foreign(['tenant_id', 'menu_id'])->references(['tenant_id', 'id'])->on('menus')->cascadeOnDelete();
        });

        Schema::create('menu_category_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_category_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['menu_category_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_category_translations');
        Schema::dropIfExists('menu_categories');
    }
};
