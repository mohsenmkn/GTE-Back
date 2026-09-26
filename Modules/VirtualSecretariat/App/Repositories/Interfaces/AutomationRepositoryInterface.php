<?php

namespace Modules\VirtualSecretariat\App\Repositories\Interfaces;

use Modules\VirtualSecretariat\App\Models\VsTemplate;

interface AutomationRepositoryInterface
{
    /**
     * ثبت نامه با mapping داینامیک
     *
     * @param array $entityMapping نگاشت فیلدها و مقادیر ثابت
     * @param array $entityData داده‌های نامه
     * @param int $creatorUserId UserID فرستنده
     * @param int $creatorRoleId RoleID فرستنده
     * @return int EntityCode تولید شده
     */
//    public function insertLetterDynamic(
//        array $entityMapping,
//        array $entityData,
//        int $creatorId,
//        int $creatorRoleId,
//        ?\Modules\VirtualSecretariat\App\Models\VsTemplate $template = null
//    ): array; // ✅ تغییر از int به array


    /**
     * ثبت گردش کار در 4 جدول: send, ActiveSends, send_receivers, ActiveSend_Receivers
     *
     * @param int $entityCode کد نامه
     * @param int $senderRoleId نقش فرستنده
     * @param int $senderUserId UserID فرستنده
     * @param int $receiverRoleId نقش گیرنده
     * @param int|null $receiverUserId UserID گیرنده (اختیاری)
     * @param int $actionCode کد اکشن
     * @param int $entityTypeCode کد نوع نامه (ETC)
     * @return int ReceiverCode تولید شده
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
    ): array;

    /**
     * دریافت وضعیت نامه از گردش کار
     *
     * @param int $entityCode کد نامه
     * @return array
     */
    public function getLetterStatus(int $entityCode): array;

    /**
     * دریافت کانکشن دیتابیس
     *
     * @return mixed
     */
    public function getConnection();


    public function getLetterWorkflow(int $entityCode): array; // ✅ متد جدید



}
