<?php


namespace Modules\VirtualSecretariat\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action_code' => $this->action_code,
            'action_name' => $this->action_name,
            'receiver_role_id' => $this->receiver_role_id,
            'state' => $this->state,
            'state_label' => $this->getStateLabel(),
            // ✅ اصلاح: استفاده از jdate()
            'receive_date' => $this->receive_date ? jdate($this->receive_date)->format('Y/m/d H:i') : null,
            'response_date' => $this->response_date ? jdate($this->response_date)->format('Y/m/d H:i') : null,
            'response_text' => $this->response_text,
        ];
    }

    protected function getStateLabel(): string
    {
        $labels = [
            'waiting' => 'در انتظار',
            'in_progress' => 'در حال بررسی',
            'finished' => 'پایان یافته',
            'rejected' => 'رد شده',
        ];

        return $labels[$this->state] ?? $this->state;
    }
}
