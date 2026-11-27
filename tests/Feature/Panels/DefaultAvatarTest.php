<?php

declare(strict_types=1);

namespace Tests\Feature\Panels;

use App\Filament\Shared\InitialsAvatarProvider;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_panels_use_the_initials_provider(): void
    {
        foreach (['admin', 'app'] as $id) {
            $this->assertSame(InitialsAvatarProvider::class, Filament::getPanel($id)->getDefaultAvatarProvider());
        }
    }

    public function test_user_avatar_is_an_inline_svg_with_initials(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('app'));
        $user = User::factory()->create(['name' => 'İbrahim Halil Çadırcı']);

        $url = Filament::getUserAvatarUrl($user);

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $url);
        $this->assertStringNotContainsString('ui-avatars.com', $url);
        $this->assertStringContainsString('>İÇ</text>', base64_decode(substr($url, strlen('data:image/svg+xml;base64,'))));
    }

    public function test_initials(): void
    {
        $this->assertSame('İÇ', InitialsAvatarProvider::initials('  İbrahim Halil Çadırcı '));
        $this->assertSame('M', InitialsAvatarProvider::initials('mabella'));
        $this->assertSame('SA', InitialsAvatarProvider::initials('[SYSTEM] Admin'));
        $this->assertSame('?', InitialsAvatarProvider::initials(''));
    }

    public function test_initials_are_escaped_in_the_svg(): void
    {
        $user = User::factory()->create(['name' => '<script> &x']);

        $svg = base64_decode(substr((new InitialsAvatarProvider)->get($user), strlen('data:image/svg+xml;base64,')));

        $this->assertStringNotContainsString('<script', $svg);
        $this->assertStringContainsString('&lt;&amp;', $svg);
    }

    public function test_colour_is_stable_per_record_and_varies_between_records(): void
    {
        $users = User::factory()->count(12)->create();

        $first = InitialsAvatarProvider::colour($users[0]);
        $users[0]->update(['name' => 'Renamed User']);

        $this->assertSame($first, InitialsAvatarProvider::colour($users[0]->fresh()));
        $this->assertGreaterThan(1, $users->map(fn (User $user): string => InitialsAvatarProvider::colour($user))->unique()->count());
    }

    public function test_tenant_uses_its_logo_or_falls_back_to_initials(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('app'));
        $tenant = Tenant::factory()->create();

        $this->assertStringStartsWith('data:image/svg+xml;base64,', Filament::getTenantAvatarUrl($tenant));

        $tenant->forceFill(['logo_path' => 'tenants/'.$tenant->public_id.'/logo/a.png'])->saveQuietly();

        $this->assertStringEndsWith('tenants/'.$tenant->public_id.'/logo/a.png', Filament::getTenantAvatarUrl($tenant));
    }
}
