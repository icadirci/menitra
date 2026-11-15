<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SocialNetwork;
use App\Models\Concerns\HasTranslations;
use App\Models\Translations\SiteSettingTranslation;
use Illuminate\Database\Eloquent\Model;

/**
 * Platform contact details for the marketing site (not tenant-owned): a single
 * row, id = 1 (check constraint). Edited in /admin by super admins
 * (SiteSettingPolicy); read through MarketingContent::contactDetails().
 */
class SiteSetting extends Model
{
    use HasTranslations;

    public const SINGLETON_ID = 1;

    /** @var list<string> */
    protected $fillable = [
        'contact_email',
        'phone',
        'phone_has_whatsapp',
        'instagram_url',
        'facebook_url',
        'x_url',
        'linkedin_url',
        'youtube_url',
        'tiktok_url',
    ];

    protected function casts(): array
    {
        return [
            'phone_has_whatsapp' => 'boolean',
        ];
    }

    /**
     * The settings row; created empty on first use.
     */
    public static function current(): self
    {
        if ($settings = static::existing()) {
            return $settings;
        }

        // insertOrIgnore: two concurrent first saves cannot fail on the primary key.
        static::query()->insertOrIgnore(['id' => self::SINGLETON_ID, 'created_at' => now(), 'updated_at' => now()]);

        return static::query()->findOrFail(self::SINGLETON_ID);
    }

    /**
     * The settings row if it exists, without writing (public read side).
     */
    public static function existing(): ?self
    {
        return static::query()->whereKey(self::SINGLETON_ID)->first();
    }

    public function socialUrl(SocialNetwork $network): ?string
    {
        return $this->getAttribute($network->column());
    }

    protected function translationModel(): string
    {
        return SiteSettingTranslation::class;
    }

    protected function translationFallbackLocale(): ?string
    {
        return config('locales.default');
    }
}
