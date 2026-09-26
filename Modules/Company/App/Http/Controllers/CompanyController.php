<?php
// Modules/Company/Http/Controllers/CompanyController.php

namespace Modules\Company\App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Company\App\Models\Company;
use Modules\Company\App\Http\Requests\StoreCompanyRequest;
use Modules\Company\App\Http\Requests\UpdateCompanyRequest;
use Modules\Company\Transformers\CompanyResource;

class CompanyController extends Controller
{
    public function index(): JsonResponse
    {
        $companies = Company::withCount('projects')
            ->latest()
            ->paginate(15);

        return CompanyResource::collection($companies)
            ->response();
    }

    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('companies/logos', 'public');
        }

        $company = Company::create($data);

        return (new CompanyResource($company))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Company $company): JsonResponse
    {
        $company->loadCount('projects');

        return (new CompanyResource($company))->response();
    }

    public function update(UpdateCompanyRequest $request, Company $company): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('companies/logos', 'public');
        }

        $company->update($data);

        return (new CompanyResource($company))->response();
    }

    public function destroy(Company $company): JsonResponse
    {
        $company->delete();

        return response()->json(['message' => 'Company deleted successfully']);
    }
}
