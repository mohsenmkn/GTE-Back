<?php

return [
    'sms' => [
        'enabled' => env('COMPLAINT_SMS_ENABLED', true),

        // اگر خواستی برای شکایت template خاص راه‌پیام استفاده شود
        // در غیر این صورت null بماند تا از template پیش‌فرض MsgwayService استفاده کند
        'template_id' => env('COMPLAINT_SMS_TEMPLATE_ID', null),
    ],

    'attachment' => [
        'max_kb' => env('COMPLAINT_ATTACHMENT_MAX_KB', 5120),
        'mimes' => [
            'jpg',
            'jpeg',
            'png',
            'gif',
            'pdf',
            'doc',
            'docx',
            'xls',
            'xlsx',
            'zip',
            'rar',
            'mp4',
        ],
    ],
];
