<?php

declare(strict_types=1);

namespace App\Filament\Shared\Auth;

use App\Services\Admin\Impersonation;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Schemas\Schema;

/**
 * Profile page of both panels, reduced to two-factor management (set up,
 * regenerate recovery codes, disable). Name, email and password are not
 * editable here: password changes go through the password reset flow, and
 * an email change would need re-verification that isn't built.
 *
 * The form is empty and save() refuses, so a crafted Livewire call can't
 * write profile fields either. During an impersonation the page is refused
 * altogether (and User refuses two-factor writes itself).
 */
class EditProfile extends BaseEditProfile
{
    public static function canAccess(): bool
    {
        return ! app(Impersonation::class)->isActive() && parent::canAccess();
    }

    public function mount(): void
    {
        abort_if(app(Impersonation::class)->isActive(), 403);

        parent::mount();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components(array_filter([
            $this->getMultiFactorAuthenticationContentComponent(),
        ]));
    }

    public function save(): void
    {
        abort(403);
    }
}
