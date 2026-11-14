<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Branch assignments: which locations a membership may act on.
     * Both composite FKs share tenant_id, so a membership of tenant A can
     * never be linked to a location of tenant B.
     */
    public function up(): void
    {
        Schema::create('location_user', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('tenant_user_id');
            $table->unsignedBigInteger('location_id');
            $table->timestamps();

            $table->unique(['tenant_user_id', 'location_id']);
            $table->index(['tenant_id', 'location_id']);

            $table->foreign(['tenant_id', 'tenant_user_id'])
                ->references(['tenant_id', 'id'])->on('tenant_user')
                ->cascadeOnDelete();
            $table->foreign(['tenant_id', 'location_id'])
                ->references(['tenant_id', 'id'])->on('locations')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_user');
    }
};
