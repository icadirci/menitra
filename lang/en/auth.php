<?php

declare(strict_types=1);

// Merged with Laravel's built-in auth.php (failed, password, throttle).
return [

    'brand' => 'Menitra',

    'home_link' => 'Menitra home page',
    'password_toggle' => 'Show or hide password',

    'fields' => [
        'name' => 'Your name',
        'email' => 'Email address',
        'password' => 'Password',
        'password_confirmation' => 'Confirm password',
        'business_name' => 'Business name',
        'slug' => 'Menu address',
    ],

    // Terms of use acceptance (registration and new accounts from an
    // invitation). The privacy notice is information, not consent: a note only.
    'terms' => [
        'accept_before' => 'I have read and accept the ',
        'accept_link' => 'terms of use',
        'accept_after' => '.',
        'privacy_before' => 'How we process your personal data is explained in our ',
        'privacy_link' => 'privacy notice',
        'privacy_after' => '.',
        'new_tab' => '(opens in a new tab)',
        'required' => 'Please accept the terms of use to continue.',
    ],

    'register' => [
        'title' => 'Create your business account',
        'subtitle' => 'Start your free trial. No credit card required.',
        'slug_help' => 'Your menu will be available at :example. Leave empty to generate it from your business name.',
        'slug_example' => 'your-business',
        'slug_format' => 'The menu address may contain only lowercase letters, numbers and single hyphens.',
        'slug_reserved' => 'This menu address is reserved. Please choose another one.',
        'selected_plan' => 'Selected plan: :plan · :days-day free trial',
        'submit' => 'Create account',
        'have_account' => 'Already have an account?',
        'login' => 'Log in',
        'reassurance' => 'No credit card needed. Cancel any time.',
        'aside_title' => 'Add your dishes, pick a theme, print your QR. Guests start browsing the same day.',
        'aside_points' => [
            'Update prices and sold-out items in seconds',
            'Turkish, English and Arabic menus from one place',
            'One QR code per table that never needs reprinting',
        ],
    ],

    'forgot' => [
        'title' => 'Forgot your password?',
        'subtitle' => 'Enter your email address and we will send you a link to choose a new password.',
        'submit' => 'Send reset link',
        'back' => 'Back to login',
        'tip_expiry' => 'The link works for :minutes minutes and can be used once.',
        'tip_spam' => "Can't find it? Check your spam or promotions folder.",
        'sent_title' => 'Check your inbox',
        'sent_subtitle' => 'If an account exists for that address, we have sent it a password reset link.',
        'sent_steps' => [
            'Open the email from Menitra',
            'Tap the reset link',
            'Log in with your new password',
        ],
        'wrong_address' => 'Wrong address?',
        'use_another' => 'Use a different email',
    ],

    'reset' => [
        'title' => 'Choose a new password',
        'subtitle' => 'Choose a strong password you don\'t use anywhere else.',
        'submit' => 'Reset password',
    ],

    'verify' => [
        'title' => 'Verify your email address',
        'body' => 'We sent a verification link to your email address. Please click it to confirm your account.',
        'sent' => 'A new verification link has been sent to your email address.',
        'resend' => 'Resend verification email',
        'logout' => 'Log out',
    ],

    'mail' => [
        'greeting' => 'Hello :name,',
        'fallback' => 'If the button does not work, paste this link into your browser:',
        'signature' => 'The Menitra team',
        'verify' => [
            'subject' => 'Verify your email address',
            'intro' => 'Please verify your email address to activate your Menitra account.',
            'cta' => 'Verify my email address',
            'expiry' => 'The link is valid for :count minutes.',
            'outro' => 'If you did not create this account, you can ignore this email.',
        ],
        'reset' => [
            'subject' => 'Your password reset link',
            'intro' => 'We received a request to reset the password of your account.',
            'cta' => 'Choose a new password',
            'expiry' => 'The link is valid for :count minutes and can be used once.',
            'outro' => 'If you did not request this, you can ignore this email; your password stays the same.',
        ],
    ],

];
