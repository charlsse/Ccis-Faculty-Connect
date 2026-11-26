<?php

return [
    // Enable only after a real mail transport has been verified by the operator.
    'email_enabled' => env('WATER_EMAIL_ENABLED', false),
    'reminder_days' => [3, 0, -3],
];
