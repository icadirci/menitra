<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Team\Pages;

use App\Filament\App\Resources\Team\TeamInvitationResource;
use Filament\Resources\Pages\ListRecords;

class ListTeamInvitations extends ListRecords
{
    protected static string $resource = TeamInvitationResource::class;

    public function getSubheading(): ?string
    {
        return TeamInvitationResource::usageText();
    }

    protected function getHeaderActions(): array
    {
        return [
            TeamInvitationResource::inviteAction(),
        ];
    }
}
