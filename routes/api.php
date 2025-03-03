<?php

use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerLevelController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\IncomeExpenseController;
use App\Http\Controllers\ParcelController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ZtoBalanceCreditController;
use App\Models\CustomerLevel;
use App\Models\IncomeExpense;
use App\Models\Parcel;
use Database\Seeders\UserSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::get('health_check', function () {
    return 'ok';
});

Route::get('/run-migrations', function () {
    // return Artisan::call('migrate', array('--path' => 'app/migrations'));
    return Artisan::call('migrate', ["--force" => true ]);
});

Route::get('/run-seeder', function () {
    // return Artisan::call('db:seed', ["--force" => true ]);
    return Artisan::call('db:seed');

});

// Route::get('/run-migrations-timezone7', function () {
//     try {
//         $migrationPath = database_path('database/migrations/2024_11_03_073400_add_zto_track_to_parcels_table.php');
//         Artisan::call('migrate', [
//             '--path' => $migrationPath,
//             '--force' => true, 
//             // '--database' => 'tradings_zto_db'
//         ]);
        
//         return "Migration for 'posts' table ran successfully.";
//     } catch (\Exception $e) {
//         return "Error: " . $e->getMessage();
//     }
// });

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

    // customer
    Route::get('/customer', [CustomerController::class, 'index']);
    Route::get('/customer/{id}', [CustomerController::class, 'getById']);
    Route::post('/customer', [CustomerController::class, 'create']);
    Route::patch('/customer/{id}', [CustomerController::class, 'update']);
    Route::delete('/customer/{id}', [CustomerController::class, 'destroy']);

    // customer_level
    Route::get('/customer_level', [CustomerLevelController::class, 'index']);
    Route::get('/customer_level/{id}', [CustomerLevelController::class, 'getById']);
    Route::post('/customer_level', [CustomerLevelController::class, 'create']);
    Route::patch('/customer_level/{id}', [CustomerLevelController::class, 'update']);
    Route::delete('/customer_level/{id}', [CustomerLevelController::class, 'destroy']);

    // currency
    Route::get('/currency', [CurrencyController::class, 'index']);
    Route::get('/currency/{id}', [CurrencyController::class, 'getById']);
    Route::post('/currency', [CurrencyController::class, 'create']);
    Route::patch('/currency/{id}', [CurrencyController::class, 'update']);
    Route::delete('/currency/{id}', [CurrencyController::class, 'destroy']);

    // payment
    Route::get('/payment', [PaymentController::class, 'index']);
    Route::get('/payment/{id}', [PaymentController::class, 'getByPaymentNo']);
    Route::post('/payment', [PaymentController::class, 'create']);
    Route::patch('/payment/{id}', [PaymentController::class, 'updatePaymentNo']);
    Route::delete('/payment/{id}', [PaymentController::class, 'destroy']);

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

    // parcel
    Route::post('/return', [ParcelController::class, 'create']);
    Route::post('/extract', [ParcelController::class, 'import']);
    Route::get('/export', [ParcelController::class, 'export']);
    Route::get('/parcel', [ParcelController::class, 'index']);
    Route::put('/parcel/{id}', [ParcelController::class, 'update']);
    Route::post('/parcel-check', [ParcelController::class, 'update_check']);

    // bill
    Route::get('/bill', [BillController::class, 'index']);
    Route::get('/bill/{id}', [BillController::class, 'getById']);
    Route::post('/bill', [BillController::class, 'createBill']);
    Route::post('/shipping', [BillController::class, 'createShipping']);
    Route::post('/shipping-payment', [BillController::class, 'createShippingPayment']);
    Route::patch('/bill/{id}', [BillController::class, 'updateBill']);
    Route::delete('/bill/{id}', [BillController::class, 'destroy']);

    // income-expenses
    Route::get('/income-expenses', [IncomeExpenseController::class, 'index']);
    Route::get('/income-expenses/{id}', [IncomeExpenseController::class, 'getById']);
    Route::post('/income', [IncomeExpenseController::class, 'createIncome']);
    Route::patch('/income/{id}', [IncomeExpenseController::class, 'updateIncome']);
    Route::post('/expenses', [IncomeExpenseController::class, 'createExpense']);
    Route::patch('/expenses/{id}', [IncomeExpenseController::class, 'updateExpense']);
    Route::patch('/income-expenses/{id}', [IncomeExpenseController::class, 'updateStatus']);
    Route::delete('/income-expenses/{id}', [IncomeExpenseController::class, 'destroy']);

    // reports
    Route::get('/report/account', [ReportController::class, 'reportAccounting']);
    Route::get('/export/account', [ReportController::class, 'exportReportAccounting']);
    Route::get('/report/return-parcel', [ReportController::class, 'reportReturnParcel']);
    Route::get('/export/return-parcel', [ReportController::class, 'exportReportReturnParcel']);
    Route::get('/report/income-expenses', [ReportController::class, 'reportIncomeExpenses']);
    Route::get('/export/income-expenses', [ReportController::class, 'exportReportIncomeExpenses']);
    Route::get('/report/daily-report', [ReportController::class, 'reportDailyReport']);

    // credit - topup
    Route::get('/credit/topup', [ZtoBalanceCreditController::class, 'getTopup']);
    Route::post('/credit/topup', [ZtoBalanceCreditController::class, 'createTopup']);
    Route::get('/credit/report-topup', [ZtoBalanceCreditController::class, 'reportParcelTopup']);

});
