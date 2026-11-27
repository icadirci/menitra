<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Enums\BillingInterval;
use App\Enums\BillingReminderType;
use App\Enums\ShopOrderStatus;
use App\Enums\TenantRole;
use App\Mail\BillingReminderMail;
use App\Mail\PaymentReceiptMail;
use App\Mail\ShopOrderMail;
use App\Mail\TeamInvitationMail;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Mailable;
use Tests\TestCase;

class BillingSenderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail.from.address' => 'info@menitra.test',
            'mail.from.name' => 'Menitra',
        ]);
    }

    public function test_payment_mails_are_sent_from_the_billing_address(): void
    {
        config([
            'mail.billing_from.address' => 'sales@menitra.test',
            'mail.billing_from.name' => 'Menitra Satış Ekibi',
        ]);

        foreach ($this->paymentMails() as $mail) {
            $this->assertTrue(
                $mail->hasFrom('sales@menitra.test', 'Menitra Satış Ekibi'),
                $mail::class.' is not sent from the billing address.',
            );
        }
    }

    public function test_payment_mails_fall_back_to_the_global_sender(): void
    {
        config(['mail.billing_from.address' => null, 'mail.billing_from.name' => null]);

        foreach ($this->paymentMails() as $mail) {
            $this->assertTrue($mail->hasFrom('info@menitra.test', 'Menitra'), $mail::class);
        }
    }

    public function test_other_mails_keep_the_global_sender(): void
    {
        config(['mail.billing_from.address' => 'sales@menitra.test']);

        $mail = new TeamInvitationMail('https://menitra.test/invitations/x', 'Kafe', 'Ayşe', TenantRole::Manager, CarbonImmutable::now()->addWeek());

        $this->assertFalse($mail->hasFrom('sales@menitra.test'));
    }

    /**
     * @return list<Mailable>
     */
    private function paymentMails(): array
    {
        $now = CarbonImmutable::parse('2026-11-27 10:00', 'UTC');

        return [
            new PaymentReceiptMail('R-1', 'Kafe', 'Profesyonel', BillingInterval::Monthly, 49900, 'TRY', $now, $now->addMonth(), $now, 'https://menitra.test/app'),
            new BillingReminderMail(BillingReminderType::cases()[0], 'Kafe', 'Profesyonel', $now, 3, 'https://menitra.test/app'),
            new ShopOrderMail(ShopOrderStatus::cases()[0], 'S-1', 'Kafe', ['1 × Stand'], 15000, 'TRY', 'https://menitra.test/app'),
        ];
    }
}
