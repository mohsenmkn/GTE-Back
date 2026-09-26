<?php
namespace Modules\Assessment\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Assessment\App\Models\AssessmentCycle;
use Modules\Assessment\App\Services\AssessmentService;

class AssessmentCycleController extends Controller
{
    public function __construct(private AssessmentService $service)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'cycles' => AssessmentCycle::withCount('assessments')->latest()->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'nullable|in:annual,transfer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'description' => 'nullable|string',
        ]);

        $cycle = $this->service->createCycle($validated);

        return response()->json(['cycle' => $cycle], 201);
    }

    public function activate(AssessmentCycle $cycle): JsonResponse
    {
        return response()->json(['cycle' => $this->service->setCycleStatus($cycle, AssessmentCycle::STATUS_ACTIVE)]);
    }

    public function close(AssessmentCycle $cycle): JsonResponse
    {
        return response()->json(['cycle' => $this->service->setCycleStatus($cycle, AssessmentCycle::STATUS_CLOSED)]);
    }
}
