<?php

declare(strict_types=1);

namespace App\Http\Requests\Team;

use App\Actions\Fortify\PasswordValidationRules;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Services\Team\InvitationTokenResolver;
use App\Services\Team\TeamInvitationService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /invitations/{token}. Which fields are read depends only on server
 * state (signed-in user, existing account for the invited email), never on
 * input. tenant_id, role and branches are never accepted: they come from the
 * stored invitation.
 */
class AcceptInvitationRequest extends FormRequest
{
    use PasswordValidationRules;

    public const string MODE_CONFIRM = 'confirm';

    public const string MODE_MISMATCH = 'mismatch';

    public const string MODE_PASSWORD = 'password';

    public const string MODE_REGISTER = 'register';

    private ?TenantInvitation $invitation = null;

    private bool $resolved = false;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if (! $this->isMethod('post') || $this->invitation() === null) {
            return [];
        }

        return match ($this->mode()) {
            self::MODE_PASSWORD => ['password' => ['required', 'string', 'max:255']],
            self::MODE_REGISTER => [
                'name' => ['required', 'string', 'max:255'],
                'password' => $this->passwordRules(),
            ],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('auth.fields.name'),
            'password' => __('auth.fields.password'),
        ];
    }

    public function invitation(): ?TenantInvitation
    {
        if (! $this->resolved) {
            $this->invitation = app(InvitationTokenResolver::class)->resolve((string) $this->route('token'));
            $this->resolved = true;

            if ($this->invitation !== null) {
                app()->setLocale($this->invitation->locale);
            }
        }

        return $this->invitation;
    }

    public function mode(): string
    {
        return self::modeFor($this->invitation(), $this->user());
    }

    public static function modeFor(?TenantInvitation $invitation, mixed $user): string
    {
        if ($user instanceof User) {
            return $invitation !== null && hash_equals(mb_strtolower($invitation->email), mb_strtolower($user->email))
                ? self::MODE_CONFIRM
                : self::MODE_MISMATCH;
        }

        return $invitation !== null && app(TeamInvitationService::class)->userWithEmail($invitation->email) !== null
            ? self::MODE_PASSWORD
            : self::MODE_REGISTER;
    }
}
