<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Services\Marketing\ContactDetails;

/**
 * Everything /account renders, already authorized and allow-listed for the
 * member's role: owners get the subscription, the tenant's public link and
 * the default branch; other roles only whether the menu is online and their
 * own branches.
 */
final readonly class AccountSummary
{
    /**
     * @param  list<AccountBranch>  $branches
     */
    public function __construct(
        public string $businessName,
        public bool $isOwner,
        public bool $menuOnline,
        public string $panelUrl,
        public ?string $publicMenuUrl,
        public ?AccountSubscription $subscription,
        public array $branches,
        public ?string $contactUrl = null,
        public ?ContactDetails $support = null,
    ) {}

    /**
     * Owner and the menu is offline (expired, no subscription, or access
     * already ended): show the reactivation path.
     */
    public function needsReactivation(): bool
    {
        return $this->isOwner && ! $this->menuOnline;
    }
}
