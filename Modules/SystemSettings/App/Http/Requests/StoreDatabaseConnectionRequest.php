<?php
// Modules/SystemSettings/Http/Requests/StoreDatabaseConnectionRequest.php

namespace Modules\SystemSettings\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDatabaseConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('system_settings.manage_database');
    }

    public function rules(): array
    {
        $id = $this->route('database_connection')?->id;

        return [
            'name'     => ['required', 'regex:/^[a-zA-Z0-9_]+$/', "unique:system_database_connections,name,{$id}"],
            'title'    => ['nullable', 'string', 'max:100'],
            'driver'   => ['required', 'in:sqlsrv,mysql,pgsql'],
            'host'     => ['required', 'string', 'max:100'],
            'port'     => ['nullable', 'numeric', 'between:1,65535'],
            'database' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required_if:is_edit,false', 'string'],
            'charset'  => ['nullable', 'string', 'max:20'],
            'collation'=> ['nullable', 'string', 'max:50'],
            'options'  => ['nullable', 'array'],
            'options.TrustServerCertificate' => ['boolean'],
            'options.Encrypt' => ['boolean'],
            'is_active'=> ['boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'نام اتصال فقط می‌تواند شامل حروف انگلیسی، عدد و _ باشد',
            'password.required_if' => 'وارد کردن کلمه عبور الزامی است',
        ];
    }
}
