<?php

declare(strict_types=1);

namespace App\Services\Contact;

use App\Enums\ContactSubject;

/**
 * A validated message from the public contact form.
 */
final readonly class ContactMessage
{
    public function __construct(
        public string $name,
        public string $email,
        public ContactSubject $subject,
        public string $body,
        public string $locale,
    ) {}
}
