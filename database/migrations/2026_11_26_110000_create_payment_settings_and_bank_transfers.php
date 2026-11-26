<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payment methods chosen in /admin (docs/architecture/subscriptions.md
 * § Payment methods): card payments through iyzico and bank transfers
 * (havale/EFT) confirmed manually by a super admin.
 *
 * - payment_settings: platform data, a single row (id = 1). The bank account
 *   shown to owners; the transfer instructions are translatable
 *   (payment_setting_translations).
 * - payments: a bank transfer has no provider token; it carries a short
 *   reference the owner writes in the transfer description and the time the
 *   owner reported the transfer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('card_enabled')->default(true);
            $table->boolean('bank_transfer_enabled')->default(false);
            $table->string('bank_account_holder', 160)->nullable();
            $table->string('bank_name', 120)->nullable();
            $table->string('bank_iban', 34)->nullable();
            $table->string('notification_email')->nullable();
            $table->timestamps();
        });
        DB::statement('ALTER TABLE payment_settings ADD CONSTRAINT payment_settings_single_row CHECK (id = 1)');

        Schema::create('payment_setting_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_setting_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->text('bank_transfer_instructions')->nullable();
            $table->timestamps();

            $table->unique(['payment_setting_id', 'locale']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->string('transfer_reference', 16)->nullable()->unique()->after('provider_payment_id');
            $table->timestamp('transfer_notified_at')->nullable()->after('transfer_reference');
        });
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_bank_transfer CHECK ((provider = 'bank_transfer' AND transfer_reference IS NOT NULL AND provider_token IS NULL) OR (provider <> 'bank_transfer' AND transfer_reference IS NULL AND transfer_notified_at IS NULL))");
    }

    /**
     * Bank transfer payments can't be told apart without these columns, so
     * the rollback fails (check constraint) while there are any.
     */
    public function down(): void
    {
        if (DB::table('payments')->where('provider', 'bank_transfer')->exists()) {
            throw new RuntimeException('Bank transfer payments exist; they are never dropped silently.');
        }

        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_bank_transfer');

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['transfer_reference']);
            $table->dropColumn(['transfer_reference', 'transfer_notified_at']);
        });

        Schema::dropIfExists('payment_setting_translations');
        Schema::dropIfExists('payment_settings');
    }
};
