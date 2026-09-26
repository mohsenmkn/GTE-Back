<?php


namespace Modules\VirtualSecretariat\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminLetterRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'automation_letter_number' => $this->automation_letter_number,
            'automation_entity_code' => $this->automation_entity_code,
            'error_message' => $this->error_message,
            'admin_note' => $this->admin_note ?? null,

            // اطلاعات کاربر
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'personnel_code' => $this->user->personnel_code ?? null,
            ] : null,

            // اطلاعات قالب
            'template' => $this->template ? [
                'id' => $this->template->id,
                'name' => $this->template->name,
                'slug' => $this->template->slug,
            ] : null,

            'request_data' => $this->request_data,
            'workflow_logs' => WorkflowLogResource::collection($this->whenLoaded('workflowLogs')),

            // تاریخ‌ها
            'sent_at' => $this->sent_at ? jdate($this->sent_at)->format('Y/m/d H:i') : null,
            'completed_at' => $this->completed_at ? jdate($this->completed_at)->format('Y/m/d H:i') : null,
            'created_at' => $this->created_at ? jdate($this->created_at)->format('Y/m/d H:i') : null,
            'updated_at' => $this->updated_at ? jdate($this->updated_at)->format('Y/m/d H:i') : null,
        ];
    }

    protected function getStatusLabel(): string
    {
        $labels = [
            'pending' => 'در انتظار',
            'processing' => 'در حال پردازش',
            'sent' => 'ارسال شده',
            'completed' => 'تکمیل شده',
            'rejected' => 'رد شده',
            'cancelled' => 'لغو شده',
            'failed' => 'خطا',
        ];

        return $labels[$this->status] ?? $this->status;
    }
}
