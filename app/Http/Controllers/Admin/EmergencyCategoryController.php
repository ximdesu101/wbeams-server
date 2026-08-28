<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEmergencyCategoryRequest;
use App\Models\Admin\EmergencyCategory;
use App\Support\SseNotifier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class EmergencyCategoryController extends Controller
{
    /** @return Collection<int, EmergencyCategory> */
    public function index(): Collection
    {
        return EmergencyCategory::withCount('alertTypes')->latest()->get();
    }

    public function show(EmergencyCategory $emergencyCategory): EmergencyCategory
    {
        return $emergencyCategory->loadCount('alertTypes');
    }

    public function store(StoreEmergencyCategoryRequest $request): JsonResponse
    {
        $category = EmergencyCategory::create($request->validated());
        SseNotifier::touch('emergency-categories');

        return response()->json($category, 201);
    }

    public function update(StoreEmergencyCategoryRequest $request, EmergencyCategory $emergencyCategory): JsonResponse
    {
        $emergencyCategory->update($request->validated());
        SseNotifier::touch('emergency-categories');

        return response()->json($emergencyCategory);
    }

    public function toggleStatus(EmergencyCategory $emergencyCategory): JsonResponse
    {
        $emergencyCategory->update(['is_active' => ! $emergencyCategory->is_active]);

        if (! $emergencyCategory->is_active) {
            $emergencyCategory->alertTypes()->update(['is_active' => false]);
            SseNotifier::touch('alert-types');
        }

        SseNotifier::touch('emergency-categories');

        return response()->json($emergencyCategory->load('alertTypes'));
    }

    public function destroy(EmergencyCategory $emergencyCategory): Response
    {
        $emergencyCategory->delete();
        SseNotifier::touch('emergency-categories');

        return response()->noContent();
    }
}
