<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Enums\PlatformRole;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Filament\App\Support\ServiceCall;
use App\Models\User;
use App\Services\Admin\Impersonation;
use App\Services\Admin\UserAdminService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * User detail with the user actions (UserPolicy; the services authorize
 * again and write the audit log).
 *
 * @property User $record
 */
class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('impersonate')
                ->label(__('admin.users.actions.impersonate'))
                ->icon(Heroicon::OutlinedEye)
                ->authorize('impersonate')
                ->requiresConfirmation()
                ->modalDescription(__('admin.users.confirm.impersonate', ['minutes' => Impersonation::MAX_MINUTES]))
                ->action(function (): void {
                    ServiceCall::run(fn () => app(Impersonation::class)->start($this->actor(), $this->record), statePath: null);

                    $this->redirect(Filament::getPanel('app')->getUrl());
                }),
            Action::make('resendVerification')
                ->label(__('admin.users.actions.resend_verification'))
                ->icon(Heroicon::OutlinedEnvelope)
                ->authorize('resendVerification')
                ->requiresConfirmation()
                ->successNotificationTitle(__('admin.users.notifications.verification_sent'))
                ->action(function (Action $action): void {
                    ServiceCall::run(fn () => app(UserAdminService::class)->resendVerification($this->actor(), $this->record), statePath: null);
                    $action->success();
                }),
            Action::make('changePlatformRole')
                ->label(__('admin.users.actions.change_platform_role'))
                ->icon(Heroicon::OutlinedShieldCheck)
                ->authorize('changePlatformRole')
                ->modalDescription(__('admin.users.confirm.change_platform_role'))
                ->fillForm(fn (): array => ['role' => $this->record->platform_role?->value ?? 'none'])
                ->schema([
                    Select::make('role')
                        ->label(__('admin.users.fields.platform_role'))
                        ->options(['none' => __('admin.users.no_platform_role')] + collect(PlatformRole::cases())->mapWithKeys(fn (PlatformRole $role): array => [$role->value => $role->label()])->all())
                        ->required()
                        ->selectablePlaceholder(false),
                ])
                ->successNotificationTitle(__('admin.notifications.saved'))
                ->action(function (array $data, Action $action): void {
                    $role = ($data['role'] ?? 'none') === 'none' ? null : (string) $data['role'];

                    ServiceCall::run(fn () => app(UserAdminService::class)->changePlatformRole($this->actor(), $this->record, $role), statePath: null);
                    $action->success();
                }),
            Action::make('deactivate')
                ->label(__('admin.users.actions.deactivate'))
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('danger')
                ->authorize('deactivate')
                ->requiresConfirmation()
                ->modalDescription(__('admin.users.confirm.deactivate'))
                ->successNotificationTitle(__('admin.notifications.saved'))
                ->action(function (Action $action): void {
                    app(UserAdminService::class)->deactivate($this->actor(), $this->record);
                    $action->success();
                }),
            Action::make('reactivate')
                ->label(__('admin.users.actions.reactivate'))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->authorize('reactivate')
                ->requiresConfirmation()
                ->successNotificationTitle(__('admin.notifications.saved'))
                ->action(function (Action $action): void {
                    app(UserAdminService::class)->reactivate($this->actor(), $this->record);
                    $action->success();
                }),
        ];
    }

    private function actor(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
