<?php

namespace Modules\PettyCash\Transformers;


use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Document\Transformers\DocumentResource;
use Modules\WBS\Transformers\WbsItemResource;

class PettyCashTransactionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'               => $this->id,
            'petty_cash_id'    => $this->petty_cash_id,
            'wbs_item_id'      => $this->wbs_item_id,
            'wbs_item'         => new WbsItemResource($this->whenLoaded('wbsItem')),
            'type'             => $this->type,
            'amount'           => $this->amount,
            'description'      => $this->description,
            'transaction_date' => $this->transaction_date?->toDateString(),
            'reference_number' => $this->reference_number,
            'created_by'       => $this->creator?->name,
            'documents'        => DocumentResource::collection($this->whenLoaded('documents')),
            'created_at'       => $this->created_at->toDateString(),
        ];
    }
}
