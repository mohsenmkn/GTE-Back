<?php

return [
    'window_seconds' => (int) env('SECURITY_ALERT_WINDOW_SECONDS', 600),
    'failed_attempts_per_ip' => (int) env('SECURITY_ALERT_FAILED_PER_IP', 10),
    'failed_attempts_per_subject' => (int) env('SECURITY_ALERT_FAILED_PER_SUBJECT', 5),
    'otp_sends_per_ip' => (int) env('SECURITY_ALERT_OTP_SENDS_PER_IP', 5),
    'alert_cooldown_seconds' => (int) env('SECURITY_ALERT_COOLDOWN_SECONDS', 900),
];
