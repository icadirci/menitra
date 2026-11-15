<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Weekly opening hours and date-specific exceptions of a location. Both
     * reference the location with a composite FK, so a row can never point
     * at another tenant's branch.
     */
    public function up(): void
    {
        Schema::create('location_opening_hours', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('location_id');
            // ISO-8601: 1 = Monday … 7 = Sunday.
            $table->unsignedSmallInteger('day_of_week');
            $table->time('opens_at');
            // closes_at < opens_at: open past midnight.
            $table->time('closes_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['location_id', 'day_of_week']);

            $table->foreign(['tenant_id', 'location_id'])
                ->references(['tenant_id', 'id'])->on('locations')
                ->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE location_opening_hours ADD CONSTRAINT location_opening_hours_day_check CHECK (day_of_week BETWEEN 1 AND 7)');
        DB::statement('ALTER TABLE location_opening_hours ADD CONSTRAINT location_opening_hours_times_check CHECK (opens_at <> closes_at)');

        Schema::create('location_special_hours', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('location_id');
            $table->date('date');
            $table->boolean('is_closed')->default(false);
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->unique(['location_id', 'date', 'opens_at']);

            $table->foreign(['tenant_id', 'location_id'])
                ->references(['tenant_id', 'id'])->on('locations')
                ->cascadeOnDelete();
        });

        // A closed day has no times; an open one has both (and they differ).
        DB::statement('ALTER TABLE location_special_hours ADD CONSTRAINT location_special_hours_times_check CHECK (
            (is_closed AND opens_at IS NULL AND closes_at IS NULL)
            OR (NOT is_closed AND opens_at IS NOT NULL AND closes_at IS NOT NULL AND opens_at <> closes_at)
        )');
        // At most one "closed" row per date (NULL opens_at escapes the unique above).
        DB::statement('CREATE UNIQUE INDEX location_special_hours_closed_unique ON location_special_hours (location_id, date) WHERE is_closed');
    }

    public function down(): void
    {
        Schema::dropIfExists('location_special_hours');
        Schema::dropIfExists('location_opening_hours');
    }
};
