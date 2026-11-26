<?php

return [
    'temporary_password_hours' => (int) env('TEMPORARY_PASSWORD_HOURS', 48),
    'invitation_expire_minutes' => (int) env('ACCOUNT_INVITATION_EXPIRE_MINUTES', 1440),
    'email_change_expire_minutes' => (int) env('EMAIL_CHANGE_EXPIRE_MINUTES', 60),
    'support_email' => env('FACULTYCONNECT_SUPPORT_EMAIL', env('MAIL_FROM_ADDRESS')),
];
