<?php

declare(strict_types=1);

return [

    'payment_statuses' => [
        'pending' => 'Pending',
        'succeeded' => 'Paid',
        'failed' => 'Failed',
        'expired' => 'Not completed',
    ],

    'payment_kinds' => [
        'activation' => 'Activation',
        'renewal' => 'Renewal',
        'resubscription' => 'Resubscription',
    ],

    'profile_types' => [
        'individual' => 'Individual / sole proprietor',
        'corporate' => 'Company',
    ],

    'validation' => [
        'tax_id_individual' => 'Enter a valid Turkish ID number (11 digits).',
        'tax_id_corporate' => 'Enter a valid tax number (10 digits).',
    ],

    'errors' => [
        'not_allowed' => 'A payment is not possible right now.',
        'renewal_not_open' => 'Your subscription is active. You can pay for the next period from :date.',
        'plan_not_allowed' => 'This plan can\'t be paid for right now. You can change plans once the paid period ends.',
        'billing_profile_missing' => 'Enter your billing details before paying.',
        'too_many_attempts' => 'Too many payment attempts. Please try again later.',
        'gateway_unavailable' => 'The payment page couldn\'t be opened. Your card was not charged; please try again in a few minutes.',
        'no_price' => 'This plan has no price for the selected period.',
    ],

    'actions' => [
        'pay' => 'Pay and continue',
        'renew' => 'Renew subscription',
        'billing_details' => 'Billing details',
        'back_to_subscription' => 'Back to subscription',
    ],

    'subscription_note' => 'Payments are taken by iyzico with 3-D Secure; Restano never stores your card details. Each payment covers one period (monthly or yearly) and nothing is charged automatically; we email you a reminder before the period ends.',

    'checkout' => [
        'title' => 'Payment',
        'not_open' => [
            'title' => 'A payment is not possible right now',
        ],
        'sections' => [
            'plan' => 'Plan and period',
            'plan_help' => 'Choose your plan and billing period. Yearly billing is discounted.',
            'plan_fixed' => 'Your paid period is still running, so the renewal uses your current plan.',
            'summary' => 'Summary',
            'billing' => 'Billing details',
        ],
        'fields' => [
            'plan' => 'Plan',
            'interval' => 'Billing period',
            'terms' => 'I confirm that this is a one-off payment for one period and that I have read and accept the',
            'terms_link' => 'terms of use.',
            'terms_required' => 'Please tick the box to continue.',
        ],
        'summary' => [
            'base' => 'Plan price',
            'extra' => 'Additional branches',
            'extra_value' => ':count branch(es) · :amount',
            'total' => 'Total',
            'vat_included' => 'VAT included',
            'period' => 'Period covered',
        ],
        'billing' => [
            'add' => 'Add billing details',
            'edit' => 'Edit',
            'missing' => 'No billing details yet. Add your billing details before paying.',
        ],
        'extra_locations_line' => 'Additional branches (:count)',
        'pay' => 'Pay securely with iyzico',
        'secure_note' => 'The payment page is provided by iyzico. You\'ll come back here automatically afterwards.',
    ],

    'result' => [
        'succeeded' => [
            'title' => 'Payment received, thank you!',
            'body' => 'Your subscription is active until :date. We emailed you the receipt; it is also in the payment history below.',
        ],
        'pending' => [
            'title' => 'We are checking your payment',
            'body' => 'The result will be clear within a few minutes. If your card was charged, your subscription is updated automatically; there is no need to pay again.',
        ],
        'failed' => [
            'title' => 'The payment didn\'t go through',
            'body' => 'Your card was not charged. Check your card details and try again, or use another card.',
        ],
        'expired' => [
            'title' => 'The payment was not completed',
            'body' => 'The payment wasn\'t completed on the payment page. You can try again at any time.',
        ],
    ],

    'history' => [
        'heading' => 'Payment history',
        'empty' => 'No payments yet',
        'receipt' => 'Receipt',
        'columns' => [
            'date' => 'Date',
            'plan' => 'Plan',
            'amount' => 'Amount',
            'status' => 'Status',
            'receipt' => 'Receipt no.',
        ],
    ],

    'receipt' => [
        'title' => 'Receipt :number',
        'print' => 'Print',
        'number' => 'Receipt no.',
        'paid_at' => 'Paid on',
        'plan' => 'Plan',
        'period' => 'Period',
        'card' => 'Card',
        'reference' => 'iyzico payment ID',
        'total' => 'Total (VAT included)',
        'tax_id' => 'Tax office / ID number',
        'not_invoice' => 'This is a payment receipt, not an invoice. Your e-Arşiv invoice is sent separately to your billing email address.',
        'sections' => [
            'payment' => 'Payment',
            'amount' => 'Amount',
            'billing' => 'Billing details',
        ],
    ],

    'profile' => [
        'title' => 'Billing details',
        'saved' => 'Billing details saved',
        'sections' => [
            'identity' => 'Invoice type',
            'identity_help' => 'Your invoice is issued with these details. They are passed to iyzico as buyer details when you pay.',
            'contact' => 'Contact and address',
        ],
        'fields' => [
            'type' => 'Invoice type',
            'name' => 'Full name',
            'company_name' => 'Company name',
            'tckn' => 'Turkish ID number',
            'vkn' => 'Tax number',
            'tax_id' => 'ID / tax number',
            'tax_office' => 'Tax office',
            'email' => 'Billing email',
            'email_help' => 'Receipts and invoices are sent to this address.',
            'phone' => 'Mobile phone',
            'address' => 'Address',
            'district' => 'District',
            'city' => 'City',
            'postal_code' => 'Postal code',
        ],
    ],

    'register' => [
        'start' => 'How would you like to start?',
        'trial' => 'Try free for :days days',
        'trial_help' => 'No card needed. Pay any time before the trial ends.',
        'pay_now' => 'Pay now and start',
        'pay_now_help' => 'After verifying your email you go straight to the payment page. You keep your trial: the paid period starts when it ends.',
    ],

    'mail' => [
        'receipt' => [
            'subject' => 'Payment received – receipt :number',
            'greeting' => 'Hello,',
            'intro' => 'We received your payment for :business. Thank you!',
            'cta' => 'View receipt',
        ],
        'reminder' => [
            'greeting' => 'Hello,',
            'cta' => 'Pay now',
            'outro' => 'Paying with iyzico takes a few minutes. Your menus and all your data are always kept.',
            'trial_ending' => [
                'subject' => ':business – your trial is ending',
                'body' => '{0} The :plan trial for :business ends today (:date). Pay now to keep your menu online without interruption.|{1} The :plan trial for :business ends tomorrow (:date). Pay now to keep your menu online without interruption.|[2,*] The :plan trial for :business ends in :count days (:date). Pay now to keep your menu online without interruption.',
            ],
            'period_ending' => [
                'subject' => ':business – your subscription period is ending',
                'body' => '{0} Your :plan period for :business ends today (:date). Pay now to renew.|{1} Your :plan period for :business ends tomorrow (:date). Pay now to renew.|[2,*] Your :plan period for :business ends in :count days (:date). Pay now to renew.',
            ],
            'grace_started' => [
                'subject' => ':business – payment due',
                'body' => 'Your :plan subscription for :business has ended and is in its grace period. Your menu stays online until :date; without a payment by then it goes offline.',
            ],
        ],
    ],

];
