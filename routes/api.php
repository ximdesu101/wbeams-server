<?php

use App\Http\Controllers\Admin\AccessRequestController;
use App\Http\Controllers\Admin\AlertController;
use App\Http\Controllers\Admin\AlertTypeController;
use App\Http\Controllers\Admin\EmergencyCategoryController;
use App\Http\Controllers\Admin\MasterlistController;
use App\Http\Controllers\Admin\OperatorController;
use App\Http\Controllers\Admin\RecipientController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SseController;
use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\Auth\OperatorAuthController;
use App\Http\Controllers\Auth\RecipientAuthController;
use App\Http\Controllers\Operator\AlertController as OperatorAlertController;
use App\Http\Controllers\Operator\EmergencyCategoryController as OperatorEmergencyCategoryController;
use App\Http\Controllers\Operator\ReportController as OperatorReportController;
use App\Http\Controllers\Recipient\AlertController as RecipientAlertController;
use App\Http\Controllers\Recipient\ReportController as RecipientReportController;
use Illuminate\Support\Facades\Route;

/* ------------------------- Admin Routes ------------------------- */
// Admin Auth
Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login']);

    Route::middleware('auth:sanctum,admin')->group(function () {
        Route::get('/me', [AdminAuthController::class, 'me']);
        Route::post('/logout', [AdminAuthController::class, 'logout']);
        Route::get('/sse', [SseController::class, 'adminStream']);
    });
    // Admin-facing alerts & reports (list views for admin UI)
    Route::middleware('auth:sanctum,admin')->group(function () {
        Route::get('/alerts', [AlertController::class, 'index']);
        Route::get('/alerts/stats', [AlertController::class, 'stats']);
        Route::get('/alerts/dispatch-stats', [AlertController::class, 'dispatchStats']);
        Route::delete('/alerts/{alert}', [AlertController::class, 'destroy']);
        Route::get('/reports', [ReportController::class, 'index']);
        Route::get('/reports/stats', [ReportController::class, 'stats']);
    });
});

// Admin-managed emergency categories
Route::prefix('admin/emergency-categories')->middleware('auth:sanctum,admin')->group(function () {
    Route::get('/', [EmergencyCategoryController::class, 'index']);
    Route::post('/', [EmergencyCategoryController::class, 'store']);
    Route::get('/{emergencyCategory}', [EmergencyCategoryController::class, 'show']);
    Route::put('/{emergencyCategory}', [EmergencyCategoryController::class, 'update']);
    Route::delete('/{emergencyCategory}', [EmergencyCategoryController::class, 'destroy']);
    Route::patch('/{emergencyCategory}/toggle-status', [EmergencyCategoryController::class, 'toggleStatus']);
});

// Admin-managed alert types
Route::prefix('admin/alert-types')->middleware('auth:sanctum,admin')->group(function () {
    Route::get('/', [AlertTypeController::class, 'index']);
    Route::post('/', [AlertTypeController::class, 'store']);
    Route::get('/{alertType}', [AlertTypeController::class, 'show']);
    Route::put('/{alertType}', [AlertTypeController::class, 'update']);
    Route::delete('/{alertType}', [AlertTypeController::class, 'destroy']);
    Route::patch('/{alertType}/toggle-status', [AlertTypeController::class, 'toggleStatus']);
});

// Admin-manage Access Request
Route::prefix('admin/access-requests')->middleware('auth:sanctum,admin')->group(function () {
    Route::get('/', [AccessRequestController::class, 'index']);
    Route::get('/pending-count', [AccessRequestController::class, 'pendingCount']);
    Route::patch('/{accessRequest}/approve', [AccessRequestController::class, 'approve']);
    Route::patch('/{accessRequest}/reject', [AccessRequestController::class, 'reject']);
});

// Admin-managed recipient records
Route::prefix('admin/recipients')->middleware('auth:sanctum,admin')->group(function () {
    Route::get('/', [RecipientController::class, 'index']);
    Route::get('/{recipient}', [RecipientController::class, 'show']);
    Route::patch('/{recipient}/status', [RecipientController::class, 'updateStatus']);
    Route::delete('/{recipient}', [RecipientController::class, 'destroy']);
});

