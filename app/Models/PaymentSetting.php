<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\HasTranslations;
use App\Models\Translations\PaymentSettingTranslation;
use Illuminate\Database\Eloquent\Model;

/**
 * Which payment methods owners can use and the bank account for transfers
 * (platform data, single row id = 1). Edited in /admin by super admins
 * (PaymentSettingPolicy); enforced by PaymentMethods in the checkout services.
 * The transfer instructions are translatable and may contain the
 * placeholders {reference}, {amount} and {business}.
 */
class PaymentSetting extends Model
{
    use HasTranslations;

    public const SINGLETON_ID = 1;

    /** Placeholders allowed in the transfer instructions. */
    public const array PLACEHOLDERS = ['{reference}', '{amount}', '{business}'];

    /** @var list<string> */
    protected $fillable = [
        'card_enabled',
        'bank_transfer_enabled',
        'bank_account_holder',
        'bank_name',
        'bank_iban',
        'notification_email',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'card_enabled' => true,
        'bank_transfer_enabled' => false,
    ];

    protected function casts(): array
    {
        return [
            'card_enabled' => 'boolean',
            'bank_transfer_enabled' => 'boolean',
        ];
    }

    /**
     * The settings row, or the unsaved defaults (card payments only).
     */
    public static function current(): self
    {
        return static::query()->with('translations')->find(self::SINGLETON_ID)
            ?? (new self)->forceFill(['id' => self::SINGLETON_ID])->setRelation('translations', new PaymentSettingTranslation()->newCollection());
    }

    /**
     * The settings row; created with the defaults on first save.
     */
    public static function persisted(): self
    {
        // insertOrIgnore: two concurrent first saves cannot fail on the primary key.
        static::query()->insertOrIgnore(['id' => self::SINGLETON_ID, 'created_at' => now(), 'updated_at' => now()]);

        return static::query()->findOrFail(self::SINGLETON_ID);
    }

    public function isEnabled(PaymentMethod $method): bool
    {
        return match ($method) {
            PaymentMethod::Card => $this->card_enabled,
            // Without an account to pay into, transfers can't be offered.
            PaymentMethod::BankTransfer => $this->bank_transfer_enabled && $this->hasBankAccount(),
        };
    }

    public function hasBankAccount(): bool
    {
        return filled($this->bank_iban) && filled($this->bank_account_holder);
    }

    /**
     * "TR12 0006 4000 0011 2345 6789 01".
     */
    public function formattedIban(): ?string
    {
        return $this->bank_iban === null ? null : trim(chunk_split($this->bank_iban, 4, ' '));
    }

    /**
     * The instructions in $locale (else the platform default), placeholders
     * filled in. Plain text: render it escaped.
     */
    public function instructions(string $locale, string $reference, string $amount, string $business): ?string
    {
        $text = $this->translations->firstWhere('locale', $locale)?->bank_transfer_instructions;

        if (blank($text)) {
            $text = $this->translations->firstWhere('locale', config('locales.default'))?->bank_transfer_instructions;
        }

        if (blank($text)) {
            return null;
        }

        return strtr((string) $text, [
            '{reference}' => $reference,
            '{amount}' => $amount,
            '{business}' => $business,
        ]);
    }

    protected function translationModel(): string
    {
        return PaymentSettingTranslation::class;
    }

    protected function translationFallbackLocale(): ?string
    {
        return config('locales.default');
    }
}
