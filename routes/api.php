<?php

use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => 'api',
], function ($router) {
    Route::post('/users', [UserController::class, 'register'])->name('register');
    Route::post('/login', [UserController::class, 'login'])->name('login');
});

Route::middleware('auth:api')->group(function () {
    // Route::post('/refresh', [UserController::class, 'refresh'])->name('refresh');
    Route::post('/logout', [UserController::class, 'logout'])->name('logout');
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'getById']);
    Route::patch('/users/{id}', [UserController::class, 'update']);
    Route::post('/me', [UserController::class, 'me'])->name('me');

    //// customer
    Route::post('/customer', [CustomerController::class, 'create']);
    Route::get('/customer', [CustomerController::class, 'index']);

    // role & permission
    Route::post('/role', [RoleController::class, 'create']);
});
