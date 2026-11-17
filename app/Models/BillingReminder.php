<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingReminderType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Tenant-owned log of sent payment reminder mails (SendBillingReminders);
 * the unique key makes every reminder at-most-once.
 */
class BillingReminder extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'type' => BillingReminderType::class,
            'boundary_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
        ];
    }
}
