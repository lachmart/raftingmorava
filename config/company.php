<?php

// Company contact details used in email templates (macros {{company_phone}}, {{company_email}}).
return [
    'name' => env('COMPANY_NAME', 'Rafting Morava'),
    'phone' => env('COMPANY_PHONE', '+420 000 000 000'),
    'email' => env('COMPANY_EMAIL', 'info@raftingmorava.cz'),

    // Shared secret token for receiving inquiries from WordPress (POST /api/inquiries).
    'inquiry_secret' => env('INQUIRY_WEBHOOK_SECRET', ''),

    // Secret token in the cron URL (GET /cron/{token}) – hosting runs cron over HTTP only.
    'cron_token' => env('CRON_TOKEN', ''),
];
