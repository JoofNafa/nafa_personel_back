<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\App\DepartmentController;
use App\Http\Controllers\API\App\UserController;
use App\Http\Controllers\API\App\LeaveController;
use App\Http\Controllers\API\App\AttendanceController;
use App\Http\Controllers\API\App\ShiftController;
use App\Http\Controllers\API\App\PermissionController;
use App\Http\Controllers\API\App\AttendanceAutoFillController;
use App\Http\Controllers\API\App\UserWeeklyDayOffController;
use App\Http\Controllers\API\App\UserStatsController;
use App\Http\Controllers\API\App\VigileController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Routes API sécurisées avec JWT et middleware rôle
|
*/

// ✅ Authentification
Route::post('/login', [AuthController::class, 'login']);
Route::post('/login-with-pin', [AuthController::class, 'loginWithPin']);

// ✅ Routes protégées avec JWT
Route::middleware('auth:api')->group(function () {

    // Routes pour les vigiles
    Route::get('/users/pending-attendance', [VigileController::class, 'usersPendingAttendance']);
    Route::post('/users/check_in', [VigileController::class, 'bulkCheckIn']);
    Route::post('/users/check_out', [VigileController::class, 'bulkCheckOut']);
    Route::get('/user/attendance', [AttendanceController::class, 'vigileAttendanceSummary']);

    // Routes communes à tous les utilisateurs connectés
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::post('/update-password', [AuthController::class, 'updatePassword']);
    Route::post('/update-pin', [AuthController::class, 'updatePin']);
    Route::get('/me', [AuthController::class, 'me']);

    // Routes de consultation personnelles (accessibles à tous)
    Route::get('/leaves/me', [LeaveController::class, 'myLeaves']);
    Route::get('/attendances/me', [AttendanceController::class, 'myAttendances']);
    Route::get('/attendances/my-summary', [AttendanceController::class, 'myAttendanceSummary']);


    // Routes pour admin et RH uniquement
    Route::middleware('role:admin,rh')->group(function () {

        // 📌 Departments
        Route::apiResource('departments', DepartmentController::class);

        // 📌 Users
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);

        // 📌 Leaves - Routes spécifiques AVANT les routes avec paramètres
        Route::get('/leaves', [LeaveController::class, 'requestedLeave']);
        Route::post('/leaves/{id}/approve', [LeaveController::class, 'approve']);
        Route::post('/leaves/{id}/reject', [LeaveController::class, 'reject']);
        Route::get('/leaves/{id}', [LeaveController::class, 'show']);
        Route::put('/leaves/{id}', [LeaveController::class, 'update']);
        Route::delete('/leaves/{id}', [LeaveController::class, 'destroy']);

        // 📌 Shifts
        Route::get('/shifts', [ShiftController::class, 'index']);
        Route::post('/shifts', [ShiftController::class, 'store']);
        Route::get('/shifts/{id}', [ShiftController::class, 'show']);
        Route::put('/shifts/{id}', [ShiftController::class, 'update']);
        Route::delete('/shifts/{id}', [ShiftController::class, 'destroy']);

        // 📌 Permissions - Routes spécifiques AVANT les routes avec paramètres
        Route::get('/permissions', [PermissionController::class, 'index']);
        Route::post('/permissions/{id}/approve', [PermissionController::class, 'approve']);
        Route::post('/permissions/{id}/reject', [PermissionController::class, 'reject']);
        Route::put('/permissions/{id}', [PermissionController::class, 'update']);
        Route::delete('/permissions/{id}', [PermissionController::class, 'destroy']);

        // 📌 Attendances
        Route::get('/attendances', [AttendanceController::class, 'index']);
        Route::get('/attendances/summary', [AttendanceController::class, 'attendanceSummary']);
        Route::post('/attendances/auto-fill', [AttendanceAutoFillController::class, 'fillAllAttendances']);
        Route::post('/attendances/{userId}/absent', [AttendanceController::class, 'markAbsent']);
        Route::get('/attendances/{id}', [AttendanceController::class, 'show']);
        Route::delete('/attendances/{id}', [AttendanceController::class, 'destroy']);

        // 📌 Weekly Day Offs - CRUD
        Route::get('/user-for-day-offs', [UserWeeklyDayOffController::class, 'userForDayOff']);
        Route::get('/weekly-day-offs', [UserWeeklyDayOffController::class, 'index']);
        Route::post('/weekly-day-offs', [UserWeeklyDayOffController::class, 'store']);
        Route::get('/weekly-day-offs/{id}', [UserWeeklyDayOffController::class, 'show']);
        Route::put('/weekly-day-offs/{id}', [UserWeeklyDayOffController::class, 'update']);
        Route::delete('/weekly-day-offs/{id}', [UserWeeklyDayOffController::class, 'destroy']);
        Route::get('/users/{id}/weekly-day-offs', [UserWeeklyDayOffController::class, 'getUserDayOffs']);

         // 📌 User monthly stats
        Route::get('/monthly-stats', [UserStatsController::class, 'getAllUsersMonthlyStats']);
        Route::get('/monthly-attendance-summary', [UserStatsController::class, 'getMonthlyAttendanceSummary']);

        Route::get('/todaySituation', [AttendanceController::class, 'todaySituation']);


    });

    // Routes pour tous les utilisateurs pouvant créer des demandes / actions
    Route::middleware('role:admin,rh,employee,manager')->group(function () {
        // Création de demandes
        Route::post('/request-leaves', [LeaveController::class, 'newLeaveRequest']);
        Route::post('/permissions', [PermissionController::class, 'store']);
        Route::get('/my-permissions', [PermissionController::class, 'myMonthlyPermissions']);

        // Pointage
        Route::post('/attendances/check-in', [AttendanceController::class, 'checkIn']);
        Route::post('/attendances/check-out', [AttendanceController::class, 'checkOut']);


        // Route::get('/my-monthly-stats', [UserStatsController::class, 'getUserMonthlyStats']);
    });

});