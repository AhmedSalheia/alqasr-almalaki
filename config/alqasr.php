<?php

return [
    'name' => [
        'en' => 'Alqasr Almalaky Calendar',
        'ar' => 'تقويم القصر الملكي',
    ],

    'firebase' => [
        'api_key' => env('FIREBASE_API_KEY'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'database_id' => env('FIREBASE_DATABASE_ID', '(default)'),
        // Seconds before an HTTP call to Firebase is considered failed (offline)
        'connect_timeout' => 3,
        'timeout' => 8,
    ],

    'sync_interval' => (int) env('ALQASR_SYNC_INTERVAL', 30),

    'username_domain' => env('ALQASR_USERNAME_DOMAIN', 'alqasr-almalaky.app'),

    'auto_bind_first_device' => filter_var(env('ALQASR_AUTO_BIND_FIRST_DEVICE', true), FILTER_VALIDATE_BOOL),

    'currency' => [
        'symbol' => env('ALQASR_CURRENCY_SYMBOL', '₪'),
        'code' => env('ALQASR_CURRENCY_CODE', 'ILS'),
    ],

    'locales' => ['ar', 'en'],

    // Reminder notifications before a reservation (to the person who added it + all admins)
    'reminders' => [
        'days_before' => (int) env('ALQASR_REMINDER_DAYS', 3),
        'hour' => (int) env('ALQASR_REMINDER_HOUR', 10),   // local time of day the reminder appears
        'horizon_days' => 120,                              // only schedule reservations this far ahead
    ],

    // First day of the week in the month grid: 0 = Sunday ... 6 = Saturday
    'week_starts_on' => (int) env('ALQASR_WEEK_STARTS_ON', 6),
];
