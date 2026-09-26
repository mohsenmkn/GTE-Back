<?php

namespace Modules\Library\App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Library\App\Services\Sms\MsgwayService;

class SendManualNotification extends Command
{
    protected $signature = 'library:send-manual {mobile} {message}';
    protected $description = 'ارسال پیامک دستی به یک شماره (توسط مدیر)';

    private MsgwayService $smsService;

    public function __construct(MsgwayService $smsService)
    {
        parent::__construct();
        $this->smsService = $smsService;
    }

    public function handle(): int
    {
        $mobile = $this->argument('mobile');
        $message = $this->argument('message');

        $this->info("ارسال پیامک دستی به: {$mobile}");
        $this->info("متن پیام: {$message}");
        $this->newLine();

        $result = $this->smsService->send($mobile, $message);

        if ($result['success']) {
            $this->info("✅ پیامک با موفقیت ارسال شد!");
        } else {
            $this->error(" خطا در ارسال: {$result['error']}");
        }

        return Command::SUCCESS;
    }
}
