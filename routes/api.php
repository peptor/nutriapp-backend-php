<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FoodLibraryController;
use App\Http\Controllers\PatientsController;
use App\Http\Controllers\RecordsController;
use App\Http\Controllers\RoutinesController;
use App\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;

// ——— Auth ———
// Rate limit per rutes d'autenticació: frena atacs de força bruta al login/registre
// (mirall de l'authLimiter d'express-rate-limit: 20 peticions cada 15 minuts).
Route::prefix('auth')->middleware('throttle:20,15')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/set-password', [AuthController::class, 'setPassword']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/me', [AuthController::class, 'updateMe']);
        Route::post('/me/password', [AuthController::class, 'changePassword']);
        Route::get('/me/export', [AuthController::class, 'exportMe']);
        Route::delete('/me', [AuthController::class, 'deleteMe']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    // ——— Patients ———
    Route::prefix('patients')->group(function () {
        Route::get('/', [PatientsController::class, 'index'])->middleware('role:NUTRICIONISTA');
        Route::post('/', [PatientsController::class, 'store'])->middleware('role:NUTRICIONISTA');
        Route::get('/me/profiles', [PatientsController::class, 'myProfiles'])->middleware('role:PACIENT');
        Route::get('/{id}', [PatientsController::class, 'show']);
        Route::put('/{id}', [PatientsController::class, 'update'])->middleware('role:NUTRICIONISTA');
        Route::post('/{id}/photo', [PatientsController::class, 'uploadPhoto'])->middleware('role:NUTRICIONISTA');
        Route::delete('/{id}/photo', [PatientsController::class, 'deletePhoto'])->middleware('role:NUTRICIONISTA');
    });

    // ——— Appointments ———
    Route::prefix('appointments')->middleware('role:NUTRICIONISTA')->group(function () {
        Route::get('/', [AppointmentController::class, 'index']);
        Route::post('/', [AppointmentController::class, 'store']);
        Route::put('/{id}', [AppointmentController::class, 'update']);
        Route::delete('/{id}', [AppointmentController::class, 'destroy']);
    });

    // ——— Horari (patró de disponibilitat, excepcions i resolució per data) ———
    Route::prefix('schedule')->middleware('role:NUTRICIONISTA')->group(function () {
        Route::get('/', [ScheduleController::class, 'show']);
        Route::put('/pattern', [ScheduleController::class, 'updatePattern']);
        Route::put('/slots', [ScheduleController::class, 'updateSlots']);
        Route::get('/resolved', [ScheduleController::class, 'resolved']);
        Route::get('/exceptions', [ScheduleController::class, 'exceptionsIndex']);
        Route::post('/exceptions', [ScheduleController::class, 'exceptionsStore']);
        Route::put('/exceptions/{id}', [ScheduleController::class, 'exceptionsUpdate']);
        Route::delete('/exceptions/{id}', [ScheduleController::class, 'exceptionsDestroy']);
    });

    // ——— Routines ———
    Route::prefix('routines')->group(function () {
        Route::get('/library', [RoutinesController::class, 'library'])->middleware('role:NUTRICIONISTA');
        Route::get('/foods', [RoutinesController::class, 'foods'])->middleware('role:NUTRICIONISTA,PACIENT');
        Route::get('/food-categories', [RoutinesController::class, 'foodCategories'])->middleware('role:NUTRICIONISTA,PACIENT');
        Route::get('/field-icons', [RoutinesController::class, 'fieldIcons'])->middleware('role:NUTRICIONISTA');
        Route::get('/field-library', [RoutinesController::class, 'fieldLibraryIndex'])->middleware('role:NUTRICIONISTA');
        Route::post('/field-library', [RoutinesController::class, 'fieldLibraryStore'])->middleware('role:NUTRICIONISTA');
        Route::delete('/field-library/{id}', [RoutinesController::class, 'fieldLibraryDestroy'])->middleware('role:NUTRICIONISTA');
        Route::post('/templates/from-library/{libraryId}', [RoutinesController::class, 'templateFromLibrary'])->middleware('role:NUTRICIONISTA');
        Route::get('/templates', [RoutinesController::class, 'templatesIndex'])->middleware('role:NUTRICIONISTA');
        Route::post('/templates', [RoutinesController::class, 'templatesStore'])->middleware('role:NUTRICIONISTA');
        Route::put('/templates/{id}', [RoutinesController::class, 'templatesUpdate'])->middleware('role:NUTRICIONISTA');
        Route::post('/assign', [RoutinesController::class, 'assign'])->middleware('role:NUTRICIONISTA');
        Route::get('/my-active', [RoutinesController::class, 'myActive'])->middleware('role:PACIENT');
        Route::get('/assignments/{patientId}', [RoutinesController::class, 'assignmentsOfPatient'])->middleware('role:NUTRICIONISTA');
        Route::get('/my-assignments', [RoutinesController::class, 'myAssignments'])->middleware('role:PACIENT');
        Route::patch('/assignments/{id}/status', [RoutinesController::class, 'updateStatus'])->middleware('role:NUTRICIONISTA');
        Route::delete('/assignments/{id}', [RoutinesController::class, 'destroyAssignment'])->middleware('role:NUTRICIONISTA');
    });

    // ——— Biblioteca d'aliments: favorits i recents del picker (pacient, o el seu nutricionista) ———
    Route::prefix('food-library')->middleware('role:NUTRICIONISTA,PACIENT')->group(function () {
        Route::get('/favorites', [FoodLibraryController::class, 'favorites']);
        Route::post('/favorites', [FoodLibraryController::class, 'addFavorite']);
        Route::delete('/favorites/{foodId}', [FoodLibraryController::class, 'removeFavorite']);
        Route::get('/recents', [FoodLibraryController::class, 'recents']);
        Route::post('/selections', [FoodLibraryController::class, 'recordSelection']);
    });

    // ——— Cerca i consulta d'aliments + gestió d'aliments personalitzats (nutricionista) ———
    Route::prefix('food-library')->middleware('role:NUTRICIONISTA')->group(function () {
        Route::get('/foods', [FoodLibraryController::class, 'index']);
        Route::post('/foods', [FoodLibraryController::class, 'storeFood']);
        Route::put('/foods/{id}', [FoodLibraryController::class, 'updateFood']);
        Route::delete('/foods/{id}', [FoodLibraryController::class, 'destroyFood']);
    });

    // ——— Records ———
    Route::prefix('records')->group(function () {
        Route::post('/', [RecordsController::class, 'store'])->middleware('role:PACIENT,NUTRICIONISTA');
        Route::post('/batch', [RecordsController::class, 'batch'])->middleware('role:PACIENT,NUTRICIONISTA');
        Route::get('/assignment/{assignmentId}', [RecordsController::class, 'forAssignment']);
    });

    // ——— Dashboard ———
    Route::prefix('dashboard')->group(function () {
        Route::get('/overview', [DashboardController::class, 'overview'])->middleware('role:NUTRICIONISTA');
        Route::get('/summary/{assignmentId}', [DashboardController::class, 'summary']);
    });

    // ——— Business ———
    Route::prefix('business')->group(function () {
        Route::get('/me', [BusinessController::class, 'show'])->middleware('role:NUTRICIONISTA');
        Route::put('/me', [BusinessController::class, 'update'])->middleware('role:NUTRICIONISTA');
        Route::delete('/me/logo', [BusinessController::class, 'deleteLogo'])->middleware('role:NUTRICIONISTA');
        Route::put('/theme', [BusinessController::class, 'updateTheme'])->middleware('role:NUTRICIONISTA');
        Route::get('/my-nutricionistes', [BusinessController::class, 'myNutricionistes'])->middleware('role:PACIENT');
    });

    // ——— Admin ———
    // Totes les rutes d'aquest grup requereixen rol ADMIN.
    Route::prefix('admin')->middleware('role:ADMIN')->group(function () {
        Route::get('/users', [AdminController::class, 'index']);
        Route::get('/users/deleted', [AdminController::class, 'deleted']);
        Route::get('/nutricionistes', [AdminController::class, 'nutricionistes']);
        Route::get('/users/{id}', [AdminController::class, 'show']);
        Route::post('/users', [AdminController::class, 'store']);
        Route::patch('/users/{id}', [AdminController::class, 'update']);
        Route::delete('/users/{id}', [AdminController::class, 'destroy']);
        Route::get('/access-logs', [AdminController::class, 'accessLogs']);
    });
});
