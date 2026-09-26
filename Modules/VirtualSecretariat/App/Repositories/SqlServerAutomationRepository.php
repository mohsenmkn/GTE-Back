<?php

namespace Modules\VirtualSecretariat\App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\VirtualSecretariat\App\Models\VsTemplate;
use Modules\VirtualSecretariat\App\Repositories\Interfaces\AutomationRepositoryInterface;

class SqlServerAutomationRepository implements AutomationRepositoryInterface
{
    protected $connection;

    public function __construct()
    {
        $this->connection = DB::connection('sqlsrv_automation');
    }

    public function getConnection()
    {
        return $this->connection;
    }

//    public function insertLetter(array $data): int
//    {
//        return $this->insertLetterDynamic([], $data);
//    }

    /**
     * ✅ متد واحد برای ثبت نامه و گردش کار در یک تراکنش (Atomic)
     * اگر هر مرحله‌ای شکست بخورد، کل تغییرات در SQL Server Rollback می‌شود.
     */
    public function createLetterAndWorkflow(
        array $entityMapping,
        array $entityData,
        int $creatorId,
        int $creatorRoleId,
        int $receiverRoleId,
        ?int $receiverUserId,
        int $actionCode,
        int $entityTypeCode,
        ?VsTemplate $template = null
    ): array {
        $this->connection->beginTransaction();

        try {
            $tableName = $entityMapping['table_name'] ?? 'Entity_Dakhli';
            $fields = $entityMapping['fields'] ?? [];
            $clientIp = request()->ip() ?? '127.0.0.1';
            $viewStates = '0000000000000000000000000000000000000000';
            $defaultDate = '1900-01-01 00:00:00.000';

            // 1. تولید شماره نامه
            $customLetterNumber = $this->generateCustomLetterNumber($template);
            if (isset($fields['entity_number'])) {
                $entityData[$fields['entity_number']] = $customLetterNumber;
            } else {
                $entityData['EntityNumber'] = $customLetterNumber;
            }

            // 2. اطمینان از فیلدهای ضروری
            $entityData['CreatorID'] = $creatorId;
            $entityData['CreatorRoleID'] = $creatorRoleId;
            $entityData['Date'] = now()->format('Y-m-d 00:00:00');

            $defaultStaticValues = [
                'IsActive' => 1, 'IsConfirm' => 1, 'CategoryCode' => -1, 'Grade' => 1,
                'IsPreNote' => 0, 'Locked' => 0, 'LocalLock' => 0, 'LocalLockUserCode' => -1,
                'LocalLockRoleID' => -1, 'LockUserCode' => -1, 'LockRoleID' => -1,
                'FieldsStatus' => '<Fields></Fields>', 'NumberOfCopies' => 1, 'Version' => 1.0,
                'OriginalVersionCode' => -1, 'IsSigned' => 0, 'SecurityLevelCode' => 1,
                'IsPrivateSearch' => 1, 'PrivateSearchUserCode' => $creatorId, 'PrivateSearchRoleID' => $creatorRoleId,
            ];
            foreach ($defaultStaticValues as $column => $value) {
                if (!isset($entityData[$column])) $entityData[$column] = $value;
            }

            // 3. ثبت در Entity_Dakhli
            $entityCode = $this->connection->table($tableName)->insertGetId($entityData);
            $firstEntityField = $fields['first_entity_code'] ?? 'FirstEntityCode';
            $this->connection->table($tableName)->where('EntityCode', $entityCode)->update([$firstEntityField => $entityCode]);

            // 4. ثبت در send (IDENTITY)
            $sendCode = $this->connection->table('Sends')->insertGetId([
                'SenderRoleID' => $creatorRoleId, 'SenderID' => $creatorId, 'EntityTypeCode' => $entityTypeCode,
                'EntityCode' => $entityCode, 'SendDate' => now(), 'SendParentCode' => -1, 'ParentReceiverCode' => -1,
                'SendType' => 0, 'Location' => $clientIp, 'Description' => null, 'SendIsRecycle' => 0,
                'SendRecycleDate' => null, 'SendIsDeleted' => 0, 'SendRecycleRefrence' => null, 'ViewInOutbox' => 1, 'WFExecutionID' => null,
            ]);

            // 5. ثبت در ActiveSends (کپی SendCode)
            $this->connection->table('ActiveSends')->insert([
                'SendCode' => $sendCode, 'SenderRoleID' => $creatorRoleId, 'SenderID' => $creatorId,
                'EntityTypeCode' => $entityTypeCode, 'EntityCode' => $entityCode, 'SendDate' => now(),
                'SendParentCode' => -1, 'ParentReceiverCode' => -1, 'SendType' => 0, 'Location' => $clientIp,
                'Description' => null, 'SendIsRecycle' => 0, 'SendRecycleDate' => null, 'SendIsDeleted' => 0,
                'SendRecycleRefrence' => null, 'ViewInOutbox' => 1, 'WFExecutionID' => null,
            ]);

            // 6. ثبت در send_receivers (IDENTITY)
            $receiverCode = $this->connection->table('Send_Receivers')->insertGetId([
                'SendCode' => $sendCode, 'ReceiverRoleID' => $receiverRoleId, 'ReceiverID' => $receiverUserId,
                'ActionCode' => $actionCode, 'Description' => 'درخواست از دبیرخانه مجازی', 'ResponseUntilDate' => null,
                'ResponseDate' => null, 'ConsiderationType' => null, 'ConsiderSendCode' => -1, 'FollowingCount' => 0,
                'ReceiveDate' => now(), 'FinishedOperation' => 0, 'ShowRejected' => 0, 'ViewStates' => $viewStates,
                'LastChangeViewStatesDate' => now(), 'FirstChangeViewStatesDate' => $defaultDate, 'WFScenarioCode' => null,
                'WFExtendedParams' => null, 'IsHidden' => 0, 'HiddenUserID' => -1, 'HiddenRoleID' => -1, 'PriorityID' => null,
            ]);

            // 7. ثبت در ActiveSend_Receivers (کپی Code و SendCode)
            $this->connection->table('ActiveSend_Receivers')->insert([
                'Code' => $receiverCode, 'SendCode' => $sendCode, 'ReceiverRoleID' => $receiverRoleId,
                'ReceiverID' => $receiverUserId, 'ActionCode' => $actionCode, 'Description' => 'درخواست از دبیرخانه مجازی',
                'ResponseUntilDate' => null, 'ResponseDate' => null, 'ConsiderationType' => null, 'ConsiderSendCode' => -1,
                'FollowingCount' => 0, 'ReceiveDate' => now(), 'FinishedOperation' => 0, 'ShowRejected' => 0,
                'ViewStates' => $viewStates, 'LastChangeViewStatesDate' => now(), 'FirstChangeViewStatesDate' => $defaultDate,
                'WFScenarioCode' => null, 'WFExtendedParams' => null, 'IsHidden' => 0, 'HiddenUserID' => -1,
                'HiddenRoleID' => -1, 'PriorityID' => null,
            ]);

            $this->connection->commit();

            return [
                'entity_code' => $entityCode,
                'entity_number' => $customLetterNumber,
            ];

        } catch (\Exception $e) {
            // ✅ اگر هر خطایی رخ دهد، کل تغییرات در SQL Server لغو می‌شود
            $this->connection->rollBack();
            Log::error('SQL Server Automation Rollback: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * ✅ متد پاکسازی در صورتی که SQL Server موفق بود اما MySQL شکست خورد
     */
    public function cleanupLetter(int $entityCode): void
    {
        try {
            $this->connection->beginTransaction();
            // حذف از جداول گردش کار
            $this->connection->table('ActiveSend_Receivers')->whereIn('SendCode', function($query) use ($entityCode) {
                $query->select('SendCode')->from('ActiveSends')->where('EntityCode', $entityCode);
            })->delete();
            $this->connection->table('send_receivers')->whereIn('SendCode', function($query) use ($entityCode) {
                $query->select('SendCode')->from('send')->where('EntityCode', $entityCode);
            })->delete();

            // حذف از جداول ارسال
            $this->connection->table('ActiveSends')->where('EntityCode', $entityCode)->delete();
            $this->connection->table('send')->where('EntityCode', $entityCode)->delete();

            // حذف از جدول اصلی
            $this->connection->table('Entity_Dakhli')->where('EntityCode', $entityCode)->delete();

            $this->connection->commit();
            Log::info("Cleanup successful for EntityCode: {$entityCode}");
        } catch (\Exception $e) {
            $this->connection->rollBack();
            Log::error('Cleanup failed for EntityCode ' . $entityCode . ': ' . $e->getMessage());
        }
    }

    /**
     * تولید شماره نامه سفارشی
     */
    protected function generateCustomLetterNumber(?VsTemplate $template): string
    {
        if (!$template || empty($template->letter_number_format)) {
            $jalaliYear = jdate(now())->format('Y');
            $maxNumber = $this->connection->table('Entity_Dakhli')
                ->where('EntityNumber', 'LIKE', $jalaliYear . '/%')
                ->where('EntityNumber', 'LIKE', '%/داخلي')
                ->selectRaw('MAX(CAST(SUBSTRING(EntityNumber, CHARINDEX(\'/\', EntityNumber) + 1, CHARINDEX(\'/\', EntityNumber, CHARINDEX(\'/\', EntityNumber) + 1) - CHARINDEX(\'/\', EntityNumber) - 1) AS INT)) AS max_num')
                ->value('max_num');
            return $jalaliYear . '/' . (($maxNumber ?? 0) + 1) . '/داخلي';
        }

        $format = $template->letter_number_format;
        $prefix = strtoupper($format['prefix'] ?? 'DOC');
        $year = $format['year'] ?? jdate(now())->format('Y');
        $startFrom = $format['start_from'] ?? 1;
        $separator = $format['separator'] ?? '/';

        $counter = \Modules\VirtualSecretariat\App\Models\VsLetterCounter::firstOrCreate(
            ['template_id' => $template->id, 'prefix' => $prefix, 'year' => $year],
            ['current_number' => $startFrom - 1]
        );

        DB::transaction(function () use ($counter) {
            $counter->lockForUpdate();
            $counter->increment('current_number');
        });

        $counter->refresh();
        return "{$prefix}{$separator}{$year}{$separator}" . str_pad($counter->current_number, 4, '0', STR_PAD_LEFT);
    }

    // ... متد getLetterWorkflow که قبلاً نوشتیم را اینجا نگه دارید ...

    /**
     * دریافت گردش کار نامه به صورت زنده از SQL Server
     *
     * @param int $entityCode کد نامه در Entity_Dakhli
     * @return array
     */
    public function getLetterWorkflow(int $entityCode): array
    {
        try {
            // کوئری اصلی با join جداول
            $rows = $this->connection->table('Send_Receivers as asr')
                ->leftJoin('ActiveSends as s', 'asr.SendCode', '=', 's.SendCode')
                ->leftJoin('Roles as r', 'asr.ReceiverRoleID', '=', 'r.Role_ID')
                ->leftJoin('Actions as a', 'asr.ActionCode', '=', 'a.ActionCode')
                ->where('s.EntityCode', $entityCode)
                ->whereNotNull('asr.ActionCode')  // حذف رکوردهایی که ActionCode NULL است
                ->where('asr.ActionCode', '!=', '')  // حذف رکوردهایی که ActionCode رشته خالی است
                ->select(
                    'asr.Code',
                    'asr.SendCode',
                    'asr.ReceiverRoleID',
                    'r.RoleName as receiver_role_name',
                    'asr.ReceiverID',
                    'asr.ActionCode',
                    'a.ActionName as action_name',
                    'asr.ConsiderationType',
                    'asr.Description',
                    'asr.FinishedOperation',
                    'asr.ReceiveDate',
                    'asr.ResponseDate',
                    'asr.ShowRejected',
                    'asr.ViewStates'
                )
                ->orderBy('asr.Code')
                ->get();

            // تبدیل داده‌ها به فرمت استاندارد
            return $rows->map(function ($item) {
                // تعیین وضعیت بر اساس FinishedOperation
                $state = $this->mapState($item->FinishedOperation ?? 0);

                return [
                    'code' => $item->Code,
                    'send_code' => $item->SendCode,
                    'receiver_role_id' => $item->ReceiverRoleID,
                    'receiver_role_name' => $item->receiver_role_name ?? 'کد نقش: ' . $item->ReceiverRoleID,
                    'receiver_id' => $item->ReceiverID ?? null,
                    'action_code' => $item->ActionCode,
                    'action_name' => $item->action_name ?? $item->ConsiderationType ?? 'اکشن کد: ' . ($item->ActionCode ?? '?'),
                    'state' => $state,
                    'state_label' => $this->mapStateLabel($state),
                    'receive_date' => $item->ReceiveDate ? $this->formatDate($item->ReceiveDate) : null,
                    'response_date' => $item->ResponseDate ? $this->formatDate($item->ResponseDate) : null,
                    'response_text' => $item->Description,
                    'is_rejected' => (bool) ($item->ShowRejected ?? 0),
                    'view_states' => $item->ViewStates,
                ];
            })->toArray();

        } catch (\Exception $e) {
            \Log::error('Error in getLetterWorkflow: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * نگاشت وضعیت FinishedOperation به state
     */
    protected function mapState(?int $finishedOperation): string
    {
        return match ($finishedOperation) {
            0 => 'waiting',        // در انتظار
            1 => 'finished',       // پایان یافته
            2 => 'rejected',       // رد شده
            default => 'in_progress', // در حال بررسی
        };
    }

    /**
     * نگاشت state به برچسب فارسی
     */
    protected function mapStateLabel(string $state): string
    {
        return match ($state) {
            'waiting' => 'در انتظار',
            'in_progress' => 'در حال بررسی',
            'finished' => 'پایان یافته',
            'rejected' => 'رد شده',
            default => $state,
        };
    }

    /**
     * فرمت‌دهی تاریخ SQL Server به شمسی
     */
    protected function formatDate($date): string
    {
        try {
            if (is_string($date)) {
                $date = new \DateTime($date);
            }
            return jdate($date)->format('Y/m/d H:i');
        } catch (\Exception $e) {
            return $date;
        }
    }

    public function getLetterStatus(int $entityCode): array
    {
        return $this->connection->table('ActiveSend_Receivers as asr')
            ->leftJoin('ActiveSends as s', 'asr.SendCode', '=', 's.SendCode')
            ->leftJoin('Actions as a', 'asr.ActionCode', '=', 'a.ActionCode')
            ->where('s.EntityCode', $entityCode)
            ->select(
                'asr.Code',
                'asr.ReceiverRoleID',
                'asr.ActionCode',
                'a.ActionName',
                'asr.FinishedOperation',
                'asr.ReceiveDate',
                'asr.ResponseDate',
                'asr.Description'
            )
            ->get()
            ->toArray();
    }

}
