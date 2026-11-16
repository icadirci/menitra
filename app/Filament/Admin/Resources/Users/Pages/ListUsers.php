<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Enums\PlatformRole;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Filament\App\Support\ServiceCall;
use App\Models\User;
use App\Services\Admin\UserAdminService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

/**
 * Business accounts come only from registration and invitations; the only
 * create action here is for platform users (super admins, UserPolicy::create).
 */
class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createPlatformUser')
                ->label(__('admin.users.actions.create_platform_user'))
                ->icon(Heroicon::OutlinedUserPlus)
                ->visible(fn (): bool => auth()->user()?->can('create', User::class) ?? false)
                ->modalDescription(__('admin.users.confirm.create_platform_user'))
                ->schema([
                    TextInput::make('name')
                        ->label(__('admin.users.fields.name'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('email')
                        ->label(__('admin.users.fields.email'))
                        ->email()
                        ->required()
                        ->maxLength(255),
                    Select::make('role')
                        ->label(__('admin.users.fields.platform_role'))
                        ->options(collect(PlatformRole::cases())->mapWithKeys(fn (PlatformRole $role): array => [$role->value => $role->label()])->all())
                        ->default(PlatformRole::PlatformStaff->value)
                        ->required()
                        ->selectablePlaceholder(false),
                ])
                ->successNotificationTitle(__('admin.users.notifications.platform_user_created'))
                ->action(function (array $data, Action $action): void {
                    $user = auth()->user();
                    abort_unless($user instanceof User, 403);

                    ServiceCall::run(fn () => app(UserAdminService::class)->createPlatformUser(
                        $user,
                        (string) $data['name'],
                        (string) $data['email'],
                        (string) $data['role'],
                    ), statePath: null);
                    $action->success();
                }),
        ];
    }
}
