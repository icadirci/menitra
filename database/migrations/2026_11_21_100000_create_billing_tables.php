<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manual period payments through iyzico (docs/architecture/subscriptions.md § Payments).
 *
 * - tenant_billing_profiles: the owner's invoice details, sent to iyzico as
 *   buyer / billing address and snapshotted on every payment. tax_id is
 *   stored encrypted (TCKN or VKN).
 * - payments: one row per checkout. Amounts in kuruş, computed on the server;
 *   billing_details is an encrypted snapshot for the receipt.
 * - billing_reminders: one row per sent reminder mail, so the daily command
 *   never sends the same reminder twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_billing_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('name', 160);
            $table->string('tax_office', 120)->nullable();
            $table->text('tax_id');
            $table->string('email');
            $table->string('phone', 32)->nullable();
            $table->string('address', 500);
            $table->string('city', 80);
            $table->string('district', 80)->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_price_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('interval', 16);
            $table->string('kind', 32)->nullable();
            $table->string('status', 16);
            $table->char('currency', 3);
            $table->bigInteger('base_amount');
            $table->unsignedInteger('extra_locations')->default(0);
            $table->bigInteger('extra_location_amount')->nullable();
            $table->bigInteger('amount');
            $table->string('provider', 32);
            $table->string('provider_token')->nullable()->unique();
            $table->string('provider_payment_id')->nullable()->unique();
            $table->timestamp('period_starts_at')->nullable();
            $table->timestamp('period_ends_at')->nullable();
            $table->string('card_association', 32)->nullable();
            $table->string('card_family', 32)->nullable();
            $table->string('card_last_four', 4)->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->string('failure_message')->nullable();
            $table->text('billing_details');
            $table->string('receipt_number', 32)->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->foreign(['tenant_id', 'subscription_id'])
                ->references(['tenant_id', 'id'])->on('subscriptions')
                ->cascadeOnDelete();
        });

        Schema::create('billing_reminders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('subscription_id');
            $table->string('type', 32);
            $table->timestamp('boundary_at');
            $table->unsignedSmallInteger('days_before');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['subscription_id', 'type', 'boundary_at', 'days_before']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'subscription_id'])
                ->references(['tenant_id', 'id'])->on('subscriptions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_reminders');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('tenant_billing_profiles');
    }
};
