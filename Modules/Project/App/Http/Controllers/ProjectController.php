<?php

// Modules/Project/Http/Controllers/ProjectController.php

namespace Modules\Project\App\Http\Controllers;


use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Project\App\FormSchemas\ProjectFormSchema;
use Modules\Project\App\Models\Project;
use Modules\Project\App\Http\Requests\StoreProjectRequest;
use Modules\Project\App\Http\Requests\UpdateProjectRequest;
use Modules\Project\Transformers\ProjectResource;

class ProjectController extends Controller
{
    public function schema(): JsonResponse
    {
        $schema = ProjectFormSchema::fields();
        return response()->json($schema);
        //return response()->json(ProjectFormSchema::fields());
    }

    /**
     * GET /api/projects
     * لیست پروژه‌ها (برای جدول یا لیست)
     */
    public function index(): JsonResponse
    {
        $projects = Project::with(['company', 'manager'])
            ->latest()
            ->paginate(15);

        return response()->json([
            'data' => ProjectResource::collection($projects->items()),
            'meta' => [
                'current_page' => $projects->currentPage(),
                'last_page'    => $projects->lastPage(),
                'total'        => $projects->total(),
            ],
        ]);
    }

    /**
     * GET /api/projects/{project}
     * گرفتن یک پروژه برای حالت Edit
     */
    public function show(Project $project): JsonResponse
    {
        $project->load(['company', 'manager']);

        return response()->json(new ProjectResource($project));
    }

    /**
     * POST /api/projects
     * ساخت پروژه جدید
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = Project::create($request->validated());

        return response()->json(
            new ProjectResource($project),
            201
        );
    }

    /**
     * PUT /api/projects/{project}
     * ویرایش پروژه موجود
     */
    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $project->update($request->validated());

        return response()->json(new ProjectResource($project));
    }

    /**
     * DELETE /api/projects/{project}
     */
    public function destroy(Project $project): JsonResponse
    {
        $project->delete();

        return response()->json(null, 204);
    }
}
