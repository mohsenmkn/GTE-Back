<?php

namespace Modules\Budget\App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Budget\App\Models\Budget;
use Modules\Budget\Transformers\BudgetResource;

class BudgetController extends Controller
{
    public function index(Request $request)
    {
        $query = Budget::query()
            ->when($request->project_id, fn($q) => $q->where('project_id', $request->project_id))
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->fiscal_year, fn($q) => $q->where('fiscal_year', $request->fiscal_year));

        return BudgetResource::collection($query->paginate($request->per_page ?? 15));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'project_id'   => 'required|exists:projects,id',
            'title'        => 'required|string|max:255',
            'type'         => 'required|in:initial,revised,contingency',
            'amount'       => 'required|numeric|min:0',
            'used_amount'  => 'nullable|numeric|min:0',
            'fiscal_year'  => 'nullable|integer|min:1400|max:1420',
            'description'  => 'nullable|string',
        ]);
        $data['used_amount'] = 0;
        $budget = Budget::create($data);
        return new BudgetResource($budget);
    }

    public function show(Budget $budget)
    {
        return new BudgetResource($budget);
    }

    public function update(Request $request, Budget $budget)
    {
        $data = $request->validate([
            'project_id'   => 'sometimes|required|exists:projects,id',
            'title'        => 'sometimes|required|string|max:255',
            'type'         => 'sometimes|required|in:initial,revised,contingency',
            'amount'       => 'sometimes|required|numeric|min:0',
            'used_amount'  => 'sometimes|nullable|numeric|min:0|lte:amount',
            'fiscal_year'  => 'sometimes|nullable|integer|min:1400|max:1420',
            'description'  => 'sometimes|nullable|string',
        ]);

        $budget->update($data);
        return new BudgetResource($budget->fresh());
    }

    public function destroy(Budget $budget)
    {
        $budget->delete();
        return response()->json(['message' => 'بودجه حذف شد.'], 200);
    }

    public function summary( $id)
    {
        //$request->validate(['project_id' => 'required|exists:projects,id']);

        $budgets = Budget::where('project_id', $id)->get();

        return response()->json([
            'total_budget'    => $budgets->sum('amount'),
            'total_used'      => $budgets->sum('used_amount'),
            'total_remaining' => $budgets->sum('amount') - $budgets->sum('used_amount'),
            'by_type'         => $budgets->groupBy('type')->map(fn($group) => [
                'amount'      => $group->sum('amount'),
                'used_amount' => $group->sum('used_amount'),
                'count'       => $group->count(),
            ]),
        ]);
    }
}
