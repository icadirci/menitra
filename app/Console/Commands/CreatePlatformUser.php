<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PlatformRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Creates a platform user (access to /admin) without a business: public
 * registration always creates a tenant, so the first super admin on a fresh
 * server is created here. The password is asked twice (hidden) or generated
 * and printed once; it is never passed on the command line or stored in code.
 * The email counts as verified; /admin asks for two-factor setup at the first
 * sign-in. Further platform users are created in /admin.
 */
class CreatePlatformUser extends Command
{
    protected $signature = 'platform:create-user
                            {email : Email address of the new account}
                            {--name= : Display name (asked when omitted)}
                            {--role=super_admin : super_admin or platform_staff}
                            {--generate-password : Generate a random password and print it once (for non-interactive shells)}';

    protected $description = 'Create a platform user (access to /admin) without creating a business';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $role = PlatformRole::tryFrom((string) $this->option('role'));

        if ($role === null) {
            $this->error('Role must be one of: '.implode(', ', array_column(PlatformRole::cases(), 'value')).'.');

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error('A user with that email already exists. Grant the role with platform:assign-role instead.');

            return self::FAILURE;
        }

        $name = trim((string) ($this->option('name') ?: $this->ask('Name', 'Platform Yöneticisi')));
        $generated = (bool) $this->option('generate-password');
        $password = $generated ? Str::password(20) : (string) $this->secret('Password');
        $confirmation = $generated ? $password : (string) $this->secret('Password (again)');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
                'password' => ['required', 'string', Password::default(), 'confirmed'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = User::query()->create(['name' => $name, 'email' => $email, 'password' => Hash::make($password)]);
        $user->forceFill(['platform_role' => $role, 'email_verified_at' => now()])->save();

        $this->info("{$user->email} was created as {$role->value} (no business).");

        if ($generated) {
            $this->warn("Password (shown only once): {$password}");
        }

        $this->line('Sign in at '.url('/admin/login').' and set up two-factor authentication.');

        return self::SUCCESS;
    }
}
