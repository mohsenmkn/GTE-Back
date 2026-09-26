<?php


namespace Modules\PreWarehouse\App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchaseAllocated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $purchase;
    public $userId;

    public function __construct($purchase, int $userId)
    {
        $this->purchase = $purchase;
        $this->userId = $userId;
    }
}
