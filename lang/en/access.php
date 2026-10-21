<?php

return [
    'welcome' => 'Welcome',
    'sign_in_title' => 'Sign in to the reservations calendar',
    'login' => 'Email or username',
    'password' => 'Password',
    'sign_in' => 'Sign in',
    'signing_in' => 'Signing in…',
    'administration_only' => 'For Al Qasr Al Malaki administration only',
    'errors' => [
        'invalid_credentials' => 'Wrong email/username or password.',
        'account_disabled' => 'This account is disabled. Contact the admin.',
        'too_many_attempts' => 'Too many attempts. Please wait a few minutes and try again.',
        'no_profile' => 'This account is not set up for the app yet. Ask the admin to add you.',
        'not_configured' => 'The app is not connected to Firebase yet (missing FIREBASE_API_KEY / FIREBASE_PROJECT_ID).',
        'offline' => 'Signing in needs an internet connection.',
        'unknown' => 'Could not sign in. Please try again.',
    ],
    'pending_title' => 'Waiting for approval',
    'pending_body' => 'This device is not tied to your account yet. We sent a request to the admin — this screen continues automatically once it is approved.',
    'pending_device' => 'Device',
    'pending_check' => 'Check again',
    'denied_title' => 'Request declined',
    'denied_body' => 'The admin declined this device. Contact the admin if you think this is a mistake.',
    'use_other_account' => 'Use another account',
    'revoked' => 'You were signed out because this device or account is no longer authorised.',
];
