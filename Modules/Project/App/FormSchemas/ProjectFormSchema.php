<?php
// app/FormSchemas/ProjectFormSchema.php

namespace Modules\Project\App\FormSchemas;

use Modules\Company\App\Models\Company;
use Modules\Auth\App\Models\User;

class ProjectFormSchema
{
public static function fields(): array
{

$companies = Company::select('id', 'name')
->orderBy('name')
->get()
->map(fn($c) => ['label' => $c->name, 'value' => $c->id])
->toArray();

// گرفتن لیست کاربران برای انتخاب مدیر
$managers = User::select('id', 'name')
->orderBy('name')
->get()
->map(fn($u) => ['label' => $u->name, 'value' => $u->id])
->toArray();

return [
[
'name'=> 'company_id',
'label'       => 'شرکت',
'type'        => 'select',
'required'    => true,
'options'     => $companies,
'placeholder' => 'شرکت را انتخاب کنید',
],
[
'name'        => 'code',
'label'       => 'کد پروژه',
'type'        => 'text',
'required'    => true,
'placeholder' => 'مثال: PRJ-001',
'rules'       => ['max' => 50],
],
[
'name'        => 'name',
'label'       => 'نام پروژه',
'type'        => 'text',
'required'    => true,
'placeholder' => 'نام پروژه را وارد کنید',
'rules'       => ['max' => 255],
],
[
'name'     => 'type',
'label'    => 'نوع پروژه',
'type'     => 'select',
'required' => true,
'options'  => [
['label' => 'مسکونی','value' => 'residential'],
['label' => 'تجاری',     'value' => 'commercial'],
['label' => 'صنعتی',     'value' => 'industrial'],
],
],
[
'name'        => 'location',
'label'       => 'موقعیت مکانی',
'type'        => 'text',
'required'    => false,
'placeholder' => 'آدرس یا موقعیت پروژه',
'rules'       => ['max' => 255],
],
[
 'name' => 'start_date',
'label' => 'تاریخ شروع',
'type' => 'date',
'required' => true,
'placeholder' => 'تاریخ شروع پروژه را انتخاب کنید'
],
[
'name'     => 'end_date',
'label'    => 'تاریخ پایان',
'type' => 'date',
'required' => true,
'placeholder' => 'تاریخ پایان پروژه را انتخاب کنید'
],
[
'name'     => 'total_budget',
'label'    => 'بودجه کل (ریال)',
'type' => 'amount',
'required' => false,
'rules'    => ['min' => 0],
],
[
'name'     => 'status',
'label'    => 'وضعیت',
'type'     => 'select',
'required' => true,
'default'  => 'planning',
'options'  => [
['label' => 'در حال برنامه‌ریزی', 'value' => 'planning'],
['label' => 'فعال','value' => 'active'],
['label' => 'متوقف',               'value' => 'on_hold'],
['label' => 'تکمیل‌شده',            'value' => 'completed'],
['label' => 'لغوشده',              'value' => 'cancelled'],
],
],
[
'name'        => 'manager_id',
'label'       => 'مدیر پروژه',
'type'        => 'select',
'required'    => false,
'options'     => $managers,
'placeholder' => 'مدیر را انتخاب کنید',
],
[
'name'        => 'description',
'label'       => 'توضیحات',
'type'        => 'textarea',
'required'    => false,
'placeholder' => 'توضیحات تکمیلی پروژه...',
],
];
}
}
