<?php

namespace Modules\TripExpense\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,

            // ✅ اصلاح: نمایش کامل اطلاعات سفر
            'trip' => $this->when($this->trip, function () {
                return [
                    'id' => $this->trip->id,
                    'origin' => $this->trip->origin,
                    'destination' => $this->trip->destination,
                    'start_date' => $this->trip->start_date?->format('Y-m-d'),
                    'end_date' => $this->trip->end_date?->format('Y-m-d'),
                    'status' => $this->trip->status,

                    // ✅ اصلاح: نمایش driver به صورت مستقیم
                    'driver' => $this->when($this->trip->driver, function () {
                        return [
                            'id' => $this->trip->driver->id,
                            'name' => $this->trip->driver->name,
                            'personnel_code' => $this->trip->driver->personnel_code,
                            'mobile' => $this->trip->driver->mobile,
                        ];
                    }),

                    // ✅ اصلاح: نمایش truck به صورت مستقیم
                    'truck' => $this->when($this->trip->truck, function () {
                        return [
                            'id' => $this->trip->truck->id,
                            'code' => $this->trip->truck->code,
                            'plate_number' => $this->trip->truck->plate_number,
                        ];
                    }),
                ];
            }),

            'category_id' => $this->category_id,
            'category' => new ExpenseCategoryResource($this->whenLoaded('category')),
            'amount' => (float) $this->amount,
            'amount_formatted' => number_format($this->amount) . ' تومان',
            'receipt_path' => $this->receipt_path,
            'receipt_url' => $this->receipt_path
                ? asset('storage/' . $this->receipt_path)
                : null,
            'expense_date' => $this->expense_date?->format('Y-m-d'),
            'description' => $this->description,
            'status' => $this->status,
            'status_label' => $this->getStatusLabels()[$this->status] ?? $this->status,
            'exceeded_limit' => $this->exceeded_limit,
            'warning_message' => $this->warning_message,
            'approver' => $this->when($this->approver, function () {
                return [
                    'id' => $this->approver->id,
                    'name' => $this->approver->name,
                ];
            }),
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function getStatusLabels(): array
    {
        return [
            'pending' => 'در انتظار تایید',
            'approved' => 'تایید شده',
            'rejected' => 'رد شده',
        ];
    }
}
