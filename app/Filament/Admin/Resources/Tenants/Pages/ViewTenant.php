<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Tenants\Pages;

use App\Filament\Admin\Resources\Tenants\TenantResource;
use App\Filament\App\Support\ServiceCall;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Admin\TenantAdminService;
use App\Services\Tenancy\TenantSlugService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * Tenant detail with the super-admin actions (TenantPolicy; the service
 * authorizes again and writes the audit log).
 *
 * @property Tenant $record
 */
class ViewTenant extends ViewRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('changeSlug')
                ->label(__('admin.tenants.actions.change_slug'))
                ->icon(Heroicon::OutlinedLink)
                ->authorize('changeSlug')
                ->modalDescription(__('admin.tenants.confirm.change_slug'))
                ->fillForm(fn (): array => ['slug' => $this->record->slug])
                ->schema([
                    TextInput::make('slug')
                        ->label(__('admin.tenants.fields.slug'))
                        ->helperText(__('admin.tenants.fields.slug_help'))
                        ->required()
                        ->minLength(3)
                        ->maxLength(40)
                        ->rules(fn (): array => TenantSlugService::rules($this->record))
                        ->validationMessages([
                            'regex' => __('auth.register.slug_format'),
                            'not_in' => __('auth.register.slug_reserved'),
                            'unique' => __('admin.tenants.errors.slug_taken'),
                        ]),
                ])
                ->successNotificationTitle(__('admin.notifications.saved'))
                ->action(function (array $data, Action $action): void {
                    ServiceCall::run(fn () => app(TenantAdminService::class)->changeSlug($this->actor(), $this->record, (string) $data['slug']), statePath: null);
                    $action->success();
                }),
            Action::make('suspend')
                ->label(__('admin.tenants.actions.suspend'))
                ->icon(Heroicon::OutlinedPauseCircle)
                ->color('warning')
                ->authorize('suspend')
                ->requiresConfirmation()
                ->modalDescription(__('admin.tenants.confirm.suspend'))
                ->schema([
                    Textarea::make('reason')
                        ->label(__('admin.tenants.fields.reason'))
                        ->helperText(__('admin.tenants.fields.reason_help'))
                        ->rows(2)
                        ->maxLength(500),
                ])
                ->successNotificationTitle(__('admin.notifications.saved'))
                ->action(function (array $data, Action $action): void {
                    app(TenantAdminService::class)->suspend($this->actor(), $this->record, $data['reason'] ?? null);
                    $action->success();
                }),
            Action::make('unsuspend')
                ->label(__('admin.tenants.actions.unsuspend'))
                ->icon(Heroicon::OutlinedPlayCircle)
                ->color('success')
                ->authorize('unsuspend')
                ->requiresConfirmation()
                ->successNotificationTitle(__('admin.notifications.saved'))
                ->action(function (Action $action): void {
                    app(TenantAdminService::class)->unsuspend($this->actor(), $this->record);
                    $action->success();
                }),
            Action::make('delete')
                ->label(__('admin.tenants.actions.delete'))
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->authorize('delete')
                ->requiresConfirmation()
                ->modalDescription(__('admin.tenants.confirm.delete'))
                ->successNotificationTitle(__('admin.notifications.deleted'))
                ->action(function (Action $action): void {
                    app(TenantAdminService::class)->delete($this->actor(), $this->record);
                    $action->success();
                }),
            Action::make('restore')
                ->label(__('admin.tenants.actions.restore'))
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('success')
                ->authorize('restore')
                ->requiresConfirmation()
                ->modalDescription(__('admin.tenants.confirm.restore'))
                ->successNotificationTitle(__('admin.notifications.restored'))
                ->action(function (Action $action): void {
                    app(TenantAdminService::class)->restore($this->actor(), $this->record);
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
