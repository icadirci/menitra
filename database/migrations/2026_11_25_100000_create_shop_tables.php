<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * QR stand shop (docs/architecture/shop.md).
 *
 * - shop_settings, shop_products (+ translations): platform data, /admin.
 * - shop_cart_items, shop_orders, shop_order_items: tenant-owned. An order
 *   line is a product printed with one QR code; name, price, code and label
 *   are snapshots. delivery_address is encrypted.
 * - payments: a payment is either for a plan period or for one shop order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('is_enabled')->default(false);
            $table->bigInteger('shipping_amount')->default(0);
            $table->bigInteger('free_shipping_from')->nullable();
            $table->unsignedSmallInteger('min_delivery_days')->nullable();
            $table->unsignedSmallInteger('max_delivery_days')->nullable();
            $table->string('notification_email')->nullable();
            $table->timestamps();
        });
        DB::statement('ALTER TABLE shop_settings ADD CONSTRAINT shop_settings_single_row CHECK (id = 1)');
        DB::statement('ALTER TABLE shop_settings ADD CONSTRAINT shop_settings_amounts CHECK (shipping_amount >= 0 AND (free_shipping_from IS NULL OR free_shipping_from >= 0))');

        Schema::create('shop_products', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->bigInteger('price_amount');
            $table->char('currency', 3)->default('TRY');
            $table->string('image_path')->nullable();
            $table->boolean('is_personalized')->default(true);
            // null = not tracked. May go below zero: paid orders are always recorded.
            $table->integer('stock_quantity')->nullable();
            $table->unsignedInteger('max_per_order')->default(200);
            $table->boolean('is_active')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
        DB::statement('ALTER TABLE shop_products ADD CONSTRAINT shop_products_values CHECK (price_amount > 0 AND max_per_order > 0)');

        Schema::create('shop_product_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_product_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['shop_product_id', 'locale']);
        });

        Schema::create('shop_cart_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_product_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('qr_code_id')->nullable();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'qr_code_id'])->references(['tenant_id', 'id'])->on('qr_codes')->cascadeOnDelete();
        });
        DB::statement('ALTER TABLE shop_cart_items ADD CONSTRAINT shop_cart_items_quantity CHECK (quantity > 0)');
        DB::statement('ALTER TABLE shop_cart_items ADD CONSTRAINT shop_cart_items_line_unique UNIQUE NULLS NOT DISTINCT (tenant_id, shop_product_id, qr_code_id)');

        Schema::create('shop_orders', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_number', 32)->nullable()->unique();
            $table->string('status', 24);
            $table->char('currency', 3);
            $table->bigInteger('subtotal_amount');
            $table->bigInteger('shipping_amount');
            $table->bigInteger('total_amount');
            $table->string('contact_email');
            $table->text('delivery_address');
            $table->string('customer_note', 500)->nullable();
            $table->string('carrier', 60)->nullable();
            $table->string('tracking_number', 60)->nullable();
            $table->string('tracking_url')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
        DB::statement('ALTER TABLE shop_orders ADD CONSTRAINT shop_orders_total CHECK (total_amount = subtotal_amount + shipping_amount AND total_amount > 0 AND shipping_amount >= 0)');

        Schema::create('shop_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('shop_order_id');
            $table->foreignId('shop_product_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('qr_code_id')->nullable();
            $table->string('product_name', 120);
            $table->string('qr_code', 16)->nullable();
            $table->string('qr_label')->nullable();
            $table->bigInteger('unit_amount');
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'shop_order_id'])->references(['tenant_id', 'id'])->on('shop_orders')->cascadeOnDelete();
        });
        DB::statement('ALTER TABLE shop_order_items ADD CONSTRAINT shop_order_items_quantity CHECK (quantity > 0)');
        // The printed code stays readable in the snapshot after the code is deleted.
        DB::statement('ALTER TABLE shop_order_items ADD CONSTRAINT shop_order_items_qr_code_foreign FOREIGN KEY (tenant_id, qr_code_id) REFERENCES qr_codes (tenant_id, id) ON DELETE SET NULL (qr_code_id)');

        Schema::table('payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('plan_id')->nullable()->change();
            $table->unsignedBigInteger('plan_price_id')->nullable()->change();
            $table->string('interval', 16)->nullable()->change();
            $table->unsignedBigInteger('shop_order_id')->nullable()->unique()->after('subscription_id');
            $table->foreign(['tenant_id', 'shop_order_id'])->references(['tenant_id', 'id'])->on('shop_orders')->cascadeOnDelete();
        });
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_purpose CHECK ((shop_order_id IS NULL AND plan_id IS NOT NULL AND plan_price_id IS NOT NULL AND "interval" IS NOT NULL) OR (shop_order_id IS NOT NULL AND plan_id IS NULL AND plan_price_id IS NULL AND "interval" IS NULL))');
    }

    /**
     * Payments of shop orders can't exist without this schema, so the rollback
     * fails (NOT NULL) while there are any; they are never deleted silently.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_purpose');

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id', 'shop_order_id']);
            $table->unsignedBigInteger('plan_id')->nullable(false)->change();
            $table->unsignedBigInteger('plan_price_id')->nullable(false)->change();
            $table->string('interval', 16)->nullable(false)->change();
            $table->dropColumn('shop_order_id');
        });

        Schema::dropIfExists('shop_order_items');
        Schema::dropIfExists('shop_orders');
        Schema::dropIfExists('shop_cart_items');
        Schema::dropIfExists('shop_product_translations');
        Schema::dropIfExists('shop_products');
        Schema::dropIfExists('shop_settings');
    }
};
