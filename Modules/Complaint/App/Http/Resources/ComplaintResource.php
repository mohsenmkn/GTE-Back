<?php

namespace Modules\Complaint\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Complaint\App\Enums\ComplaintPriority;
use Modules\Complaint\App\Enums\ComplaintStatus;

class ComplaintResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $canManage = $user?->can('complaints.manage');

        return [
            'id' => $this->id,
            'tracking_code' => $this->tracking_code,
            'subject' => $this->subject,
            'description' => $this->description,

            'status' => $this->status?->value,
            'status_label' => $this->status instanceof ComplaintStatus
                ? $this->status->label()
                : null,

            'priority' => $this->priority?->value,
            'priority_label' => $this->priority instanceof ComplaintPriority
                ? $this->priority->label()
                : null,

            'jalali_date' => $this->jalali_date,
            'answered_at' => $this->answered_at?->toDateTimeString(),
            'resolved_at' => $this->resolved_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),

            'category' => [
                'id' => $this->complaint_category_id,
                'title' => $this->whenLoaded('category', fn () => $this->category?->title),
                'full_title' => $this->whenLoaded('category', function () {
                    $titles = [];
                    $category = $this->category;

                    while ($category) {
                        array_unshift($titles, $category->title);
                        $category = $category->parent;
                    }

                    return implode(' / ', $titles);
                }),
            ],

            'complainant' => $canManage ? [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'mobile' => $this->user?->mobile,
                'personnel_code' => $this->user?->personnel_code,
            ] : null,

            'replies' => $this->whenLoaded('replies', function () use ($canManage) {
                return $this->replies
                    ->filter(fn ($reply) => $canManage || ! $reply->is_internal)
                    ->map(fn ($reply) => [
                        'id' => $reply->id,
                        'content' => $reply->content,
                        'is_internal' => $reply->is_internal,
                        'sent_sms' => $reply->sent_sms,
                        'created_at' => $reply->created_at?->toDateTimeString(),
                        'replier' => $canManage ? [
                            'id' => $reply->user?->id,
                            'name' => $reply->user?->name,
                        ] : null,
                    ])
                    ->values();
            }),

            // ✅ جدید: معاونت مقصد
            'organizational_unit' => $this->whenLoaded('organizationalUnit', function () {
                return [
                    'id' => $this->organizationalUnit->id,
                    'title' => $this->organizationalUnit->title,
                ];
            }),
            // ✅ جدید
            // ✅ جدید: مسئول پیگیری
            'assigned_user' => $this->whenLoaded('assignedUser', function () {
                return [
                    'id' => $this->assignedUser->id,
                    'name' => $this->assignedUser->name,
                ];
            }),
            // ✅ جدید: تاریخ ارجاع
            'assigned_at' => $this->assigned_at?->format('Y-m-d H:i:s'),
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'personnel_code' => $this->user->personnel_code,
                ];
            }),
            'attachments' => $this->whenLoaded('attachments', function () {
                return $this->attachments->map(fn ($attachment) => [
                    'id' => $attachment->id,
                    'file_name' => $attachment->file_name,
                    'file_path' => $attachment->file_path,
                    'file_type' => $attachment->file_type,
                    'file_size' => $attachment->file_size,
                    'created_at' => $attachment->created_at?->toDateTimeString(),
                ])->values();
            }),
        ];
    }
}
