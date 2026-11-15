<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Topics of the marketing contact form.
 */
enum ContactSubject: string
{
    case Pricing = 'pricing';
    case Setup = 'setup';
    case Billing = 'billing';
    case Issue = 'issue';
    case Partnership = 'partnership';

    public function label(?string $locale = null): string
    {
        return __("marketing.contact_page.subjects.{$this->value}", [], $locale);
    }
}
