<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMasterlistRequest;
use App\Http\Requests\Admin\UpdateMasterlistRequest;
use App\Models\Admin\Masterlist;
use App\Imports\MasterlistImport;
use App\Support\SseNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class MasterlistController extends Controller
{
    // ============================================================
    // 1. READ / LIST - Display all masterlist entries with filters and role counts
    // ============================================================
    public function index(Request $request): JsonResponse
    {
        $query = Masterlist::query();

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('id_number', 'like', "%{$search}%");
            });
        }

        $masterlists = $query->latest()->paginate(20);

        $roleCounts = Masterlist::selectRaw('role, count(*) as count')
            ->groupBy('role')
            ->pluck('count', 'role');

        return response()->json([
            'data' => $masterlists->items(),
            'meta' => [
                'current_page' => $masterlists->currentPage(),
                'last_page' => $masterlists->lastPage(),
                'total' => $masterlists->total(),
                'role_counts' => [
                    'student' => $roleCounts->get('student', 0),
                    'faculty' => $roleCounts->get('faculty', 0),
                    'staff' => $roleCounts->get('staff', 0),
                ],
            ],
        ]);
    }

    // ============================================================
    // 2. READ / SHOW - Display a single masterlist entry
    // ============================================================
    public function show(Masterlist $masterlist): JsonResponse
    {
        return response()->json([
            'data' => $masterlist,
        ]);
    }

    // ============================================================
    // 3. CREATE / STORE - Add a new masterlist entry
    // ============================================================
    public function store(StoreMasterlistRequest $request): JsonResponse
    {
        $masterlist = Masterlist::create($request->validated());
        SseNotifier::touch('masterlist');

        return response()->json([
            'message' => 'Masterlist entry added successfully.',
            'data' => $masterlist,
        ], 201);
    }

    // ============================================================
    // 4. CREATE / IMPORT - Bulk import masterlist entries from file
    // ============================================================
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls'],
        ]);

        $import = new MasterlistImport();
        Excel::import($import, $request->file('file'));

        $failures = $import->failures()->map(function ($failure) {
            return [
                'row' => $failure->row(),
                'errors' => $failure->errors(),
                'values' => $failure->values(),
            ];
        });

        $errors = $import->errors()->map(function ($error) {
            return $error->getMessage();
        });

        if ($import->importedCount > 0) {
            SseNotifier::touch('masterlist');
        }

        return response()->json([
            'message' => "Import completed. {$import->importedCount} row(s) added.",
            'imported_count' => $import->importedCount,
            'failed_rows' => $failures,
            'errors' => $errors,
        ], 200);
    }

    // ============================================================
    // 5. UPDATE - Modify an existing masterlist entry
    // ============================================================
    public function update(UpdateMasterlistRequest $request, Masterlist $masterlist): JsonResponse
    {
        $masterlist->update($request->validated());
        SseNotifier::touch('masterlist');

        return response()->json([
            'message' => 'Masterlist entry updated successfully.',
            'data' => $masterlist,
        ]);
    }

    // ============================================================
    // 6. DELETE - Remove a masterlist entry
    // ============================================================
    public function destroy(Masterlist $masterlist): JsonResponse
    {
        $masterlist->delete();
        SseNotifier::touch('masterlist');

        return response()->json([
            'message' => 'Masterlist entry deleted successfully.',
        ]);
    }
}