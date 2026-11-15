<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Enums\MediaType;
use App\Models\Location;
use App\Models\Tenant;
use App\Services\Media\ImageNormalizer;
use App\Services\Media\MediaCleaner;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Fixtures\Livewire\ImageUploadFixture;
use Tests\TestCase;

/**
 * Phase 8: uploads are re-encoded with gd (orientation, size per type,
 * metadata stripped, same format) before they are stored.
 */
class ImageNormalizerTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/restano-img-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);

        parent::tearDown();
    }

    private function file(string $name, string $bytes): UploadedFile
    {
        file_put_contents($this->dir.'/'.$name, $bytes);

        return new UploadedFile($this->dir.'/'.$name, $name, test: true);
    }

    private function encode(\GdImage $image, string $format): string
    {
        ob_start();
        match ($format) {
            'jpeg' => imagejpeg($image, null, 90),
            'png' => imagepng($image),
            'webp' => imagewebp($image, null, 90),
        };

        return (string) ob_get_clean();
    }

    private function image(int $width, int $height, bool $transparent = false): \GdImage
    {
        $image = imagecreatetruecolor($width, $height);

        if ($transparent) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
            imagefilledrectangle($image, (int) ($width / 4), (int) ($height / 4), (int) ($width / 2), (int) ($height / 2), imagecolorallocatealpha($image, 200, 30, 30, 0));
        } else {
            imagefill($image, 0, 0, imagecolorallocate($image, 200, 120, 40));
        }

        return $image;
    }

    /**
     * A JPEG with an EXIF APP1 segment: Orientation = 6 (rotate 90° CW) and a
     * GPS IFD pointer plus a marker string, inserted right after SOI.
     */
    private function jpegWithExif(int $width, int $height): string
    {
        $jpeg = $this->encode($this->image($width, $height), 'jpeg');
        $tiff = "II*\x00".pack('V', 8)
            .pack('v', 1).pack('vvVvv', 0x0112, 3, 1, 6, 0).pack('V', 0)
            .'GPS-SECRET-40.98,29.02';
        $app1 = "Exif\x00\x00".$tiff;

        return substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($app1) + 2).$app1.substr($jpeg, 2);
    }

    public function test_jpeg_orientation_is_applied_and_exif_removed(): void
    {
        $file = $this->file('photo.jpg', $this->jpegWithExif(300, 200));
        $this->assertSame(6, exif_read_data($file->getRealPath())['Orientation']);

        app(ImageNormalizer::class)->normalize($file, MediaType::Item);

        clearstatcache();
        $bytes = (string) file_get_contents($file->getRealPath());
        [$width, $height, $type] = getimagesize($file->getRealPath());

        $this->assertSame([200, 300, IMAGETYPE_JPEG], [$width, $height, $type], 'Rotated by the EXIF orientation, still a JPEG.');
        $this->assertStringNotContainsString('GPS-SECRET', $bytes);
        $this->assertStringNotContainsString("Exif\x00\x00", $bytes);
    }

    public function test_images_are_scaled_to_the_longest_edge_of_their_type(): void
    {
        $item = $this->file('wide.png', $this->encode($this->image(3000, 1000), 'png'));
        app(ImageNormalizer::class)->normalize($item, MediaType::Item);
        $this->assertSame([1200, 400], array_slice(getimagesize($item->getRealPath()), 0, 2));

        $logo = $this->file('logo.webp', $this->encode($this->image(1000, 1000), 'webp'));
        app(ImageNormalizer::class)->normalize($logo, MediaType::Logo);
        [$width, $height, $type] = getimagesize($logo->getRealPath());
        $this->assertSame([512, 512, IMAGETYPE_WEBP], [$width, $height, $type]);

        $small = $this->file('small.jpg', $this->encode($this->image(300, 200), 'jpeg'));
        app(ImageNormalizer::class)->normalize($small, MediaType::Cover);
        $this->assertSame([300, 200], array_slice(getimagesize($small->getRealPath()), 0, 2), 'Small images keep their size.');
    }

    public function test_png_transparency_survives(): void
    {
        $file = $this->file('logo.png', $this->encode($this->image(1024, 1024, transparent: true), 'png'));

        app(ImageNormalizer::class)->normalize($file, MediaType::Logo);

        $image = imagecreatefrompng($file->getRealPath());
        $this->assertSame(512, imagesx($image));
        // gd alpha: 127 = fully transparent; bicubic resampling may blend a step or two.
        $this->assertGreaterThanOrEqual(120, (imagecolorat($image, 2, 2) >> 24) & 0x7F, 'The transparent corner stays transparent.');
        $this->assertLessThanOrEqual(5, (imagecolorat($image, 190, 190) >> 24) & 0x7F, 'The opaque area stays opaque.');
    }

    public function test_the_pixel_limit_is_read_from_the_header_without_decoding(): void
    {
        // A PNG header claiming 5000 × 4000 (20 MP), no pixel data.
        $ihdr = pack('NN', 5000, 4000)."\x08\x02\x00\x00\x00";
        $header = "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.$ihdr.pack('N', crc32('IHDR'.$ihdr));
        $huge = $this->file('huge.png', $header);

        $this->assertFalse(ImageNormalizer::withinPixelLimit($huge));
        $this->assertTrue(ImageNormalizer::withinPixelLimit($this->file('ok.png', $this->encode($this->image(4096, 10), 'png'))));

        // Normalising it keeps the original bytes (never decoded).
        app(ImageNormalizer::class)->normalize($huge, MediaType::Item);
        $this->assertSame($header, file_get_contents($huge->getRealPath()));
    }

    public function test_an_undecodable_file_is_kept_as_uploaded(): void
    {
        $ihdr = pack('NN', 10, 10)."\x08\x02\x00\x00\x00";
        $broken = "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.$ihdr.pack('N', crc32('IHDR'.$ihdr)).'garbage';
        $file = $this->file('broken.png', $broken);

        app(ImageNormalizer::class)->normalize($file, MediaType::Item);

        $this->assertSame($broken, file_get_contents($file->getRealPath()));
    }

    public function test_stored_uploads_are_normalised(): void
    {
        Storage::fake('media');
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);

        $path = app(MediaCleaner::class)->store($tenant, $this->file('cover.jpg', $this->jpegWithExif(2400, 1600)), MediaType::Location);

        $stored = Storage::disk('media')->get($path);
        $size = getimagesizefromstring($stored);
        $this->assertSame([1067, 1600], [$size[0], $size[1]], 'Rotated, then scaled to 1600 on the longest edge.');
        $this->assertStringNotContainsString('GPS-SECRET', $stored);
        $this->assertStringEndsWith('.jpg', $path);
    }

    public function test_the_panel_upload_rejects_images_over_the_pixel_limit(): void
    {
        Storage::fake('media');
        Storage::fake(config('filesystems.default'));
        $tenant = Tenant::factory()->create();
        $location = Location::factory()->for($tenant)->create();
        app(TenantContext::class)->set($tenant);

        $ihdr = pack('NN', 6000, 4000)."\x08\x02\x00\x00\x00";
        $header = "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.$ihdr.pack('N', crc32('IHDR'.$ihdr))
            .pack('N', 0).'IEND'.pack('N', crc32('IEND'));

        Livewire::test(ImageUploadFixture::class, ['location' => $location])
            ->fillForm(['cover_path' => UploadedFile::fake()->createWithContent('huge.png', $header)])
            ->call('save')
            ->assertHasFormErrors(['cover_path']);

        $this->assertNull($location->fresh()->cover_path);
        $this->assertSame([], Storage::disk('media')->allFiles('tenants'));
    }
}
