<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stable QR codes (/q/{code}). The table comes with the public menu
     * infrastructure (Phase 6); the panel module (images, CRUD) is B8.
     */
    public function up(): void
    {
        Schema::create('qr_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 16)->unique();
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('menu_id')->nullable();
            $table->string('label')->nullable();
            $table->string('table_label', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);

            $table->foreign(['tenant_id', 'location_id'])->references(['tenant_id', 'id'])->on('locations')->cascadeOnDelete();
            // MATCH SIMPLE (default): skipped while menu_id is null.
            $table->foreign(['tenant_id', 'menu_id'])->references(['tenant_id', 'id'])->on('menus')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_codes');
    }
};
