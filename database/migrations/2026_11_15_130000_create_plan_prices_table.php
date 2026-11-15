<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Plan prices (one row per plan, interval and currency; amount in kuruş),
     * the "most popular" flag shown on the pricing pages, and the nullable
     * subscriptions.plan_price_id announced in docs/architecture/database.md.
     * Additive only: no existing data is changed.
     */
    public function up(): void
    {
        Schema::create('plan_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('interval', 16);
            $table->bigInteger('amount');
            $table->char('currency', 3)->default('TRY');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['plan_id', 'interval', 'currency']);
        });

        DB::statement('ALTER TABLE plan_prices ADD CONSTRAINT plan_prices_amount_check CHECK (amount >= 0)');

        Schema::table('plans', function (Blueprint $table): void {
            $table->boolean('is_featured')->default(false)->after('is_public');
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            // Trials have no price; a price row in use can't be deleted.
            $table->foreignId('plan_price_id')->nullable()->after('plan_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('plan_price_id');
        });

        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn('is_featured');
        });

        Schema::dropIfExists('plan_prices');
    }
};
