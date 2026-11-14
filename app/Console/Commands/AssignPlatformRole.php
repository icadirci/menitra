<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PlatformRole;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Explicitly grants or revokes a platform role. Until a platform user
 * management UI exists, this is the only way to grant /admin access.
 */
class AssignPlatformRole extends Command
{
    protected $signature = 'platform:assign-role
                            {email : Email of an existing user}
                            {role? : super_admin or platform_staff}
                            {--revoke : Remove the platform role instead}';

    protected $description = 'Grant or revoke a platform role (access to /admin)';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('No user with that email address.');

            return self::FAILURE;
        }

        if ($this->option('revoke')) {
            $user->forceFill(['platform_role' => null])->save();
            $this->info("Platform role revoked for {$user->email}.");

            return self::SUCCESS;
        }

        $role = PlatformRole::tryFrom((string) $this->argument('role'));

        if ($role === null) {
            $allowed = implode(', ', array_column(PlatformRole::cases(), 'value'));
            $this->error("Role must be one of: {$allowed}.");

            return self::FAILURE;
        }

        $user->forceFill(['platform_role' => $role])->save();
        $this->info("{$user->email} is now {$role->value}.");

        return self::SUCCESS;
    }
}
