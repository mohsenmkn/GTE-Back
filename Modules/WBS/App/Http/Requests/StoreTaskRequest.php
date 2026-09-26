<?php

namespace Modules\WBS\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'            => 'required|string|max:255',
            'description'      => 'nullable|string',
            'assigned_to'      => 'nullable|exists:users,id',
            'start_date'       => 'nullable|date',
            'due_date'         => 'nullable|date|after_or_equal:start_date',
            'priority'         => 'required|in:low,medium,high,critical',
            'status'           => 'required|in:pending,in_progress,completed,blocked',
            'progress_percent' => 'nullable|numeric|min:0|max:100',
            'estimated_hours'  => 'nullable|integer|min:0',
            'actual_hours'     => 'nullable|integer|min:0',
            'notes'            => 'nullable|string',
            'assignees'        => 'nullable|array',
            'assignees.*.user_id' => 'required|exists:users,id',
            'assignees.*.role'    => 'required|in:assignee,reviewer,observer',
        ];
    }
}
