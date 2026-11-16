<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Removes a user's two-factor secret and recovery codes, for a user who lost
 * both their authenticator and their recovery codes. A platform user must set
 * up two-factor again at their next /admin visit. Requires server access, so
 * it is the out-of-band recovery path; verify the person's identity first.
 */
class ResetTwoFactor extends Command
{
    protected $signature = 'platform:reset-two-factor {email : Email of an existing user}';

    protected $description = 'Remove a user\'s two-factor authentication (lost device and recovery codes)';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('No user with that email address.');

            return self::FAILURE;
        }

        $user->forceFill([
            'app_authentication_secret' => null,
            'app_authentication_recovery_codes' => null,
        ])->save();

        $this->info("Two-factor authentication removed for {$user->email}.");

        return self::SUCCESS;
    }
}
