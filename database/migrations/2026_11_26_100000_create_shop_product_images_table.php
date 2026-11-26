<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additional images of a shop product (platform data). The cover stays in
 * shop_products.image_path; these follow it in sort order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_product_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['shop_product_id', 'path']);
            $table->index(['shop_product_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_product_images');
    }
};
