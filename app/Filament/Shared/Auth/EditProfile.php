<?php

declare(strict_types=1);

namespace App\Filament\Shared\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Schemas\Schema;

/**
 * Profile page of both panels, reduced to two-factor management (set up,
 * regenerate recovery codes, disable). Name, email and password are not
 * editable here: password changes go through the password reset flow, and
 * an email change would need re-verification that isn't built.
 *
 * The form is empty and save() refuses, so a crafted Livewire call can't
 * write profile fields either.
 */
class EditProfile extends BaseEditProfile
{
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
