<?php

namespace Modules\PettyCash\App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\PettyCash\App\Models\PettyCash;
use Modules\PettyCash\App\Models\PettyCashTransaction;
use Modules\PettyCash\App\Http\Requests\StorePettyCashTransactionRequest;
use Modules\PettyCash\Transformers\PettyCashTransactionResource;

class PettyCashTransactionController extends Controller
{
    public function index(Request $request, PettyCash $pettyCash)
    {
        $query = $pettyCash->transactions()
            ->with(['wbsItem', 'creator', 'documents'])
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->wbs_item_id, fn($q) => $q->where('wbs_item_id', $request->wbs_item_id))
            ->when($request->date_from, fn($q) => $q->whereDate('transaction_date', '>=', $request->date_from))
            ->when($request->date_to, fn($q) => $q->whereDate('transaction_date', '<=', $request->date_to));

        return PettyCashTransactionResource::collection(
            $query->latest('transaction_date')->paginate($request->per_page ?? 20)
        );
    }

    public function store(StorePettyCashTransactionRequest $request, PettyCash $pettyCash)
    {
        if (!$pettyCash->is_active) {
            return response()->json(['message' => 'تنخواه غیرفعال است.'], 422);
        }

        return DB::transaction(function () use ($request, $pettyCash) {
            $data = $request->validated();
            $data['petty_cash_id'] = $pettyCash->id;
            $data['created_by']    = $request->user()->id;

            // ✅ بررسی موجودی فقط برای expense
            if ($data['type'] === 'expense' && $pettyCash->current_balance < $data['amount']) {
                return response()->json(['message' => 'موجودی تنخواه کافی نیست.'], 422);
            }

            $transaction = PettyCashTransaction::create($data);

            return new PettyCashTransactionResource(
                $transaction->load(['wbsItem', 'creator'])
            );
        });
    }

    public function show(PettyCash $pettyCash, PettyCashTransaction $transaction)
    {
        $this->ensureBelongs($pettyCash, $transaction);
        $transaction->load(['wbsItem', 'creator', 'documents']);
        return new PettyCashTransactionResource($transaction);
    }

    public function destroy(PettyCash $pettyCash, PettyCashTransaction $transaction)
    {
        $this->ensureBelongs($pettyCash, $transaction);

        return DB::transaction(function () use ($pettyCash, $transaction) {
            // ✅ برگرداندن موجودی بر اساس نوع
            if ($transaction->type === 'expense') {
                $pettyCash->increment('current_balance', $transaction->amount);
            } else {
                $pettyCash->decrement('current_balance', $transaction->amount);
            }

            $transaction->delete();
            return response()->json(['message' => 'تراکنش حذف شد.'], 200);
        });
    }

    public function summary(PettyCash $pettyCash)
    {
        $transactions = $pettyCash->transactions();

        return response()->json([
            'initial_balance'    => $pettyCash->initial_balance,
            'current_balance'    => $pettyCash->current_balance,
            'ceiling'            => $pettyCash->ceiling,
            'total_expenses'     => (clone $transactions)->where('type', 'expense')->sum('amount'),
            'total_replenishments' => (clone $transactions)->where('type', 'replenishment')->sum('amount'),
            'transactions_count' => (clone $transactions)->count(),
            'by_wbs_item'        => (clone $transactions)
                ->where('type', 'expense')
                ->with('wbsItem:id,code,name')
                ->get()
                ->groupBy('wbs_item_id')
                ->map(fn($group) => [
                    'wbs_item' => $group->first()->wbsItem?->only(['id', 'code', 'name']),
                    'total'    => $group->sum('amount'),
                    'count'    => $group->count(),
                ]),
        ]);
    }

    private function ensureBelongs(PettyCash $pettyCash, PettyCashTransaction $transaction): void
    {
        abort_if($transaction->petty_cash_id !== $pettyCash->id, 404);
    }
}
