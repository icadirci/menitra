<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `plan_price_id` is added together with the plan_prices table.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 32);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_starts_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('provider', 32)->nullable();
            $table->string('provider_reference')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['status', 'current_period_ends_at']);
        });

        // At most one live subscription per tenant.
        DB::statement('CREATE UNIQUE INDEX subscriptions_tenant_id_live_unique ON subscriptions (tenant_id) WHERE ended_at IS NULL');

        Schema::create('subscription_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('subscription_id');
            $table->string('type', 32);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['subscription_id', 'occurred_at']);
            $table->foreign(['tenant_id', 'subscription_id'])
                ->references(['tenant_id', 'id'])->on('subscriptions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_events');
        Schema::dropIfExists('subscriptions');
    }
};
