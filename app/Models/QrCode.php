<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Stable QR code: restano.app/q/{code} → redirect computed at scan time.
 * `code` is 10 random Crockford base32 characters, never sequential.
 */
class QrCode extends Model
{
    use BelongsToTenant;

    public const string ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    /** @var list<string> */
    protected $fillable = [
        'location_id',
        'menu_id',
        'location_table_id',
        'label',
        'table_label',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_scanned_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (QrCode $qr): void {
            $qr->code ??= self::generateCode();
        });
    }

    public static function generateCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 10; $i++) {
                $code .= self::ALPHABET[random_int(0, 31)];
            }
            // Global uniqueness: a plain table query, not a tenant-scope bypass.
        } while (DB::table('qr_codes')->where('code', $code)->exists());

        return $code;
    }

    public static function normalise(string $code): string
    {
        return strtr(Str::upper($code), ['I' => '1', 'L' => '1', 'O' => '0', 'U' => 'V']);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * Set for table codes (one per table, same branch); null for branch codes.
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(LocationTable::class, 'location_table_id');
    }

    public function isTableCode(): bool
    {
        return $this->location_table_id !== null;
    }
}
