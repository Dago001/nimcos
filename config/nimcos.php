<?php

return [

    'name' => 'NIMCOS E-VOTING',
    'subtitle' => 'Nigeria Immigration Multi-Purpose Cooperative Society Electronic Voting Platform',
    'short_org' => 'Nigeria Immigration Multi-Purpose Cooperative Society',

    // Operational timezone for display. Everything is stored in UTC.
    'display_timezone' => env('NIMCOS_DISPLAY_TIMEZONE', 'Africa/Lagos'),

    // Demo mode: exposes demo OTPs on screen and allows test fixtures.
    // The application refuses to boot with demo mode on in production.
    'demo_mode' => (bool) env('NIMCOS_DEMO_MODE', false),

    // Purely cosmetic: shows the "DEMONSTRATION ENVIRONMENT" banner and demo-mode
    // labels in the UI. Independent of demo_mode itself, so the on-screen OTP and
    // other demo_mode behaviour can stay on for local testing without the banner.
    'show_demo_banner' => (bool) env('NIMCOS_SHOW_DEMO_BANNER', env('NIMCOS_DEMO_MODE', false)),

    'otp' => [
        'length' => 6,
        'ttl_minutes' => (int) env('NIMCOS_OTP_TTL', 5),
        'max_attempts' => (int) env('NIMCOS_OTP_MAX_ATTEMPTS', 5),
        'resend_cooldown_seconds' => 60,
        'max_requests_per_window' => 3,
        'request_window_minutes' => 15,
    ],

    'termii' => [
        'api_key' => env('TERMII_API_KEY'),
        'base_url' => env('TERMII_BASE_URL', 'https://v4.api.termii.com'),
        'sender_id' => env('TERMII_SENDER_ID', 'NIMCOS'),
        'channel' => env('TERMII_CHANNEL', 'generic'),
    ],

    'voting_session' => [
        // Idle lifetime of a ballot session; refreshed on each ballot page.
        'idle_minutes' => (int) env('NIMCOS_VOTING_SESSION_MINUTES', 10),
        'ballot_cookie' => 'nimcos_ballot',
    ],

    'admin' => [
        'require_mfa' => (bool) env('NIMCOS_REQUIRE_ADMIN_MFA', false),
        'max_failed_logins' => 5,
        'lockout_minutes' => 15,
        'password_min_length' => 12,
    ],

    'voters' => [
        // Accepted Service Number shape after trim + upper-casing.
        'service_number_pattern' => env('NIMCOS_SERVICE_NUMBER_PATTERN', '/^\d{4,5}$/'),
    ],

    'uploads' => [
        'photo_max_kb' => 3072,
        'photo_min_dimension' => 200,
        'photo_max_dimension' => 6000,
        'photo_output_size' => 600,
        'import_max_kb' => 20480,
    ],

    // Thresholds for security alerts (flagged for human review only).
    'alerts' => [
        'failed_otp_per_voter' => 5,
        'failed_lookups_per_ip' => 15,
        'window_minutes' => 30,
    ],

    'receipt_prefix' => 'NIM',

    // Behind a TLS-terminating proxy / load balancer: comma-separated IPs/CIDRs, or "*".
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    'contact' => [
        'email' => env('NIMCOS_CONTACT_EMAIL', 'support@nimcoselection.com'),
        'support' => env('NIMCOS_SUPPORT_CONTACT', 'support@nimcoselection.com'),
    ],
];
