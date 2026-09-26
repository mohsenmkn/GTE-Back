<?php

namespace Modules\VirtualSecretariat\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\VirtualSecretariat\App\Models\VsTemplate;
use Illuminate\Support\Facades\Log;

class StoreTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * دریافت ID قالب از مسیر، بدنه یا کوئری‌استرینگ
     */
    protected function getTemplateId(): ?int
    {
        // ۱. دریافت تمام پارامترهای مسیر
        $routeParams = $this->route() ? $this->route()->parameters() : [];
        Log::info('Route parameters:', $routeParams);

        // ۲. جستجو در پارامترهای مسیر
        foreach ($routeParams as $key => $value) {
            if ($value instanceof VsTemplate) {
                Log::info('Found model in route parameter: ' . $key);
                return $value->id;
            }
            if (is_numeric($value)) {
                Log::info('Found numeric ID in route: ' . $value);
                return (int) $value;
            }
            if (is_string($value) && !empty($value)) {
                $model = VsTemplate::where('slug', $value)->first();
                if ($model) {
                    Log::info('Found model by slug: ' . $value);
                    return $model->id;
                }
            }
        }

        // ۳. جستجو در بدنه درخواست
        $inputKeys = ['id', 'template_id', 'vs_template_id'];
        foreach ($inputKeys as $key) {
            if ($this->has($key) && is_numeric($this->input($key))) {
                Log::info('Found ID in input: ' . $key . ' = ' . $this->input($key));
                return (int) $this->input($key);
            }
        }

        Log::info('No ID found, returning null');
        return null;
    }

    public function rules(): array
    {
        $id = $this->getTemplateId();
        Log::info('Final template ID for ignore: ' . ($id ?? 'null'));

        return [
            'name' => 'required|string|max:255',

            'slug' => [
                'required',
                'string',
                'max:255',
                // ✅ استفاده از Rule::unique با ignore
                Rule::unique('vs_templates', 'slug')->ignore($id),
            ],

            'description' => 'nullable|string',
            'db_connection_name' => 'nullable|string',

            'entity_mapping' => 'required|array',
            'entity_mapping.table_name' => 'required|string',
            'entity_mapping.fields' => 'required|array',

            'workflow_mapping' => 'required|array',
            'workflow_mapping.sends_table' => 'required|string',
            'workflow_mapping.receivers_table' => 'required|string',
            'receiver_user_id' => 'nullable|integer',
            'letter_number_format' => 'nullable|array',
            'letter_number_format.prefix' => 'nullable|string|max:10|alpha',
            'letter_number_format.year' => 'nullable|string|max:10',
            'letter_number_format.start_from' => 'nullable|integer|min:1',
            'letter_number_format.separator' => 'nullable|string|max:5',
            'entity_type_code' => 'required|integer|min:1',
            'virtual_personnel_user_id' => 'required|integer',
            'virtual_personnel_role_id' => 'required|integer',
            'receiver_role_id' => 'required|integer',
            'target_action_code' => 'required|integer',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'نام قالب الزامی است',
            'slug.required' => 'شناسه یکتا (slug) الزامی است',
            'slug.unique' => 'این شناسه قبلاً استفاده شده است',
            'slug.max' => 'شناسه نباید بیشتر از ۲۵۵ کاراکتر باشد',

            'entity_mapping.required' => 'نگاشت موجودیت الزامی است',
            'entity_mapping.array' => 'نگاشت موجودیت باید به صورت آرایه باشد',
            'entity_mapping.table_name.required' => 'نام جدول موجودیت الزامی است',
            'entity_mapping.fields.required' => 'فیلدهای موجودیت الزامی است',

            'workflow_mapping.required' => 'نگاشت گردش کار الزامی است',
            'workflow_mapping.array' => 'نگاشت گردش کار باید به صورت آرایه باشد',
            'workflow_mapping.sends_table.required' => 'نام جدول ارسال الزامی است',
            'workflow_mapping.receivers_table.required' => 'نام جدول گیرندگان الزامی است',

            'letter_number_format.array' => 'فرمت شماره نامه باید به صورت آرایه باشد',
            'letter_number_format.prefix.alpha' => 'پیشوند فقط می‌تواند شامل حروف انگلیسی باشد',
            'letter_number_format.start_from.min' => 'شماره شروع باید حداقل ۱ باشد',

            'virtual_personnel_user_id.required' => 'UserID کاربر مجازی (CreatorID) الزامی است',
            'virtual_personnel_role_id.required' => 'RoleID پرسنل بدون کارتابل الزامی است',
            'receiver_role_id.required' => 'RoleID گیرنده نامه (دبیرخانه) الزامی است',
            'target_action_code.required' => 'کد اکشن هدف الزامی است',

            'entity_type_code.required' => 'کد نوع نامه (EntityTypeCode) الزامی است',
            'entity_type_code.integer' => 'کد نوع نامه باید عدد باشد',
            'entity_type_code.min' => 'کد نوع نامه باید حداقل 1 باشد',

            'receiver_user_id.integer' => 'UserID گیرنده باید عدد باشد',
        ];
    }
}
