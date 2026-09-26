<?php

namespace Modules\Complaint\App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Complaint\App\Models\Complaint;
use Modules\Complaint\App\Models\ComplaintReply;

class ComplaintReplied
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Complaint $complaint,
        public ComplaintReply $reply
    ) {
    }
}
