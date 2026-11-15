<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Platform contact details shown on the marketing site (footer, contact
     * page), edited in /admin by super admins. A single row (id = 1, enforced
     * by a check constraint); not tenant-owned. Translatable text (address,
     * support hours) lives in site_setting_translations.
     */
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('contact_email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->boolean('phone_has_whatsapp')->default(false);
            $table->string('instagram_url')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('x_url')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('youtube_url')->nullable();
            $table->string('tiktok_url')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE site_settings ADD CONSTRAINT site_settings_single_row CHECK (id = 1)');

        Schema::create('site_setting_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_setting_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('address', 500)->nullable();
            $table->string('support_hours')->nullable();
            $table->timestamps();

            $table->unique(['site_setting_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_setting_translations');
        Schema::dropIfExists('site_settings');
    }
};
