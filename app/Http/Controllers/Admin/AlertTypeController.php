<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAlertTypeRequest;
use App\Models\Admin\AlertType;
use App\Support\SseNotifier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AlertTypeController extends Controller
{
    /** @return Collection<int, AlertType> */
    public function index(Request $request): Collection
    {
        return AlertType::with('emergencyCategory')
            ->when($request->emergency_category_id, fn ($q, $id) => $q->where('emergency_category_id', $id))
            ->latest()
            ->get();
    }

    public function show(AlertType $alertType): AlertType
    {
        return $alertType->load('emergencyCategory');
    }

    public function store(StoreAlertTypeRequest $request): JsonResponse
    {
        $alertType = AlertType::create($request->validated());
        SseNotifier::touch('alert-types');

        return response()->json($alertType, 201);
    }

    public function update(StoreAlertTypeRequest $request, AlertType $alertType): JsonResponse
    {
        $alertType->update($request->validated());
        SseNotifier::touch('alert-types');

        return response()->json($alertType);
    }

    public function toggleStatus(AlertType $alertType): JsonResponse
    {
        $alertType->update(['is_active' => ! $alertType->is_active]);
        SseNotifier::touch('alert-types');

        return response()->json($alertType);
    }

    public function destroy(AlertType $alertType): JsonResponse|Response
    {
        // Block delete if any alerts still use this type
        if ($alertType->alerts()->exists()) {
            return response()->json([
                'message' => 'Cannot delete this alert type because it is used by existing sent alerts. Deactivate it instead, or remove those alerts first.',
            ], 422);
        }

        $alertType->delete();
        SseNotifier::touch('alert-types');

        return response()->noContent();
    }
}
