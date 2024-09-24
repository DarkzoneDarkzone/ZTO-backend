<?php

use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerLevelController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Models\CustomerLevel;
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

    // users
    Route::post('/logout', [UserController::class, 'logout'])->name('logout');
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'getById']);
    Route::patch('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::post('/me', [UserController::class, 'me'])->name('me');

    //// customer
    Route::post('/customer', [CustomerController::class, 'create']);
    Route::get('/customer', [CustomerController::class, 'index']);
    Route::get('/customer/{id}', [CustomerController::class, 'getById']);
    Route::patch('/customer/{id}', [CustomerController::class, 'update']);
    Route::delete('/customer/{id}', [CustomerController::class, 'destroy']);

    //// customer_level
    Route::post('/customer_level', [CustomerLevelController::class, 'create']);
    Route::get('/customer_level', [CustomerLevelController::class, 'index']);
    Route::get('/customer_level/{id}', [CustomerLevelController::class, 'getById']);
    Route::patch('/customer_level/{id}', [CustomerLevelController::class, 'update']);
    Route::delete('/customer_level/{id}', [CustomerLevelController::class, 'destroy']);

    //// currency
    Route::post('/currency', [CurrencyController::class, 'create']);
    Route::get('/currency', [CurrencyController::class, 'index']);
    Route::get('/currency/{id}', [CurrencyController::class, 'getById']);
    Route::patch('/currency/{id}', [CurrencyController::class, 'update']);
    Route::delete('/currency/{id}', [CurrencyController::class, 'destroy']);

    // role & permission
    Route::get('/role', [RoleController::class, 'index']);
    Route::get('/role/{id}', [RoleController::class, 'getById']);
    Route::post('/role', [RoleController::class, 'create']);
    Route::patch('/role/{id}', [RoleController::class, 'update']);
    Route::delete('/role/{id}', [RoleController::class, 'destroy']);

    // department
    Route::get('/department', [DepartmentController::class, 'index']);
    Route::get('/department/{id}', [DepartmentController::class, 'getById']);
    Route::post('/department', [DepartmentController::class, 'create']);
    Route::patch('/department/{id}', [DepartmentController::class, 'update']);
    Route::delete('/department/{id}', [DepartmentController::class, 'destroy']);
});
