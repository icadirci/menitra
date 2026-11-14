<?php

declare(strict_types=1);

// Merged with Laravel's built-in auth.php (failed, password, throttle).
return [

    'brand' => 'Restano',

    'fields' => [
        'name' => 'Your name',
        'email' => 'Email address',
        'password' => 'Password',
        'password_confirmation' => 'Confirm password',
        'business_name' => 'Business name',
        'slug' => 'Menu address',
    ],

    'register' => [
        'title' => 'Create your business account',
        'subtitle' => 'Start your free trial. No credit card required.',
        'slug_help' => 'Your menu will be available at :example. Leave empty to generate it from your business name.',
        'slug_example' => 'your-business',
        'slug_format' => 'The menu address may contain only lowercase letters, numbers and single hyphens.',
        'slug_reserved' => 'This menu address is reserved. Please choose another one.',
        'submit' => 'Create account',
        'have_account' => 'Already have an account?',
        'login' => 'Log in',
    ],

    'forgot' => [
        'title' => 'Forgot your password?',
        'subtitle' => 'Enter your email address and we will send you a link to choose a new password.',
        'submit' => 'Send reset link',
        'back' => 'Back to login',
    ],

    'reset' => [
        'title' => 'Choose a new password',
        'submit' => 'Reset password',
    ],

    'verify' => [
        'title' => 'Verify your email address',
        'body' => 'We sent a verification link to your email address. Please click it to confirm your account.',
        'sent' => 'A new verification link has been sent to your email address.',
        'resend' => 'Resend verification email',
        'logout' => 'Log out',
    ],

];