// Admin-managed masterlist records
Route::prefix('admin/masterlists')->middleware('auth:sanctum,admin')->group(function () {
    Route::get('/', [MasterlistController::class, 'index']);
    Route::get('/{masterlist}', [MasterlistController::class, 'show']);
    Route::post('/', [MasterlistController::class, 'store']);
    Route::put('/{masterlist}', [MasterlistController::class, 'update']);
    Route::delete('/{masterlist}', [MasterlistController::class, 'destroy']);
    Route::post('/import', [MasterlistController::class, 'import']);
});

// Admin-managed operator records
Route::prefix('admin/operators')->middleware('auth:sanctum,admin')->group(function () {
    Route::get('/', [OperatorController::class, 'index']);
    Route::post('/', [OperatorController::class, 'store']);
    Route::get('/{operator}/activity', [OperatorController::class, 'activity']);
    Route::get('/{operator}/daily-activity', [OperatorController::class, 'dailyActivity']);
    Route::patch('/{operator}/status', [OperatorController::class, 'updateStatus']);
    Route::delete('/{operator}', [OperatorController::class, 'destroy']);
    Route::get('/{operator}', [OperatorController::class, 'show']);
    Route::post('/{operator}/resend-invitation', [OperatorController::class, 'resendInvitation']);

});

/* ------------------------- Operator Routes ------------------------- */
// Operator Auth
Route::prefix('operator')->group(function () {
    Route::get('/validate-token', [OperatorAuthController::class, 'validateToken']);
    Route::post('/activate', [OperatorAuthController::class, 'activate']);
    Route::post('/login', [OperatorAuthController::class, 'login']);

    Route::middleware('auth:sanctum,operator')->group(function () {
        Route::get('/me', [OperatorAuthController::class, 'me']);
        Route::post('/logout', [OperatorAuthController::class, 'logout']);
        Route::get('/sse', [SseController::class, 'operatorStream']);
    });
});

// Operator-facing emergency categories + alert types (read-only)
Route::prefix('operator/emergency-categories')->middleware('auth:sanctum,operator')->group(function () {
    Route::get('/', [OperatorEmergencyCategoryController::class, 'index']);
});


// Operator-managed alerts
Route::prefix('operator/alerts')->middleware('auth:sanctum,operator')->group(function () {
    Route::get('/', [OperatorAlertController::class, 'index']);
    Route::post('/', [OperatorAlertController::class, 'store']);
});

Route::prefix('operator/reports')->middleware('auth:sanctum,operator')->group(function () {
    Route::get('/', [OperatorReportController::class, 'index']);
    Route::patch('/{report}/status', [OperatorReportController::class, 'updateStatus']);
});

/* ------------------------- Recipient Routes ------------------------- */
// Recipient Auth
Route::prefix('recipient')->group(function () {
    Route::post('/verify', [RecipientAuthController::class, 'verify']);
    Route::post('/register', [RecipientAuthController::class, 'register']);
    Route::post('/request-access', [RecipientAuthController::class, 'requestAccess']);
    Route::post('/login', [RecipientAuthController::class, 'login']);

    Route::middleware('auth:sanctum,recipient')->group(function () {
        Route::get('/me', [RecipientAuthController::class, 'me']);
        Route::post('/logout', [RecipientAuthController::class, 'logout']);
        Route::get('/sse', [SseController::class, 'recipientStream']);
    });
});

//Recipient-receving alerts
Route::prefix('recipient/alerts')->middleware('auth:sanctum,recipient')->group(function () {
    Route::get('/', [RecipientAlertController::class, 'index']);
    Route::get('/unread-count', [RecipientAlertController::class, 'unreadCount']);
    Route::get('/pending', [RecipientAlertController::class, 'pending']);
    Route::patch('/read-all', [RecipientAlertController::class, 'markAllRead']);
    Route::patch('/{alert}/read', [RecipientAlertController::class, 'markRead']);
});

// Recipient-submitted reports
Route::prefix('recipient/reports')->middleware('auth:sanctum,recipient')->group(function () {
    Route::get('/', [RecipientReportController::class, 'index']);
    Route::post('/', [RecipientReportController::class, 'store']);
    Route::delete('/{report}', [RecipientReportController::class, 'destroy']);
});

//Recipient-send alerts
Route::post('/recipient/emergency-sos', [RecipientReportController::class, 'emergencySos'])
    ->middleware('auth:sanctum,recipient');

//Recipient-acknowledge alerts
Route::get('/recipient/alerts/{alert}/acknowledge-email/{recipient}', [RecipientAlertController::class, 'acknowledgeViaEmail'])
    ->name('recipient.alerts.acknowledge-email')
    ->middleware('signed');