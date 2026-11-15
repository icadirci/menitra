<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Enums\MediaType;
use App\Exceptions\CrossTenantWriteException;
use App\Models\Location;
use App\Models\Tenant;
use App\Services\Media\MediaCleaner;
use App\Support\Media\TenantMediaPath;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\Fixtures\Livewire\ImageUploadFixture;
use Tests\TestCase;

class TenantMediaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Tenant $other;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
        Storage::fake(config('filesystems.default'));

        $this->tenant = Tenant::factory()->create();
        $this->other = Tenant::factory()->create();
        $this->location = Location::factory()->for($this->tenant)->create();

        $this->app->make(TenantContext::class)->set($this->tenant);
    }

    private function storedFile(Tenant $tenant, MediaType $type = MediaType::Location): string
    {
        $path = TenantMediaPath::directory($tenant, $type).'/'.fake()->uuid().'.png';
        Storage::disk('media')->put($path, 'png');

        return $path;
    }

    private function png(string $name = 'cover.png', int $kilobytes = 10): UploadedFile
    {
        return UploadedFile::fake()->image($name, 40, 40)->size($kilobytes);
    }

    private function pngBytes(): string
    {
        $file = $this->png();

        return (string) file_get_contents($file->getRealPath());
    }

    public function test_the_media_disk_uses_the_public_storage_root(): void
    {
        $this->assertSame(storage_path('app/public'), config('filesystems.disks.media.root'));
        $this->assertSame('local', config('filesystems.disks.media.driver'));
        $this->assertSame(config('filesystems.disks.public.root'), config('filesystems.disks.media.root'));
    }

    public function test_paths_are_tenant_prefixed_and_names_are_uuids_from_the_mime_type(): void
    {
        $this->assertSame("tenants/{$this->tenant->public_id}/items", TenantMediaPath::directory($this->tenant, MediaType::Item));

        // A real upload (content-based MIME detection) with a misleading name.
        $tmp = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($tmp, $this->pngBytes());
        $png = new UploadedFile($tmp, 'evil.php', null, null, true);

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}\.png$/', TenantMediaPath::fileName($png));
        unlink($tmp);
    }

    public function test_ownership_rejects_foreign_traversal_and_absolute_paths(): void
    {
        $own = $this->storedFile($this->tenant);
        $prefix = "tenants/{$this->tenant->public_id}";

        $this->assertTrue(TenantMediaPath::isOwnedBy($this->tenant, $own, MediaType::Location));
        $this->assertFalse(TenantMediaPath::isOwnedBy($this->tenant, $own, MediaType::Item));
        $this->assertFalse(TenantMediaPath::isOwnedBy($this->tenant, $this->storedFile($this->other)));

        foreach ([
            "{$prefix}/locations/../../{$this->other->public_id}/locations/x.png",
            "{$prefix}/./locations/x.png",
            "/{$prefix}/locations/x.png",
            "{$prefix}\\locations\\x.png",
            "{$prefix}/",
            "{$prefix}extra/locations/x.png",
            '',
        ] as $path) {
            $this->assertFalse(TenantMediaPath::isOwnedBy($this->tenant, $path), $path);
        }
    }

    public function test_a_model_cannot_point_to_another_tenants_file(): void
    {
        $this->expectException(CrossTenantWriteException::class);

        $this->location->forceFill(['cover_path' => $this->storedFile($this->other)])->save();
    }

    public function test_a_model_cannot_point_to_a_file_of_another_media_type(): void
    {
        $this->expectException(CrossTenantWriteException::class);

        $this->location->forceFill(['cover_path' => $this->storedFile($this->tenant, MediaType::Logo)])->save();
    }

    public function test_replacing_an_image_deletes_the_old_file_after_commit(): void
    {
        $old = $this->storedFile($this->tenant);
        $this->location->forceFill(['cover_path' => $old])->save();
        $new = $this->storedFile($this->tenant);

        DB::transaction(function () use ($new, $old): void {
            $this->location->forceFill(['cover_path' => $new])->save();
            Storage::disk('media')->assertExists($old); // not before commit
        });

        Storage::disk('media')->assertMissing($old);
        Storage::disk('media')->assertExists($new);
    }

    public function test_clearing_an_image_deletes_the_file(): void
    {
        $old = $this->storedFile($this->tenant);
        $this->location->forceFill(['cover_path' => $old])->save();

        $this->location->forceFill(['cover_path' => null])->save();

        Storage::disk('media')->assertMissing($old);
    }

    public function test_a_rolled_back_replacement_keeps_the_old_file(): void
    {
        $old = $this->storedFile($this->tenant);
        $this->location->forceFill(['cover_path' => $old])->save();

        try {
            DB::transaction(function (): void {
                $this->location->forceFill(['cover_path' => $this->storedFile($this->tenant)])->save();

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
            // expected
        }

        Storage::disk('media')->assertExists($old);
    }

    public function test_soft_delete_keeps_files_and_force_delete_removes_them(): void
    {
        $file = $this->storedFile($this->tenant);
        $this->location->forceFill(['cover_path' => $file])->save();

        $this->location->delete();
        Storage::disk('media')->assertExists($file);

        $this->location->forceDelete();
        Storage::disk('media')->assertMissing($file);
    }

    public function test_tenant_logo_and_cover_use_their_own_directories(): void
    {
        $logo = $this->storedFile($this->tenant, MediaType::Logo);
        $this->tenant->forceFill(['logo_path' => $logo])->save();
        $this->assertSame($logo, $this->tenant->fresh()->logo_path);

        $this->expectException(CrossTenantWriteException::class);
        $this->tenant->forceFill(['cover_path' => $this->storedFile($this->tenant, MediaType::Logo)])->save();
    }

    public function test_the_cleaner_never_deletes_outside_the_tenant_prefix(): void
    {
        $foreign = $this->storedFile($this->other);
        $unrelated = 'private/backups/db.dump';
        Storage::disk('media')->put($unrelated, 'x');

        $cleaner = $this->app->make(MediaCleaner::class);
        $cleaner->deleteAfterCommit($this->tenant, $foreign);
        $cleaner->deleteAfterCommit($this->tenant, $unrelated);
        $cleaner->deleteAfterCommit($this->tenant, "tenants/{$this->tenant->public_id}/../{$this->other->public_id}/locations/x.png");

        Storage::disk('media')->assertExists($foreign);
        Storage::disk('media')->assertExists($unrelated);
    }

    public function test_an_upload_is_stored_under_the_tenant_prefix_with_a_uuid_name(): void
    {
        Livewire::test(ImageUploadFixture::class, ['location' => $this->location])
            ->fillForm(['cover_path' => $this->png('../../my photo.png')])
            ->call('save')
            ->assertHasNoFormErrors();

        $path = $this->location->fresh()->cover_path;

        $this->assertMatchesRegularExpression(
            '#^tenants/'.$this->tenant->public_id.'/locations/[0-9a-f-]{36}\.png$#',
            (string) $path,
        );
        Storage::disk('media')->assertExists($path);
    }

    public function test_a_new_upload_replaces_and_removes_the_previous_file(): void
    {
        $old = $this->storedFile($this->tenant);
        $this->location->forceFill(['cover_path' => $old])->save();

        // As in the UI: the current image is removed, then a new one uploaded.
        Livewire::test(ImageUploadFixture::class, ['location' => $this->location])
            ->fillForm(['cover_path' => []])
            ->fillForm(['cover_path' => $this->png()])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNotSame($old, $this->location->fresh()->cover_path);
        Storage::disk('media')->assertMissing($old);
        Storage::disk('media')->assertExists($this->location->fresh()->cover_path);
    }

    public function test_a_failed_save_leaves_no_orphaned_upload(): void
    {
        $old = $this->storedFile($this->tenant);
        $this->location->forceFill(['cover_path' => $old])->save();

        try {
            Livewire::test(ImageUploadFixture::class, ['location' => $this->location])
                ->set('failAfterStoring', true)
                ->fillForm(['cover_path' => []])
                ->fillForm(['cover_path' => $this->png()])
                ->call('save');
            $this->fail('Expected the save to fail.');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame($old, $this->location->fresh()->cover_path);
        $this->assertSame([$old], Storage::disk('media')->allFiles("tenants/{$this->tenant->public_id}"));
    }

    public function test_svg_gif_oversized_and_disguised_files_are_rejected(): void
    {
        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $svgAsPng = UploadedFile::fake()->createWithContent('logo.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $gif = UploadedFile::fake()->image('anim.gif', 10, 10);
        $tooBig = $this->png('big.png', MediaType::Location->maxKilobytes() + 1);
        $wrongExtension = UploadedFile::fake()->createWithContent('cover.php', $this->pngBytes());

        foreach (['svg' => $svg, 'svgAsPng' => $svgAsPng, 'gif' => $gif, 'tooBig' => $tooBig, 'wrongExtension' => $wrongExtension] as $label => $file) {
            Livewire::test(ImageUploadFixture::class, ['location' => $this->location])
                ->fillForm(['cover_path' => $file])
                ->call('save')
                ->assertHasFormErrors(['cover_path']);
        }

        $this->assertNull($this->location->fresh()->cover_path);
        $this->assertSame([], Storage::disk('media')->allFiles('tenants'));
    }

    public function test_a_tampered_path_in_the_upload_state_is_rejected(): void
    {
        $original = $this->storedFile($this->tenant);
        $this->location->forceFill(['cover_path' => $original])->save();
        $otherOwnFile = $this->storedFile($this->tenant);
        $foreignFile = $this->storedFile($this->other);

        foreach ([$otherOwnFile, $foreignFile] as $path) {
            Livewire::test(ImageUploadFixture::class, ['location' => $this->location])
                ->set('data.cover_path', [fake()->uuid() => $path])
                ->call('save')
                ->assertHasFormErrors(['cover_path']);
        }

        $this->assertSame($original, $this->location->fresh()->cover_path);
        Storage::disk('media')->assertExists($otherOwnFile);
        Storage::disk('media')->assertExists($foreignFile);
    }
}
