<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WorkOrderController;
use App\Http\Controllers\Api\DefectController;
use App\Http\Controllers\Api\DowntimeEventController;
use App\Http\Controllers\Api\DashboardController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    Route::get('work-orders/export', [WorkOrderController::class, 'export']);
    Route::post('work-orders/import', [WorkOrderController::class, 'import']);

    Route::post('defects/import', [DefectController::class, 'import']);
    Route::get('defects/export', [DefectController::class, 'export']);

    Route::post('downtime-events/import', [DowntimeEventController::class, 'import']);
    Route::get('downtime-events/export', [DowntimeEventController::class, 'export']);


    Route::apiResource('work-orders', WorkOrderController::class);

    Route::apiResource('defects', DefectController::class);
    Route::apiResource('downtime-events', DowntimeEventController::class);

    Route::get('dashboard/kpi', [DashboardController::class, 'kpi']);
    Route::get('dashboard/production', [DashboardController::class, 'production']);
    Route::get('dashboard/quality', [DashboardController::class, 'quality']);
    Route::get('dashboard/downtime', [DashboardController::class, 'downtime']);
});