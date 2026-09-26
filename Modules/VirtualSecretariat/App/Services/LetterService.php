<?php

namespace Modules\VirtualSecretariat\App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Auth\App\Models\User;
use Modules\VirtualSecretariat\App\Models\VsRequest;
use Modules\VirtualSecretariat\App\Models\VsTemplate;
use Modules\VirtualSecretariat\App\Repositories\SqlServerAutomationRepository;

class LetterService
{
    protected SqlServerAutomationRepository $automationRepo;

    public function __construct(SqlServerAutomationRepository $automationRepo)
    {
        $this->automationRepo = $automationRepo;
    }

    public function createRequest(array $data, int $userId): VsRequest
    {
        $template = VsTemplate::findOrFail($data['template_id']);

        if (!$template->isActive()) {
            throw new \Exception('این قالب غیرفعال است');
        }

        $bodyText = $this->resolveBodyText($data, $userId);
        $entityData = $this->prepareEntityData($template, $data, $bodyText, $userId);

        // ✅ شروع تراکنش MySQL
        DB::beginTransaction();
        $sqlServerResult = null;

        try {
            // 1. ثبت اولیه درخواست در MySQL با وضعیت pending
            $request = VsRequest::create([
                'user_id' => $userId,
                'template_id' => $template->id,
                'title' => $data['subject'] ?? '',
                'request_data' => $data,
                'status' => 'pending',
            ]);

            // 2. ثبت در SQL Server (اگر اینجا خطا دهد، خود Repository Rollback می‌کند و Exception پرتاب می‌کند)
            $sqlServerResult = $this->automationRepo->createLetterAndWorkflow(
                $template->entity_mapping,
                $entityData,
                $template->virtual_personnel_user_id,
                $template->virtual_personnel_role_id,
                $template->receiver_role_id,
                $template->receiver_user_id,
                $template->target_action_code,
                $template->entity_type_code,
                $template
            );

            // 3. به‌روزرسانی درخواست MySQL با اطلاعات موفقیت‌آمیز SQL Server
            $request->update([
                'status' => 'sent',
                'automation_entity_code' => $sqlServerResult['entity_code'],
                'automation_letter_number' => $sqlServerResult['entity_number'],
                'sent_at' => now(),
            ]);

            // ✅ کامیت نهایی MySQL
            DB::commit();
            return $request->fresh();

        } catch (\Exception $e) {
            // ✅ Rollback تراکنش MySQL
            DB::rollBack();

            // ✅ اگر SQL Server موفق بود اما MySQL شکست خورد، رکوردهای SQL Server را پاک کن
            if ($sqlServerResult && isset($sqlServerResult['entity_code'])) {
                Log::warning('MySQL failed after SQL Server success. Triggering cleanup for EntityCode: ' . $sqlServerResult['entity_code']);
                $this->automationRepo->cleanupLetter($sqlServerResult['entity_code']);
            }

            Log::error('LetterService::createRequest failed: ' . $e->getMessage());
            throw $e;
        }
    }

    protected function resolveBodyText(array $data, int $userId): string
    {
        if (!empty($data['body'])) {
            return $data['body'];
        }
        return $this->generateLetterBody($data, $userId);
    }

    protected function generateLetterBody(array $data, int $userId): string
    {
        $user = User::find($userId);
        $organization = $data['receiver_org'] ?? $data['organization'] ?? '';
        return sprintf(
            "اینجانب %s درخواست گواهی جهت ارائه به %s را دارم. لطفا نسبت به صدور اقدام نمایید.",
            $user->full_name ?? $user->name,
            $organization
        );
    }

    protected function prepareEntityData(VsTemplate $template, array $data, string $bodyText, int $userId): array
    {
        $mapping = $template->entity_mapping;
        $fields = $mapping['fields'] ?? [];
        $staticValues = $mapping['static_values'] ?? [];
        $entityData = [];

        if (isset($fields['subject'])) $entityData[$fields['subject']] = $data['subject'] ?? '';
        if (isset($fields['mozoa'])) $entityData[$fields['mozoa']] = $data['subject'] ?? '';
        if (isset($fields['body'])) $entityData[$fields['body']] = $bodyText;
        if (isset($fields['sender'])) $entityData[$fields['sender']] = $data['receiver_org'] ?? $data['organization'] ?? null;
        if (isset($fields['receiver'])) $entityData[$fields['receiver']] = $data['receiver_name'] ?? $data['receiver'] ?? null;
        if (isset($fields['date'])) $entityData[$fields['date']] = now()->format('Y-m-d 00:00:00');

        foreach ($staticValues as $column => $value) {
            if (in_array($column, ['CreatorID', 'CreatorRoleID', 'Date'])) continue;
            $entityData[$column] = is_numeric($value) ? $value + 0 : $value;
        }

        return $entityData;
    }
}
