<?php

declare(strict_types=1);

namespace App\Http\Requests\Marketing;

use App\Enums\ContactSubject;
use App\Services\Contact\ContactMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Public contact form. HONEYPOT is a field real visitors never see; when it is
 * filled the controller answers like a success and sends nothing.
 */
class ContactRequest extends FormRequest
{
    public const HONEYPOT = 'company_website';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'not_regex:/[\r\n]/'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'subject' => ['required', 'string', Rule::enum(ContactSubject::class)],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            self::HONEYPOT => ['nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('marketing.contact_page.fields.name'),
            'email' => __('marketing.contact_page.fields.email'),
            'subject' => __('marketing.contact_page.fields.subject'),
            'message' => __('marketing.contact_page.fields.message'),
        ];
    }

    public function isSpam(): bool
    {
        return filled($this->input(self::HONEYPOT));
    }

    public function toMessage(): ContactMessage
    {
        $data = $this->validated();

        return new ContactMessage(
            name: trim($data['name']),
            email: trim($data['email']),
            subject: ContactSubject::from($data['subject']),
            body: trim($data['message']),
            locale: app()->getLocale(),
        );
    }
}
