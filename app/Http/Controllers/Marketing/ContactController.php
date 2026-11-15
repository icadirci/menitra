<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Enums\ContactSubject;
use App\Enums\FaqPlacement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\ContactRequest;
use App\Services\Contact\ContactMessageSender;
use App\Services\Marketing\MarketingContent;
use App\Support\MarketingUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class ContactController extends Controller
{
    public const SENT_FLASH = 'contact_sent';

    public function show(MarketingContent $content): View
    {
        return view('marketing.contact', [
            'contact' => $content->contactDetails(),
            'subjects' => ContactSubject::cases(),
            'hasHomeFaq' => $content->faqs(FaqPlacement::Home)->isNotEmpty(),
            'sent' => (bool) session(self::SENT_FLASH),
        ]);
    }

    public function store(ContactRequest $request, ContactMessageSender $sender): RedirectResponse
    {
        if ($request->isSpam()) {
            // Same answer as a real submission, so bots learn nothing.
            Log::info('Contact form honeypot triggered.');
        } else {
            $sender->send($request->toMessage());
        }

        return redirect()->to(MarketingUrl::to('contact', fragment: 'contact-form'))->with(self::SENT_FLASH, true);
    }
}
