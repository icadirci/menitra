<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use App\Enums\SocialNetwork;

/**
 * Platform contact details for the marketing site, resolved for the current
 * locale. Only these allow-listed fields reach the views.
 */
final readonly class ContactDetails
{
    /**
     * @param  list<array{network: SocialNetwork, url: string}>  $socials
     */
    public function __construct(
        public ?string $email,
        public ?string $phone,
        public bool $whatsapp,
        public ?string $address,
        public ?string $supportHours,
        public array $socials,
    ) {}

    public static function empty(): self
    {
        return new self(null, null, false, null, null, []);
    }

    public function telUrl(): ?string
    {
        return $this->phone !== null ? 'tel:'.$this->digits(keepPlus: true) : null;
    }

    public function whatsappUrl(): ?string
    {
        return $this->phone !== null && $this->whatsapp ? 'https://wa.me/'.$this->digits() : null;
    }

    public function hasChannels(): bool
    {
        return $this->email !== null || $this->phone !== null || $this->address !== null || $this->supportHours !== null;
    }

    private function digits(bool $keepPlus = false): string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->phone);

        return $keepPlus && str_starts_with(trim((string) $this->phone), '+') ? '+'.$digits : $digits;
    }
}
