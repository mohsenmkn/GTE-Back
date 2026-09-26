<?php

namespace Modules\Document\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'documentable_type' => $this->documentable_type,
            'documentable_id' => $this->documentable_id,
            'title' => $this->title,
            'file_name' => $this->file_name,
            'file_path' => $this->file_path,
            'file_size' => $this->file_size,
            'type' => $this->type,
            'mime_type' => $this->mime_type,
            'description' => $this->description,
            'uploaded_by' => $this->uploader?->name,
            'created_at' => $this->created_at->toDateString(),
        ];
    }
}
