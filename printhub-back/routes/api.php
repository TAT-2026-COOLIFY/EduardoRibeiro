<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FilamentController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\RegisterController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Rota Pública
//Auth
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

//Register
Route::post('/register', [RegisterController::class, 'register'])->middleware('throttle:register');

// Rotas Protegidas
Route::middleware('auth:sanctum')->group(function () {
    
    //User
    Route::get('me', [UserController::class, 'me']);
    Route::apiResource('users', UserController::class);

    //Filament
    Route::apiResource('filaments', FilamentController::class);


    //Job
    Route::patch('jobs/{job}/status', [JobController::class, 'update']);
    Route::apiResource('jobs', JobController::class);

    Route::get('settings', [SettingController::class, 'show']);
    Route::put('settings', [SettingController::class, 'update']);

    // Auth Logout
    Route::delete('logout', [AuthController::class, 'logout']);
});
