<?php

use App\Http\Controllers\Admin\AdminSurveyController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\PublicInvitationController;
use App\Http\Controllers\PublicSurveyController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\SurveySubmissionController;
use App\Http\Controllers\SurveyShareController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/surveys', [SurveyController::class, 'index']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});

Route::middleware(['auth:sanctum', 'role:creator,admin'])->group(function (): void {
    Route::post('/surveys', [SurveyController::class, 'store']);
    Route::post('/surveys/{survey}/share', [SurveyShareController::class, 'store']);
    Route::post('/surveys/{survey}/invite', [InvitationController::class, 'store']);
    Route::post('/surveys/{survey}/questions', [QuestionController::class, 'store']);
    Route::delete('/surveys/{survey}', [SurveyController::class, 'destroy']);
    Route::patch('/surveys/{survey}/close', [SurveyController::class, 'close']);
    Route::patch('/surveys/{survey}/reopen', [SurveyController::class, 'reopen']);
});

Route::middleware(['auth:sanctum', 'role:respondent'])->group(function (): void {
    Route::post('/surveys/{survey}/submit', [SurveySubmissionController::class, 'submit']);
});

Route::prefix('public')->group(function (): void {
    Route::get('/surveys/{share_token}', [PublicSurveyController::class, 'show']);
    Route::get('/invite/{invitation_token}', [PublicInvitationController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function (): void {
    Route::get('/surveys/deleted', [AdminSurveyController::class, 'deleted']);
    Route::patch('/surveys/{survey}/restore', [AdminSurveyController::class, 'restore']);
});
