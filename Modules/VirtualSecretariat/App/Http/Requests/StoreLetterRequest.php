<?php

namespace Modules\VirtualSecretariat\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\VirtualSecretariat\App\Models\VsTemplate;

class StoreLetterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'template_id' => 'required|exists:vs_templates,id',
            'subject' => 'required|string|max:500',

            // ✅ فیلدهای جدید - حتماً باید اینجا باشند
            'body' => 'required|string',
            'receiver_org' => 'required|string|max:255',
            'receiver_name' => 'required|string|max:255',

            // فیلدهای اختیاری
            'organization' => 'nullable|string|max:255',
            'certificate_type' => 'nullable|string|max:255',
            'receiver' => 'nullable|string|max:255',
            'request_data' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'template_id.required' => 'انتخاب نوع نامه الزامی است',
            'template_id.exists' => 'قالب انتخابی معتبر نیست',
            'subject.required' => 'موضوع نامه الزامی است',
            'body.required' => 'متن نامه الزامی است',
            'receiver_org.required' => 'نام سازمان گیرنده الزامی است',
            'receiver_name.required' => 'نام گیرنده نامه الزامی است',
        ];
    }

    protected function prepareForValidation(): void
    {
        // بررسی فعال بودن template
        $template = VsTemplate::find($this->template_id);

        if (!$template || !$template->is_active) {
            $this->merge(['template_id' => null]);
        }
    }
}
