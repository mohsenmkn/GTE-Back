<?php
// Modules/WBS/Transformers/TaskResource.php

namespace Modules\WBS\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'wbs_item_id'      => $this->wbs_item_id,
            'title'            => $this->title,
            'description'      => $this->description,
            'priority'         => $this->priority,
            'status'           => $this->status,
            'progress_percent' => $this->progress_percent,
            'start_date'       => $this->start_date?->toDateString(),
            'due_date'         => $this->due_date?->toDateString(),
            'completed_at'     => $this->completed_at?->toDateTimeString(),
            'estimated_hours'  => $this->estimated_hours,
            'actual_hours'     => $this->actual_hours,
            'is_overdue'       => $this->isOverdue(),
            'assigned_user'    => $this->whenLoaded('assignedUser', fn() => [
                'id'   => $this->assignedUser->id,
                'name' => $this->assignedUser->name,
            ]),
            'assignees'        => $this->whenLoaded('assignees', fn() =>
            $this->assignees->map(fn($user) => [
                'id'   => $user->id,
                'name' => $user->name,
                'role' => $user->pivot->role,
            ])
            ),
            'created_at'       => $this->created_at->toDateTimeString(),
        ];
    }
}
