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
        'upgrade' => 'Plan upgrade',
        'shop_order' => 'QR stand order',
    ],

    'profile_types' => [
        'individual' => 'Individual / sole proprietor',
        'corporate' => 'Company',
    ],

    'validation' => [
        'tax_id_individual' => 'Enter a valid Turkish ID number (11 digits).',
        'iban' => 'Enter a valid Turkish IBAN (26 characters starting with TR).',
        'tax_id_corporate' => 'Enter a valid tax number (10 digits).',
    ],

    'errors' => [
        'shop_closed' => 'The shop is closed right now.',
        'shop_cart_empty' => 'There is nothing in your cart that can be bought.',
        'shop_stock' => 'At most :max of :product can be ordered.',
        'shop_price_changed' => 'Prices in your cart have changed. Check the new total and try again.',
        'not_allowed' => 'A payment is not possible right now.',
        'prepaid_limit' => 'Your subscription is already prepaid for the longest time allowed. You can extend it again from :date.',
        'periods_not_allowed' => 'You can pay for at most :max years in this payment.',
        'plan_not_allowed' => 'This plan can\'t be paid for right now. You can change plans once the paid period ends.',
        'billing_profile_missing' => 'Enter your billing details before paying.',
        'too_many_attempts' => 'Too many payment attempts. Please try again later.',
        'gateway_rejected' => 'The payment page couldn\'t be opened: iyzico didn\'t accept the details sent. Your card was not charged. Please check your billing details (email, phone, ID / tax number, address) and try again.',
        'gateway_unavailable' => 'The payment page couldn\'t be opened. Your card was not charged; please try again in a few minutes.',
        'method_unavailable' => 'The selected payment method is not available right now. Please choose another one or try again later.',
        'transfer_open' => 'You have a pending bank transfer. Complete it, wait for its confirmation or cancel it before starting a new payment.',
        'no_price' => 'This plan has no price for the selected period.',
    ],

    'actions' => [
        'pay' => 'Pay and continue',
        'extend' => 'Extend subscription',
        'choose_paid_plan' => 'Switch to a paid plan',
        'upgrade' => 'Upgrade plan',
        'billing_details' => 'Billing details',
        'back_to_subscription' => 'Back to subscription',
    ],

    'subscription_note' => 'Card payments are taken by iyzico with 3-D Secure and Menitra never stores your card details; bank transfers are confirmed by our team. Each payment covers one month or one or more years, you can extend at any time and nothing is charged automatically; we email you a reminder before your time runs out.',

    'checkout' => [
        'title' => 'Payment',
        'title_active' => 'Extend or upgrade your plan',
        'title_upgrade' => 'Upgrade your plan',
        'subheading' => 'Choose your plan and billing period, then complete the payment.',
        'active' => [
            'title' => 'Your subscription: :plan · :interval · until :date',
            'upgrade_help' => 'Upgrade now and the new plan is active right after payment; you only pay the difference for your remaining prepaid time.',
            'extend_open' => 'You can extend your plan at any time; the new time is added to your current end date, so no days are lost.',
        ],
        'badges' => [
            'current' => 'Current plan',
            'upgrade' => 'Upgrade',
            'popular' => 'Most popular',
        ],
        'select' => 'Choose this plan',
        'selected' => 'Selected',
        'per_month' => '/ month',
        'per_year' => '/ year',
        'per_month_equivalent' => 'Equals :amount per month',
        'monthly_only' => 'This plan is billed monthly only',
        'saving' => 'Save :amount (:percent%)',
        'interval_yearly_saving' => ':interval · :percent% off',
        'yearly_perk_months' => '{1} Pay :paid months instead of 12 with yearly billing: 1 month free.|[2,*] Pay :paid months instead of 12 with yearly billing: :free months free.',
        'yearly_perk_percent' => 'Save :percent% with yearly billing.',
        'switch_yearly' => 'Pay yearly',
        'switch_yearly_hint' => 'Pay yearly and save :amount (:percent%).',
        'branch_note' => ':count branches included · each additional branch +:amount per month',
        'upgrade_today' => 'Pay only :amount today (difference for the remaining :days days)',
        'upgrade_interval' => 'The upgrade runs until the end of your current period; your billing period stays :interval.',
        'upgrade_line' => 'Upgrade to :plan (remaining :days days)',
        'not_open' => [
            'title' => 'A payment is not possible right now',
        ],
        'sections' => [
            'interval' => 'Billing period',
            'plan' => 'Plan',
            'plan_help' => 'Pick the plan that fits your business. Your data is always kept and you can upgrade at any time.',
            'plan_active' => 'Extend your current plan or upgrade right away.',
            'summary' => 'Order summary',
            'billing' => 'Billing details',
        ],
        'fields' => [
            'terms' => 'I confirm that this is a one-off payment for one period and that I have read and accept the',
            'terms_link' => 'terms of use.',
            'years' => 'How many years do you want to pay for?',
            'terms_required' => 'Please tick the box to continue.',
        ],
        'summary' => [
            'base' => 'Plan price',
            'base_years' => 'Plan price (:period)',
            'extra' => 'Additional branches',
            'extra_value' => ':count branch(es) · :amount',
            'total' => 'Total',
            'vat_included' => 'VAT included',
            'period' => 'Period covered',
            'saving' => 'You save :amount (:percent%) with yearly billing · equals :monthly per month',
            'upgrade_title' => 'Upgrade to :plan',
            'upgrade_charge' => 'New plan (remaining :days days)',
            'upgrade_credit' => 'Unused value of your current plan',
            'upgrade_period' => 'From today until :date (current period)',
        ],
        'billing' => [
            'add' => 'Add billing details',
            'edit' => 'Edit',
            'missing' => 'Add your billing details to pay.',
        ],
        'extra_locations_line' => 'Additional branches (:count)',
        'extra_locations_line_periods' => 'Additional branches (:count, :period)',
        'years_extend_help' => 'The selected time is added to your current end date, :date.',
        'pay' => 'Pay securely with iyzico',
        'pay_amount' => 'Pay :amount',
        'trust' => [
            'secure' => 'Paid with iyzico and 3-D Secure',
            'card' => 'Menitra never stores your card details',
            'receipt' => 'The receipt goes to your billing email',
            'no_auto' => 'No automatic charges; we remind you before the period ends',
        ],
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
        'heading' => 'Payment receipt',
        'line_description' => 'Description',
        'line_amount' => 'Amount',
        'title' => 'Receipt :number',
        'print' => 'Print',
        'number' => 'Receipt no.',
        'paid_at' => 'Paid on',
        'plan' => 'Plan',
        'period' => 'Period',
        'card' => 'Payment method',
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

    'methods' => [
        'label' => 'Payment method',
        'card' => 'Credit / debit card',
        'bank_transfer' => 'Bank transfer',
    ],

    'bank_transfer' => [
        'title' => 'Pay by bank transfer',
        'pay_amount' => 'Pay :amount by bank transfer',
        'method_help' => 'Next you will see our bank account and the payment code to write in the transfer description. Your subscription / order is updated once the payment is confirmed.',
        'trust' => 'Your payment is checked and confirmed by our team',
        'amount' => 'Amount',
        'reference' => 'Payment code',
        'reference_help' => 'Write this code in the transfer description.',
        'purpose' => 'Payment for',
        'instructions' => 'Instructions',
        'sections' => [
            'payment' => 'Payment details',
            'account' => 'Bank account',
        ],
        'states' => [
            'awaiting' => [
                'title' => 'Waiting for your payment',
                'body' => 'Send the amount to the account below and write :reference in the description. Then click "I have paid".',
            ],
            'notified' => [
                'title' => 'We received your payment notice',
                'body' => 'Your payment :reference is being checked. It is confirmed once the amount reaches our account and we will email you.',
            ],
            'succeeded' => [
                'title' => 'Your payment was confirmed, thank you!',
                'body' => 'Your bank transfer :reference was confirmed.',
            ],
            'rejected' => [
                'title' => 'Your payment was not confirmed',
                'body' => 'The payment :reference was not confirmed. Reason: :reason. Please contact us with any questions.',
            ],
            'cancelled' => [
                'title' => 'Payment cancelled',
                'body' => 'The bank transfer :reference was cancelled. You can start a new payment at any time.',
            ],
        ],
        'open' => [
            'title' => 'You have a pending bank transfer',
            'body' => 'No new subscription payment can be started until the payment :reference is completed, confirmed or cancelled.',
            'body_shop' => 'No new order can be placed until the order payment :reference is completed, confirmed or cancelled.',
            'action' => 'Show transfer details',
        ],
        'actions' => [
            'notify' => 'I have paid',
            'notify_confirm' => 'Do you confirm that you sent :amount with :reference in the description? Our team will check and confirm the payment.',
            'notified' => 'Thank you. We will let you know once your payment is confirmed.',
            'cancel' => 'Cancel payment',
            'cancel_confirm' => 'This bank transfer will be cancelled. Continue only if you have not sent any money.',
            'cancelled' => 'Payment cancelled.',
        ],
        'errors' => [
            'not_open' => 'This payment is no longer pending.',
        ],
        'statuses' => [
            'awaiting' => 'Awaiting payment',
            'notified' => 'Awaiting confirmation',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
        ],
        'mail' => [
            'subject' => 'Bank transfer notice: :reference',
            'intro' => ':business reported a bank transfer.',
            'check' => 'Check the bank account and approve or reject the payment in the admin panel.',
        ],
        'admin' => [
            'singular' => 'Bank transfer',
            'plural' => 'Bank transfers',
            'purpose' => 'For',
            'notified_at' => 'Reported',
            'shop_order' => 'Stand order :number',
            'upgrade' => 'Upgrade to :plan',
            'approve' => 'Approve',
            'approve_heading' => 'Approve :reference',
            'approve_confirm' => 'Did you check that :amount arrived with this payment code? Approving applies the payment like a card payment (the subscription starts / is extended or the order is paid) and cannot be undone.',
            'approved' => 'Payment approved',
            'reject' => 'Reject',
            'reject_confirm' => 'The payment is not applied. The owner sees the reason on the payment page.',
            'reject_reason' => 'Reason',
            'reject_reason_help' => 'Shown to the business owner.',
            'rejected' => 'Payment rejected',
        ],
    ],

    'settings' => [
        'title' => 'Payment settings',
        'subheading' => 'Choose how businesses can pay and the bank account for transfers.',
        'sections' => [
            'methods' => 'Payment methods',
            'methods_help' => 'A disabled method is not offered on the payment pages and is refused on the server. With both disabled no new payment can be made.',
            'bank' => 'Bank transfer account',
            'bank_help' => 'These details and the instructions are shown to owners who choose bank transfer.',
        ],
        'fields' => [
            'card_enabled' => 'Card payments (iyzico)',
            'card_enabled_help' => 'Keep this off until the iyzico account and keys are ready.',
            'bank_transfer_enabled' => 'Bank transfer',
            'bank_transfer_enabled_help' => 'The business pays and clicks "I have paid"; the payment is approved manually on the "Bank transfers" page.',
            'bank_account_holder' => 'Account holder',
            'bank_name' => 'Bank',
            'bank_iban' => 'IBAN',
            'notification_email' => 'Notification email',
            'notification_email_help' => '"I have paid" notices go to this address. When empty, the contact email from the site settings is used.',
            'instructions' => 'Instructions',
            'instructions_help' => 'Shown below the bank account on the payment page. Available fields: :placeholders (payment code, amount, business name).',
            'instructions_placeholder' => 'Write only {reference} in the transfer description. Payments are usually confirmed within one business day.',
        ],
        'errors' => [
            'bank_account_required' => 'Account holder and IBAN are required while bank transfers are enabled.',
        ],
    ],

];
