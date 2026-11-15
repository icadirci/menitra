<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * B7: per-branch sold-out state and menu serving hours.
     */
    public function up(): void
    {
        Schema::create('location_item_states', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('menu_item_id');
            $table->boolean('is_sold_out')->default(false);
            // UTC; null with is_sold_out = indefinitely sold out.
            $table->timestamp('sold_out_until')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['location_id', 'menu_item_id']);

            $table->foreign(['tenant_id', 'location_id'])->references(['tenant_id', 'id'])->on('locations')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'menu_item_id'])->references(['tenant_id', 'id'])->on('menu_items')->cascadeOnDelete();
        });

        Schema::create('location_menu_hours', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('location_menu_id');
            $table->unsignedSmallInteger('day_of_week');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['location_menu_id', 'day_of_week']);

            $table->foreign(['tenant_id', 'location_menu_id'])->references(['tenant_id', 'id'])->on('location_menu')->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE location_menu_hours ADD CONSTRAINT location_menu_hours_check CHECK (day_of_week BETWEEN 1 AND 7 AND starts_at <> ends_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('location_menu_hours');
        Schema::dropIfExists('location_item_states');
    }
};
