<?php

namespace Modules\Library\App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Library\App\Services\Sms\SmsManager;

class SendTestSms extends Command
{
    protected $signature = 'library:test-sms {mobile} {message?}';
    protected $description = 'ارسال پیامک تست به یک شماره';

    private SmsManager $smsManager;

    public function __construct(SmsManager $smsManager)
    {
        parent::__construct();
        $this->smsManager = $smsManager;
    }

    public function handle(): int
    {
        $mobile = $this->argument('mobile');
        $message = $this->argument('message') ?? 'این یک پیامک تست از سیستم کتابخانه گهرترابر است.';

        $this->info("ارسال پیامک تست به: {$mobile}");
        $this->info("متن: {$message}");
        $this->newLine();

        $result = $this->smsManager->send($mobile, $message);

        if ($result['success']) {
            $this->info("✅ پیامک با موفقیت ارسال شد!");
            $this->info("Message ID: {$result['message_id']}");
        } else {
            $this->error(" خطا در ارسال: {$result['error']}");
        }

        return Command::SUCCESS;
    }
}
