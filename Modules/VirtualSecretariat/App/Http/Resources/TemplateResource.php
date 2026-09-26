<?php

namespace Modules\VirtualSecretariat\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'virtual_personnel_user_id' => $this->virtual_personnel_user_id,
            'virtual_personnel_role_id' => $this->virtual_personnel_role_id,
            'receiver_role_id' => $this->receiver_role_id,
            'target_action_code' => $this->target_action_code,
            'entity_type_code' => $this->entity_type_code, // ✅ جدید
            'receiver_user_id' => $this->receiver_user_id, // ✅ جدید
            'is_active' => $this->is_active,
            'requests_count' => $this->requests_count ?? 0,
            'entity_mapping' => $this->entity_mapping,
            'workflow_mapping' => $this->workflow_mapping,
            'letter_number_format' => $this->letter_number_format,
            'created_at' => $this->created_at ? jdate($this->created_at)->format('Y/m/d H:i') : null,
            'updated_at' => $this->updated_at ? jdate($this->updated_at)->format('Y/m/d H:i') : null,
        ];
    }
}
