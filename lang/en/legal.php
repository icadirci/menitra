<?php

declare(strict_types=1);

/*
 * LEGAL REVIEW REQUIRED — generic drafts written during the unattended run
 * (2026-11-15). Before publishing, a lawyer must review them against the
 * Turkish Personal Data Protection Law (KVKK) and related legislation, and the
 * bracketed placeholders ([...]) must be filled in. After the review set
 * config marketing.legal_review_pending to false (removes the draft notice).
 * The Turkish text is the primary version.
 */

return [

    'eyebrow' => 'Legal',
    'updated' => 'Last updated: :date',
    'draft_title' => 'Draft text.',
    'draft_body' => 'This is a generic draft that has not been legally reviewed yet. Until the binding version is published, please contact us with any questions.',
    'contact_lead' => 'For questions and requests about this text:',
    'contact_link' => 'Contact page',

    'privacy' => [
        'title' => 'Privacy and Personal Data Protection',
        'description' => 'Why Menitra processes personal data, how it is protected and your rights under the Turkish Personal Data Protection Law (KVKK).',
        'updated' => '28 November 2026',
        'intro' => 'This notice explains, in line with the Turkish Personal Data Protection Law No. 6698 ("KVKK"), how your personal data is processed when you use the Menitra platform (website, business panel and QR menu pages).',
        'sections' => [
            [
                'title' => '1. Data controller',
                'paragraphs' => [
                    'The data controller is Menitra.',
                    'Content that businesses publish in their QR menus (products, prices, business details) is the responsibility of the business concerned.',
                ],
            ],
            [
                'title' => '2. Personal data we process',
                'paragraphs' => ['Depending on the service, we may process:'],
                'items' => [
                    'Account data: name, email address, an irreversible hash of your password, email verification status.',
                    'Business data: business name, branch addresses, phone and contact details, menu content and uploaded images.',
                    'Subscription data: selected plan, trial and subscription dates.',
                    'Contact form: your name, email address, subject and message. Messages are forwarded to our team by email and not stored in our database.',
                    'Technical logs: IP address, browser information and request times for security and error tracking.',
                ],
            ],
            [
                'title' => '3. Purposes and legal grounds',
                'paragraphs' => ['We process personal data to create your account and provide the service (KVKK Art. 5/2-c, performance of a contract), to comply with legal obligations (Art. 5/2-ç), to keep the service secure and prevent abuse (Art. 5/2-f, legitimate interest) and to answer your requests.'],
            ],
            [
                'title' => '4. Cookies',
                'paragraphs' => [
                    'The website and business panel use strictly necessary cookies for your session and form security (CSRF).',
                    'The marketing pages, the registration page and the email verification page use the Google Ads tag and its cookies to measure how our ads perform, only with your explicit consent (KVKK Art. 5/1). Without consent no request is sent to Google. You can change your choice at any time with the "Cookie settings" link at the bottom of the page. The business panel and the account page use no advertising cookies.',
                    'QR menu pages use no cookies and start no session.',
                ],
            ],
            [
                'title' => '5. Transfers',
                'paragraphs' => ['Data may be shared with our hosting, email delivery and infrastructure providers only as far as needed to provide the service and in line with KVKK Articles 8 and 9. [List of providers and cross-border transfer details to be added.] Data may be shared with authorities where required by law.', 'If you accept Google Ads cookies, the page you visit, browser and device details, your IP address and ad click details are transferred to Google LLC (USA). This cross-border transfer is based on the explicit consent you give in the cookie banner (KVKK Art. 9).'],
            ],
            [
                'title' => '6. Retention',
                'paragraphs' => ['Data is kept for the duration of the service relationship and for the periods required by law. The end of a subscription does not delete business data automatically; deletion requests are handled separately. [Specific retention periods to be added.]'],
            ],
            [
                'title' => '7. Your rights',
                'paragraphs' => ['Under KVKK Art. 11 you may learn whether your data is processed, request information, learn whether it is used for its purpose, know the third parties it is transferred to, ask for incomplete or incorrect data to be corrected, ask for deletion or destruction where the conditions are met, object, and claim compensation for damages. Send your request through the contact page or to [application address].'],
            ],
        ],
    ],

    'terms' => [
        'title' => 'Terms of Use',
        'description' => 'Terms of use of the Menitra QR menu platform: accounts, trial and subscription, content responsibility and limits of the service.',
        'updated' => '15 November 2026',
        'intro' => 'These terms govern the use by businesses of the QR menu platform provided by Menitra. By creating an account you accept these terms.',
        'sections' => [
            [
                'title' => '1. The service',
                'paragraphs' => ['Menitra is software that lets businesses create digital menus, publish them through QR codes and manage their branches, menus and item availability. It does not provide online ordering, payment or reservations.'],
            ],
            [
                'title' => '2. Account and security',
                'paragraphs' => ['You are responsible for the accuracy of the information you give when registering and for keeping your login details confidential. You are responsible for actions taken through your account.'],
            ],
            [
                'title' => '3. Trial, subscription and fees',
                'paragraphs' => [
                    'New businesses start with a free trial. When a trial or subscription ends, a short grace period follows; menus of accounts not renewed by its end go offline.',
                    'Your menus, branches and QR codes are not deleted automatically when a subscription ends; they come back online when you renew. Plan prices and limits are listed on the pricing page. [Payment, invoicing and refund terms to be added with the payment integration.]',
                ],
            ],
            [
                'title' => '4. Responsibility for content',
                'paragraphs' => ['Product names, descriptions, prices, allergen and ingredient information and images published in a menu are the responsibility of the business. The business ensures this information is accurate, up to date and compliant with the law (including price labelling, food and consumer legislation) and uploads no content that infringes third-party rights.'],
            ],
            [
                'title' => '5. Unacceptable use',
                'paragraphs' => ['The following is not allowed:'],
                'items' => [
                    'using the service for unlawful purposes or with misleading content,',
                    'trying to access other businesses’ data or to bypass security measures,',
                    'sending automated or excessive requests that disrupt the service.',
                ],
            ],
            [
                'title' => '6. Availability',
                'paragraphs' => ['We make reasonable efforts to keep the service available and error-free, but interruptions may happen for maintenance, updates or reasons beyond our control. Accounts that breach these terms may be suspended; suspension does not delete data.'],
            ],
            [
                'title' => '7. Limitation of liability',
                'paragraphs' => ['To the extent permitted by applicable law, we are not liable for indirect damages or loss of profit. [Liability cap to be set after legal review.]'],
            ],
            [
                'title' => '8. Changes and governing law',
                'paragraphs' => ['We may update these terms and will announce material changes to your registered email address. These terms are governed by the laws of the Republic of Türkiye. [Competent courts and enforcement offices to be specified.]'],
            ],
        ],
    ],

];
