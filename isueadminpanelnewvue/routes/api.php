<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BuildingController;
use App\Http\Controllers\FacultyController;
use App\Http\Controllers\MapController;

// Map Management Routes - Put these first to avoid conflicts with other routes
Route::prefix('map')->group(function () {
    Route::get('/active', [MapController::class, 'getActive']); // Get active map - Put this before {map} route
    Route::post('/upload', [MapController::class, 'upload']); // Upload map image
    Route::get('/', [MapController::class, 'index']);  // Get all maps
    Route::post('/', [MapController::class, 'store']); // Create new map
    Route::get('/{map}', [MapController::class, 'show']); // Get specific map
    Route::put('/{map}', [MapController::class, 'update']); // Update map
    Route::delete('/{map}', [MapController::class, 'destroy']); // Delete map
    Route::put('/{map}/activate', [MapController::class, 'activate']); // Activate map
});

// Building Routes
Route::prefix('buildings')->group(function () {
    Route::get('/', [BuildingController::class, 'index']);
    Route::post('/', [BuildingController::class, 'store']);
    Route::get('/{id}', [BuildingController::class, 'show']);
    Route::put('/{id}', [BuildingController::class, 'update']);
    Route::delete('/{id}', [BuildingController::class, 'destroy']);
});

// Faculty Routes
Route::prefix('faculty')->group(function () {
    Route::get('/', [FacultyController::class, 'index']);
    Route::post('/', [FacultyController::class, 'store']);
    Route::get('/building/{buildingId}', [FacultyController::class, 'getByBuilding']);
    Route::get('/{id}', [FacultyController::class, 'show']);
    Route::put('/{id}', [FacultyController::class, 'update']);
    Route::delete('/{id}', [FacultyController::class, 'destroy']);
}); 