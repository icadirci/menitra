<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * B5: items, their translations, allergens and dietary labels.
     */
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('menu_category_id');
            $table->bigInteger('price')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_available')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_popular')->default(false);
            $table->unsignedInteger('calories')->nullable();
            $table->unsignedSmallInteger('preparation_minutes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'menu_category_id', 'sort_order']);

            $table->foreign(['tenant_id', 'menu_category_id'])->references(['tenant_id', 'id'])->on('menu_categories')->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE menu_items ADD CONSTRAINT menu_items_price_check CHECK (price IS NULL OR price >= 0)');
        DB::statement('CREATE INDEX menu_items_tenant_featured_index ON menu_items (tenant_id) WHERE is_featured');

        Schema::create('menu_item_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['menu_item_id', 'locale']);
        });

        foreach (['menu_item_allergens' => 'allergen', 'menu_item_dietary_labels' => 'label'] as $name => $column) {
            Schema::create($name, function (Blueprint $table) use ($column): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('menu_item_id');
                $table->string($column, 32);
                $table->timestamps();

                $table->unique(['tenant_id', 'id']);
                $table->unique(['menu_item_id', $column]);

                $table->foreign(['tenant_id', 'menu_item_id'])->references(['tenant_id', 'id'])->on('menu_items')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_dietary_labels');
        Schema::dropIfExists('menu_item_allergens');
        Schema::dropIfExists('menu_item_translations');
        Schema::dropIfExists('menu_items');
    }
};
