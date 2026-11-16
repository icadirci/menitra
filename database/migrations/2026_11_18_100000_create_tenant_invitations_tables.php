<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Team invitations (launch plan Faz 7, model TenantInvitation) and the
     * branches they grant (tenant_invitation_location).
     *
     * - Only the SHA-256 hash of the token is stored (token_hash).
     * - One open invitation per tenant and email: a partial unique index on
     *   the rows that are neither accepted nor revoked (an expired open row is
     *   revoked by the service before a new one is created).
     * - The inviter is a membership of the same tenant (composite FK); when
     *   that membership is removed only invited_by_membership_id is cleared.
     */
    public function up(): void
    {
        Schema::create('tenant_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 32);
            $table->string('token_hash', 64)->unique();
            $table->string('locale', 8);
            $table->unsignedBigInteger('invited_by_membership_id')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('sent_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'email']);
        });

        // PostgreSQL 15: SET NULL on a column subset keeps tenant_id intact.
        DB::statement('ALTER TABLE tenant_invitations ADD CONSTRAINT tenant_invitations_inviter_foreign
            FOREIGN KEY (tenant_id, invited_by_membership_id) REFERENCES tenant_user (tenant_id, id)
            ON DELETE SET NULL (invited_by_membership_id)');

        DB::statement('CREATE UNIQUE INDEX tenant_invitations_open_unique ON tenant_invitations (tenant_id, email)
            WHERE accepted_at IS NULL AND revoked_at IS NULL');

        Schema::create('tenant_invitation_location', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('tenant_invitation_id');
            $table->unsignedBigInteger('location_id');
            $table->timestamps();

            $table->unique(['tenant_invitation_id', 'location_id']);
            $table->index(['tenant_id', 'location_id']);

            $table->foreign(['tenant_id', 'tenant_invitation_id'])
                ->references(['tenant_id', 'id'])->on('tenant_invitations')
                ->cascadeOnDelete();
            $table->foreign(['tenant_id', 'location_id'])
                ->references(['tenant_id', 'id'])->on('locations')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_invitation_location');
        Schema::dropIfExists('tenant_invitations');
    }
};
