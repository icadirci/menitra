<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only log of state-changing admin actions (launch plan Faz 8,
     * .claude/rules/security.md § Admin platform management). A trigger
     * refuses UPDATE and DELETE, so rows can't be changed from the application
     * either. Users and tenants are never hard-deleted, so the FKs restrict.
     * `OR REPLACE`: migrate:fresh drops tables but not functions.
     */
    public function up(): void
    {
        Schema::create('admin_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users');
            $table->string('action', 64);
            $table->string('subject_type', 32)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants');
            $table->jsonb('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['subject_type', 'subject_id']);
            $table->index('tenant_id');
            $table->index('actor_user_id');
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION admin_audit_events_append_only() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'admin_audit_events is append-only';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER admin_audit_events_no_update_or_delete
                BEFORE UPDATE OR DELETE ON admin_audit_events
                FOR EACH ROW EXECUTE FUNCTION admin_audit_events_append_only();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_events');
        DB::unprepared('DROP FUNCTION IF EXISTS admin_audit_events_append_only()');
    }
};
