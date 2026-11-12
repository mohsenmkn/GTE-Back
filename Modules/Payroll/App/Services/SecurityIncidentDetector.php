<?php

namespace Modules\Payroll\App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Payroll\App\Models\SecurityAlert;

class SecurityIncidentDetector
{
    public function inspect(Request $request, string $module, string $action, string $status, ?string $subject): void
    {
        if ($module !== 'Auth') {
            return;
        }

        $ip = (string) $request->ip();
        $window = (int) config('security_alerts.window_seconds', 600);
        $windowMinutes = max(1, (int) ceil($window / 60));
        $cooldown = (int) config('security_alerts.alert_cooldown_seconds', 900);

        if ($status === 'failed') {
            $ipCount = $this->hit('auth-fail-ip', $ip, $window);
            $ipThreshold = (int) config('security_alerts.failed_attempts_per_ip', 10);
            if ($ipCount >= $ipThreshold) {
                $this->raiseOnce(
                    $request,
                    'auth.failed_attempts.ip', $ip, $cooldown, 'high',
                    'تلاش‌های ناموفق متعدد از یک IP',
                    "در {$windowMinutes} دقیقهٔ اخیر {$ipCount} رخداد ناموفق احراز هویت از این IP ثبت شده است.",
                    $ip, null, $ipCount,
                );
            }

            if ($subject) {
                $subjectCount = $this->hit('auth-fail-subject', $subject, $window);
                $subjectThreshold = (int) config('security_alerts.failed_attempts_per_subject', 5);
                if ($subjectCount >= $subjectThreshold) {
                    $this->raiseOnce(
                        $request,
                        'auth.failed_attempts.subject', $subject, $cooldown, 'medium',
                        'تلاش‌های ناموفق متعدد برای یک حساب',
                        "در {$windowMinutes} دقیقهٔ اخیر {$subjectCount} رخداد ناموفق برای این شناسه ثبت شده است.",
                        $ip, $subject, $subjectCount,
                    );
                }
            }
        }

        if ($action === 'password_reset.otp_sent' && $status === 'success') {
            $otpCount = $this->hit('auth-otp-ip', $ip, $window);
            $otpThreshold = (int) config('security_alerts.otp_sends_per_ip', 5);
            if ($otpCount >= $otpThreshold) {
                $this->raiseOnce(
                    $request,
                    'auth.otp_send_burst.ip', $ip, $cooldown, 'high',
                    'تعداد زیاد درخواست ارسال کد بازیابی',
                    "در {$windowMinutes} دقیقهٔ اخیر {$otpCount} درخواست ارسال کد بازیابی از این IP ثبت شده است.",
                    $ip, null, $otpCount,
                );
            }
        }
    }

    private function hit(string $dimension, string $value, int $window): int
    {
        $key = 'security-alert:'.$dimension.':'.hash('sha256', $value);
        RateLimiter::hit($key, $window);

        return RateLimiter::attempts($key);
    }

    private function raiseOnce(
        Request $request,
        string $rule,
        string $fingerprint,
        int $cooldown,
        string $severity,
        string $title,
        string $description,
        ?string $ip,
        ?string $subject,
        int $count,
    ): void {
        $fingerprintHash = hash('sha256', $rule.'|'.$fingerprint);
        $key = 'security-alert:cooldown:'.$fingerprintHash;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            $openAlert = SecurityAlert::query()
                ->where('fingerprint', $fingerprintHash)
                ->where('status', 'open')
                ->first();

            if ($openAlert) {
                $openAlert->increment('occurrence_count', 1, [
                    'last_detected_at' => now(),
                    'updated_at' => now(),
                ]);
                return;
            }

            $resolvedAlert = SecurityAlert::query()
                ->where('fingerprint', $fingerprintHash)
                ->where('status', 'resolved')
                ->latest('last_detected_at')
                ->first();

            if ($resolvedAlert) {
                $resolvedAlert->forceFill([
                    'severity' => $severity,
                    'title' => $title,
                    'description' => $description,
                    'source_ip' => $ip,
                    'subject' => $subject,
                    'occurrence_count' => $resolvedAlert->occurrence_count + max(1, $count),
                    'status' => 'open',
                    'first_detected_at' => now(),
                    'last_detected_at' => now(),
                    'resolved_at' => null,
                    'resolved_by' => null,
                ])->save();
            }

            return;
        }

        RateLimiter::hit($key, $cooldown);

        SecurityAlert::query()->create([
            'rule_key' => $rule,
            'fingerprint' => $fingerprintHash,
            'severity' => $severity,
            'title' => $title,
            'description' => $description,
            'source_ip' => $ip,
            'subject' => $subject,
            'occurrence_count' => $count,
            'status' => 'open',
            'first_detected_at' => now(),
            'last_detected_at' => now(),
        ]);

        app(SystemAuditLogger::class)->record(
            $request,
            'Security',
            'alert.detected',
            'failed',
            null,
            $subject,
        );
    }
}
