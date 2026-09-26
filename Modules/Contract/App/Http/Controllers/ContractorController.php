<?php
// Modules/Contract/App/Http/Controllers/ContractorController.php

namespace Modules\Contract\App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Contract\App\Models\Contractor;
use Modules\Contract\App\Http\Requests\StoreContractorRequest;
use Modules\Contract\Transformers\ContractorResource;

class ContractorController extends Controller
{
    public function index(Request $request)
    {
        $query = Contractor::withCount('contracts')
            ->when($request->company_id, fn($q) => $q->where('company_id', $request->company_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->search, function($q) use ($request) {
                $q->where(function($query) use ($request) {
                    $query->where('name', 'like', "%{$request->search}%")
                        ->orWhere('code', 'like', "%{$request->search}%")
                        ->orWhere('national_id', 'like', "%{$request->search}%")
                        ->orWhere('registration_number', 'like', "%{$request->search}%");
                });
            })
            ->latest();

        return ContractorResource::collection($query->paginate($request->per_page ?? 15));
    }

    public function store(StoreContractorRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        $contractor = Contractor::create($data);

        return new ContractorResource($contractor);
    }

    public function show(Contractor $contractor)
    {
        $contractor->load(['contracts', 'creator']);
        return new ContractorResource($contractor);
    }

    public function update(Request $request, Contractor $contractor)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => 'nullable|string|max:50|unique:contractors,code,' . $contractor->id,
            'registration_number' => 'nullable|string|max:50',
            'economic_code' => 'nullable|string|max:50',
            'national_id' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'bank_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:50',
            'bank_card_number' => 'nullable|string|max:50',
            'shaba_number' => 'nullable|string|max:50',
            'type' => 'sometimes|required|in:legal,real',
            'expertise' => 'nullable|array',
            'certificates' => 'nullable|array',
            'description' => 'nullable|string',
            'status' => 'sometimes|required|in:active,inactive,suspended',
        ]);

        $contractor->update($data);

        return new ContractorResource($contractor);
    }

    public function destroy(Contractor $contractor)
    {
        if ($contractor->contracts()->exists()) {
            return response()->json([
                'message' => 'این پیمانکار دارای قرارداد است و قابل حذف نیست.'
            ], 422);
        }

        $contractor->delete();

        return response()->json(['message' => 'پیمانکار با موفقیت حذف شد.'], 200);
    }
}
