<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Admin\EmergencyCategory;
use Illuminate\Http\JsonResponse;

class EmergencyCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = EmergencyCategory::where('is_active', true)
            ->with(['alertTypes' => function ($query) {
                $query->where('is_active', true)
                    ->select(
                        'id',
                        'emergency_category_id',
                        'name',
                        'description',
                        'response_instructions',
                        'severity',
                        'icon',
                        'color'
                    );
            }])
            ->get(['id', 'name']);

        return response()->json(['data' => $categories]);
    }
}
