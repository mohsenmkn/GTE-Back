<?php

namespace Modules\Complaint\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'title' => $this->title,
            'level' => $this->level,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'full_title' => $this->when($request->boolean('with_full_title'), $this->full_title),
            'children' => CategoryResource::collection($this->whenLoaded('children')),
        ];
    }
}
