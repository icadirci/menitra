<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * B6: item variants and reusable extra groups.
     */
    public function up(): void
    {
        Schema::create('menu_item_variants', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('menu_item_id');
            $table->bigInteger('price');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_available')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['menu_item_id', 'sort_order']);

            $table->foreign(['tenant_id', 'menu_item_id'])->references(['tenant_id', 'id'])->on('menu_items')->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE menu_item_variants ADD CONSTRAINT menu_item_variants_price_check CHECK (price >= 0)');
        // At most one default variant per item.
        DB::statement('CREATE UNIQUE INDEX menu_item_variants_default_unique ON menu_item_variants (menu_item_id) WHERE is_default');

        Schema::create('menu_item_variant_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_item_variant_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('name');
            $table->timestamps();

            $table->unique(['menu_item_variant_id', 'locale']);
        });

        Schema::create('extra_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('selection_type', 16);
            $table->unsignedSmallInteger('min_select')->default(0);
            $table->unsignedSmallInteger('max_select')->nullable();
            $table->boolean('is_required')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
        });

        DB::statement('ALTER TABLE extra_groups ADD CONSTRAINT extra_groups_select_check CHECK (max_select IS NULL OR max_select >= min_select)');

        Schema::create('extra_group_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('extra_group_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('name');
            $table->timestamps();

            $table->unique(['extra_group_id', 'locale']);
        });

        Schema::create('extras', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('extra_group_id');
            $table->bigInteger('price_delta')->default(0);
            $table->boolean('is_available')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['extra_group_id', 'sort_order']);

            $table->foreign(['tenant_id', 'extra_group_id'])->references(['tenant_id', 'id'])->on('extra_groups')->cascadeOnDelete();
        });

        Schema::create('extra_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('extra_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('name');
            $table->timestamps();

            $table->unique(['extra_id', 'locale']);
        });

        Schema::create('menu_item_extra_group', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('menu_item_id');
            $table->unsignedBigInteger('extra_group_id');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['menu_item_id', 'extra_group_id']);

            $table->foreign(['tenant_id', 'menu_item_id'])->references(['tenant_id', 'id'])->on('menu_items')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'extra_group_id'])->references(['tenant_id', 'id'])->on('extra_groups')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_extra_group');
        Schema::dropIfExists('extra_translations');
        Schema::dropIfExists('extras');
        Schema::dropIfExists('extra_group_translations');
        Schema::dropIfExists('extra_groups');
        Schema::dropIfExists('menu_item_variant_translations');
        Schema::dropIfExists('menu_item_variants');
    }
};
