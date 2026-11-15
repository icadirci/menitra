<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-plan feature limits (App\Enums\PlanFeature). limit_value: null =
     * unlimited, 0/1 for boolean features; a missing row = not included.
     * New table only; no existing data is touched.
     */
    public function up(): void
    {
        Schema::create('plan_limits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('feature', 64);
            $table->integer('limit_value')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'feature']);
        });

        DB::statement('ALTER TABLE plan_limits ADD CONSTRAINT plan_limits_limit_value_check CHECK (limit_value IS NULL OR limit_value >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_limits');
    }
};
