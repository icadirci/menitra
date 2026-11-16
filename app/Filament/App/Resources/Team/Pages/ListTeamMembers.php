<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Team\Pages;

use App\Filament\App\Resources\Team\TeamInvitationResource;
use App\Filament\App\Resources\Team\TeamMemberResource;
use Filament\Resources\Pages\ListRecords;

class ListTeamMembers extends ListRecords
{
    protected static string $resource = TeamMemberResource::class;

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
