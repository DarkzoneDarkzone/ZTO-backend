<?php

namespace App\Http\Controllers;

use App\Exports\ReportAccountingExport;
use App\Exports\ReportIncomeExpensesExport;
use App\Exports\ReportReturnParcelExport;
use App\Http\Resources\ReportAccountingCollection;
use App\Http\Resources\ReportIncomeExpensesCollection;
use App\Http\Resources\ReportReturnParcelCollection;
use App\Models\Balance;
use App\Models\IncomeExpense;
use App\Models\Parcel;
use App\Models\Payment;
use App\Models\ReturnParcel;
use App\Support\Collection;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function reportAccounting(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'start_at' => 'date_format:Y-m-d H:i:s',
            'end_at' => 'date_format:Y-m-d H:i:s',
        ]);

        if ($validator->fails()) {
            $errors_val = $this->ValidatorErrors($validator);
            return response()->json([
                'msg' => 'validator errors',
                'errors' => $errors_val,
                'status' => 'ERROR',
            ], 400);
        }

        try {
            $subQuery = Payment::select(
                'payments.id',
                'payments.payment_no',
                'payments.amount_lak',
                'payments.created_at',
                'payments.deleted_at',
                DB::raw('(CASE WHEN payments.method = "cash" THEN payments.amount_lak ELSE 0 END) as cash'),
                DB::raw('(CASE WHEN payments.method = "transffer" THEN payments.amount_lak ELSE 0 END) as transffer'),
                DB::raw('(CASE WHEN payments.method = "alipay" THEN payments.amount_cny ELSE 0 END) as alipay'),
                DB::raw('(CASE WHEN payments.method = "wechat_pay" THEN payments.amount_cny ELSE 0 END) as wechat_pay'),
            )
                ->where(['status' => 'paid', 'active' => 1])
                ->whereNull('deleted_at')
                ->whereHas('Bills', function ($query) {
                    $query->where('bills.status', 'success');
                });

            $lastQuery = Payment::select(
                DB::raw('MAX(payments.id) as id'),
                'payments.payment_no',
                DB::raw('SUM(cash) as cash'),
                DB::raw('SUM(transffer) as transffer'),
                DB::raw('SUM(alipay) as alipay'),
                DB::raw('SUM(wechat_pay) as wechat_pay'),
                DB::raw('SUM(payments.amount_lak) as amount'),
                DB::raw('MAX(payments.created_at) as created_at'),
            )
                ->with('Bills')
                ->fromSub($subQuery, 'payments')
                ->groupBy('payments.payment_no');


            if ($request->has('start_at')) {
                $lastQuery->where('payments.created_at', '>=', $request->query('start_at'));
            }

            if ($request->has('end_at')) {
                $lastQuery->where('payments.created_at', '<=', $request->query('end_at'));
            }

            if ($request->has('sorts')) {
                $arraySorts = explode(',', $request->query('sorts', []));
                foreach ($arraySorts as $sort) {
                    [$field, $direction] = explode(':', $sort);
                    $lastQuery->orderBy($field, $direction);
                }
            }

            $reports = ReportAccountingCollection::collection($lastQuery->get());
            if ($request->has('per_page') && $request->query('page')) {
                $reports = (new Collection($reports))->paginate($request->query('per_page'));
            }

            return response()->json([
                'code' => 200,
                'status' => 'OK',
                'data' => $reports,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'msg' => $e->getMessage(),
                'status' => 'ERROR',
                'error' => array(),
                'code' => 400
            ], 400);
        }
    }

    public function exportReportAccounting(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'start_at' => 'date_format:Y-m-d H:i:s',
            'end_at' => 'date_format:Y-m-d H:i:s',
        ]);

        if ($validator->fails()) {
            $errors_val = $this->ValidatorErrors($validator);
            return response()->json([
                'msg' => 'validator errors',
                'errors' => $errors_val,
                'status' => 'ERROR',
            ], 400);
        }

        try {
            $subQuery = Payment::select(
                'payments.id',
                'payments.payment_no',
                'payments.amount_lak',
                'payments.created_at',
                'payments.deleted_at',
                DB::raw('(CASE WHEN payments.method = "cash" THEN payments.amount_lak ELSE 0 END) as cash'),
                DB::raw('(CASE WHEN payments.method = "transffer" THEN payments.amount_lak ELSE 0 END) as transffer'),
                DB::raw('(CASE WHEN payments.method = "alipay" THEN payments.amount_cny ELSE 0 END) as alipay'),
                DB::raw('(CASE WHEN payments.method = "wechat_pay" THEN payments.amount_cny ELSE 0 END) as wechat_pay'),
            )
                ->where(['status' => 'paid', 'active' => 1])
                ->whereNull('deleted_at')
                ->whereHas('Bills', function ($query) {
                    $query->where('bills.status', 'success');
                });

            $lastQuery = Payment::select(
                DB::raw('MAX(payments.id) as id'),
                'payments.payment_no',
                DB::raw('SUM(cash) as cash'),
                DB::raw('SUM(transffer) as transffer'),
                DB::raw('SUM(alipay) as alipay'),
                DB::raw('SUM(wechat_pay) as wechat_pay'),
                DB::raw('SUM(payments.amount_lak) as amount'),
                DB::raw('MAX(payments.created_at) as created_at'),
            )
                ->with('Bills')
                ->fromSub($subQuery, 'payments')
                ->groupBy('payments.payment_no');


            if ($request->has('start_at')) {
                $lastQuery->where('payments.created_at', '>=', $request->query('start_at'));
            }

            if ($request->has('end_at')) {
                $lastQuery->where('payments.created_at', '<=', $request->query('end_at'));
            }

            $reports = ReportAccountingCollection::collection($lastQuery->get())->toResponse($request);

            $reports = $reports->getData()->data;

            return Excel::download(new ReportAccountingExport($reports), 'reports-accounting-' . Carbon::now()->format('Y-m-d') . '.xlsx');
        } catch (Exception $e) {
            return response()->json([
                'msg' => $e->getMessage(),
                'status' => 'ERROR',
                'error' => array(),
                'code' => 400
            ], 400);
        }
    }


    public function reportReturnParcel(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'start_at' => 'date_format:Y-m-d H:i:s',
            'end_at' => 'date_format:Y-m-d H:i:s',
        ]);

        if ($validator->fails()) {
            $errors_val = $this->ValidatorErrors($validator);
            return response()->json([
                'msg' => 'validator errors',
                'errors' => $errors_val,
                'status' => 'ERROR',
            ], 400);
        }

        try {
            $returnParcel = ReturnParcel::select('return_parcels.*', 'parcels.track_no')->with('Parcel')->join('parcels', 'parcels.id', '=', 'return_parcels.parcel_id');
            if ($request->has('start_at')) {
                $returnParcel->where('return_parcels.created_at', '>=', $request->query('start_at'));
            }

            if ($request->has('end_at')) {
                $returnParcel->where('return_parcels.created_at', '<=', $request->query('end_at'));
            }

            if ($request->has('sorts')) {
                $arraySorts = explode(',', $request->query('sorts', []));
                foreach ($arraySorts as $sort) {
                    [$field, $direction] = explode(':', $sort);
                    $returnParcel->orderBy($field, $direction);
                }
            }

            $reports = ReportReturnParcelCollection::collection($returnParcel->get());
            if ($request->has('per_page') && $request->query('page')) {
                $reports = (new Collection($reports))->paginate($request->query('per_page'));
            }

            return response()->json([
                'code' => 200,
                'status' => 'OK',
                'data' => $reports,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'msg' => $e->getMessage(),
                'status' => 'ERROR',
                'error' => array(),
                'code' => 400
            ], 400);
        }
    }

    public function exportReportReturnParcel(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'start_at' => 'date_format:Y-m-d H:i:s',
            'end_at' => 'date_format:Y-m-d H:i:s',
        ]);

        if ($validator->fails()) {
            $errors_val = $this->ValidatorErrors($validator);
            return response()->json([
                'msg' => 'validator errors',
                'errors' => $errors_val,
                'status' => 'ERROR',
            ], 400);
        }

        try {
            $returnParcel = ReturnParcel::select('return_parcels.*', 'parcels.track_no')->with('Parcel')->join('parcels', 'parcels.id', '=', 'return_parcels.parcel_id');

            if ($request->has('start_at')) {
                $returnParcel->where('return_parcels.created_at', '>=', $request->query('start_at'));
            }

            if ($request->has('end_at')) {
                $returnParcel->where('return_parcels.created_at', '<=', $request->query('end_at'));
            }

            $reports = ReportReturnParcelCollection::collection($returnParcel->get())->toResponse($request);

            $reports = $reports->getData()->data;

            return Excel::download(new ReportReturnParcelExport($reports), 'reports-return-parcel-' . Carbon::now()->format('Y-m-d') . '.xlsx');
        } catch (Exception $e) {
            return response()->json([
                'msg' => $e->getMessage(),
                'status' => 'ERROR',
                'error' => array(),
                'code' => 400
            ], 400);
        }
    }

    public function reportIncomeExpenses(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'start_at' => 'date_format:Y-m-d H:i:s',
            'end_at' => 'date_format:Y-m-d H:i:s',
        ]);

        if ($validator->fails()) {
            $errors_val = $this->ValidatorErrors($validator);
            return response()->json([
                'msg' => 'validator errors',
                'errors' => $errors_val,
                'status' => 'ERROR',
            ], 400);
        }

        try {
            $subQuery = Balance::select(
                'balances.id',
                'balances.created_at',
                'balances.amount_lak',
                'balances.amount_cny',
                'balances.balance_amount_lak',
                'balances.balance_amount_cny',
                'payments.payment_no',
                'balances.payment_id',
                'balances.deleted_at',
                'income_expenses.id as income_id',
                'income_expenses.type',
                'income_expenses.sub_type',
                'income_expenses.description',
                DB::raw('(CASE WHEN income_expenses.type = "income" AND income_expenses.sub_type = "other" THEN income_expenses.amount_lak ELSE 0 END) as top_up_lak'),
                DB::raw('(CASE WHEN income_expenses.type = "income" AND income_expenses.sub_type = "other" THEN income_expenses.amount_cny ELSE 0 END) as top_up_cny'),
                DB::raw('(CASE WHEN income_expenses.type = "income" AND income_expenses.sub_type = "return" THEN income_expenses.amount_lak ELSE 0 END) as shipping_lak'),
                DB::raw('(CASE WHEN income_expenses.type = "income" AND income_expenses.sub_type = "return" THEN income_expenses.amount_cny ELSE 0 END) as shipping_cny'),
                DB::raw('(CASE WHEN income_expenses.type = "expenses" AND (income_expenses.sub_type = "refund" OR income_expenses.sub_type = "other") THEN income_expenses.amount_lak ELSE 0 END) as expenses_lak'),
                DB::raw('(CASE WHEN income_expenses.type = "expenses" AND (income_expenses.sub_type = "refund" OR income_expenses.sub_type = "other") THEN income_expenses.amount_cny ELSE 0 END) as expenses_cny'),
            )->leftJoin('payments', 'balances.payment_id', '=', 'payments.id')
                ->leftJoin('income_expenses', 'balances.income_id', '=', 'income_expenses.id')
                ->whereNull('balances.deleted_at')
                ->where(function ($query) {
                    $query->orWhere(function ($sub_query) {
                        $sub_query->where('payments.status', 'paid')
                            ->whereNull('payments.deleted_at')
                            ->where('payments.active', 1);
                    });
                    $query->orWhere(function ($sub_query) {
                        $sub_query->Where('income_expenses.status', 'verify')
                            ->whereNull('income_expenses.deleted_at');
                    });
                });
            $lastQuery = Balance::select(
                DB::raw('MAX(SubBalances.id) as id'),
                DB::raw('MAX(SubBalances.created_at) as created_at'),
                DB::raw('MAX(SubBalances.payment_no) as payment_no'),
                DB::raw('MAX(SubBalances.payment_id) as payment_id'),
                DB::raw('MAX(SubBalances.income_id) as income_id'),
                DB::raw('MAX(SubBalances.description) as description'),
                DB::raw('SUM(SubBalances.amount_lak) as amount_lak'),
                DB::raw('SUM(SubBalances.amount_cny) as amount_cny'),
                DB::raw('MAX(SubBalances.type) as type'),
                DB::raw('MAX(SubBalances.sub_type) as sub_type'),
                DB::raw('MAX(SubBalances.balance_amount_lak) as balance_amount_lak'),
                DB::raw('MAX(SubBalances.balance_amount_cny) as balance_amount_cny'),
                DB::raw('SUM(top_up_lak) as top_up_lak'),
                DB::raw('SUM(top_up_cny) as top_up_cny'),
                DB::raw('SUM(shipping_lak) as shipping_lak'),
                DB::raw('SUM(shipping_cny) as shipping_cny'),
                DB::raw('SUM(expenses_lak) as expenses_lak'),
                DB::raw('SUM(expenses_cny) as expenses_cny'),
            )
                ->with('Payment', 'Payment.Bills')
                ->fromSub($subQuery, 'SubBalances')
                ->whereNull('SubBalances.deleted_at')
                ->groupBy('SubBalances.payment_no', 'SubBalances.income_id');

            if ($request->has('start_at')) {
                $lastQuery->where('created_at', '>=', $request->query('start_at'));
            }

            if ($request->has('end_at')) {
                $lastQuery->where('created_at', '<=', $request->query('end_at'));
            }

            if ($request->has('sorts')) {
                $arraySorts = explode(',', $request->query('sorts', []));
                foreach ($arraySorts as $sort) {
                    [$field, $direction] = explode(':', $sort);
                    $lastQuery->orderBy($field, $direction);
                }
            }
            
            $reports = ReportIncomeExpensesCollection::collection($lastQuery->get());
            if ($request->has('per_page') && $request->query('page')) {
                $reports = (new Collection($reports))->paginate($request->query('per_page'));
            }

            return response()->json([
                'code' => 200,
                'status' => 'OK',
                'data' => $reports,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'msg' => $e->getMessage(),
                'status' => 'ERROR',
                'error' => array(),
                'code' => 400
            ], 400);
        }
    }

    public function exportReportIncomeExpenses(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'start_at' => 'date_format:Y-m-d H:i:s',
            'end_at' => 'date_format:Y-m-d H:i:s',
        ]);

        if ($validator->fails()) {
            $errors_val = $this->ValidatorErrors($validator);
            return response()->json([
                'msg' => 'validator errors',
                'errors' => $errors_val,
                'status' => 'ERROR',
            ], 400);
        }

        try {
            $subQuery = Balance::select(
                'balances.id',
                'balances.created_at',
                'balances.amount_lak',
                'balances.amount_cny',
                'balances.balance_amount_lak',
                'balances.balance_amount_cny',
                'payments.payment_no',
                'balances.payment_id',
                'income_expenses.id as income_id',
                'income_expenses.type',
                'income_expenses.sub_type',
                'income_expenses.description',
                DB::raw('(CASE WHEN income_expenses.type = "income" AND income_expenses.sub_type = "other" THEN income_expenses.amount_lak ELSE 0 END) as top_up_lak'),
                DB::raw('(CASE WHEN income_expenses.type = "income" AND income_expenses.sub_type = "other" THEN income_expenses.amount_cny ELSE 0 END) as top_up_cny'),
                DB::raw('(CASE WHEN income_expenses.type = "income" AND income_expenses.sub_type = "return" THEN income_expenses.amount_lak ELSE 0 END) as shipping_lak'),
                DB::raw('(CASE WHEN income_expenses.type = "income" AND income_expenses.sub_type = "return" THEN income_expenses.amount_cny ELSE 0 END) as shipping_cny'),
                DB::raw('(CASE WHEN income_expenses.type = "expenses" AND (income_expenses.sub_type = "refund" OR income_expenses.sub_type = "other") THEN income_expenses.amount_lak ELSE 0 END) as expenses_lak'),
                DB::raw('(CASE WHEN income_expenses.type = "expenses" AND (income_expenses.sub_type = "refund" OR income_expenses.sub_type = "other") THEN income_expenses.amount_cny ELSE 0 END) as expenses_cny'),
            )->leftJoin('payments', 'balances.payment_id', '=', 'payments.id')
                ->leftJoin('income_expenses', 'balances.income_id', '=', 'income_expenses.id')
                ->whereNull('balances.deleted_at')
                ->where(function ($query) {
                    $query->orWhere(function ($sub_query) {
                        $sub_query->where('payments.status', 'paid')
                            ->whereNull('payments.deleted_at')
                            ->where('payments.active', 1);
                    });
                    $query->orWhere(function ($sub_query) {
                        $sub_query->Where('income_expenses.status', 'verify')
                            ->whereNull('income_expenses.deleted_at');
                    });
                });

            $lastQuery = Balance::select(
                DB::raw('MAX(SubBalances.id) as id'),
                DB::raw('MAX(SubBalances.created_at) as created_at'),
                DB::raw('MAX(SubBalances.payment_no) as payment_no'),
                DB::raw('MAX(SubBalances.payment_id) as payment_id'),
                DB::raw('MAX(SubBalances.income_id) as income_id'),
                DB::raw('MAX(SubBalances.description) as description'),
                DB::raw('SUM(SubBalances.amount_lak) as amount_lak'),
                DB::raw('SUM(SubBalances.amount_cny) as amount_cny'),
                DB::raw('MAX(SubBalances.type) as type'),
                DB::raw('MAX(SubBalances.sub_type) as sub_type'),
                DB::raw('SUM(SubBalances.balance_amount_lak) as balance_amount_lak'),
                DB::raw('SUM(SubBalances.balance_amount_cny) as balance_amount_cny'),
                DB::raw('SUM(top_up_lak) as top_up_lak'),
                DB::raw('SUM(top_up_cny) as top_up_cny'),
                DB::raw('SUM(shipping_lak) as shipping_lak'),
                DB::raw('SUM(shipping_cny) as shipping_cny'),
                DB::raw('SUM(expenses_lak) as expenses_lak'),
                DB::raw('SUM(expenses_cny) as expenses_cny'),
            )
                ->with('Payment', 'Payment.Bills')
                ->fromSub($subQuery, 'SubBalances')
                ->groupBy('SubBalances.payment_no', 'SubBalances.income_id');

            if ($request->has('start_at')) {
                $lastQuery->where('created_at', '>=', $request->query('start_at'));
            }

            if ($request->has('end_at')) {
                $lastQuery->where('created_at', '<=', $request->query('end_at'));
            }

            if ($request->has('sorts')) {
                $arraySorts = explode(',', $request->query('sorts', []));
                foreach ($arraySorts as $sort) {
                    [$field, $direction] = explode(':', $sort);
                    $lastQuery->orderBy($field, $direction);
                }
            }

            $reports = ReportIncomeExpensesCollection::collection($lastQuery->get())->toResponse($request);

            $reports = $reports->getData()->data;
            
            return Excel::download(new ReportIncomeExpensesExport($reports), 'reports-income-expenses-' . Carbon::now()->format('Y-m-d') . '.xlsx');
        } catch (Exception $e) {
            return response()->json([
                'msg' => $e->getMessage(),
                'status' => 'ERROR',
                'error' => array(),
                'code' => 400
            ], 400);
        }
    }

    public function reportDailyReport(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'start_at' => 'date_format:Y-m-d H:i:s',
            'end_at' => 'date_format:Y-m-d H:i:s',
        ]);

        if ($validator->fails()) {
            $errors_val = $this->ValidatorErrors($validator);
            return response()->json([
                'msg' => 'validator errors',
                'errors' => $errors_val,
                'status' => 'ERROR',
            ], 400);
        }

        try {
            $responseData = [];

            if($request->start_at && $request->end_at) {
                $parcelImportQuery = Parcel::select(
                    DB::raw('COUNT(parcels.id) as qty_actual'),
                    DB::raw('SUM(CASE WHEN bill_id IS NOT NULL AND parcels.status <> "success" THEN 1 ELSE 0 END) as qty_create_bill'),
                    DB::raw('SUM(CASE WHEN bill_id IS NOT NULL AND parcels.status <> "success" THEN weight ELSE 0 END) as weight_create_bill'),
                    DB::raw('SUM(CASE WHEN bill_id IS NOT NULL THEN bills.amount_lak ELSE 0 END) as total_lak'),
                    DB::raw('SUM(CASE WHEN bill_id IS NOT NULL THEN bills.amount_lak ELSE 0 END) / SUM(CASE WHEN bill_id IS NOT NULL AND parcels.status <> "success" THEN weight ELSE 0 END) as price_per_weight'),
                    DB::raw('DATE(parcels.created_at) as date'),
                )
                    ->leftJoin('bills', 'parcels.bill_id', '=', 'bills.id')
                    ->whereNull('parcels.deleted_at')
                    ->whereBetween('parcels.created_at', [$request->start_at, $request->end_at])
                    ->groupBy(DB::raw('DATE(parcels.created_at)'))->get();

                $responseData['import_parcels'] = [
                    'start_date' => $parcelImportQuery->count() > 0 ? $parcelImportQuery->min('date') : null,
                    'end_date' => $parcelImportQuery->count() > 0 ? $parcelImportQuery->max('date') : null,
                    'group' => 'Import Parcels',
                    'description' => "Import parcels",
                    'import_actual' => $parcelImportQuery->sum('qty_actual'),
                    'qty_create_bill' => $parcelImportQuery->sum('qty_create_bill'),
                    'weight_create_bill' => number_format($parcelImportQuery->sum('weight_create_bill'), 2),
                    'total_lak' => number_format($parcelImportQuery->sum('total_lak'), 2),
                    'price_per_weight' => number_format($parcelImportQuery->avg('price_per_weight'), 2),
                    'payment_total_lak' => null,
                    'payment_total_cny' => null,
                    'payment_cash' => null,
                    'payment_transfer' => null,
                    'payment_alipay' => null,
                    'payment_wechatpay' => null,
                ];
            } else {
                $parcelImportQuery = Parcel::select(
                    DB::raw('COUNT(parcels.id) as qty_actual'),
                    DB::raw('SUM(CASE WHEN bill_id IS NOT NULL AND parcels.status <> "success" THEN 1 ELSE 0 END) as qty_create_bill'),
                    DB::raw('SUM(CASE WHEN bill_id IS NOT NULL AND parcels.status <> "success" THEN weight ELSE 0 END) as weight_create_bill'),
                    DB::raw('SUM(CASE WHEN bill_id IS NOT NULL THEN bills.amount_lak ELSE 0 END) as total_lak'),
                    DB::raw('SUM(CASE WHEN bill_id IS NOT NULL THEN bills.amount_lak ELSE 0 END) / SUM(CASE WHEN bill_id IS NOT NULL AND parcels.status <> "success" THEN weight ELSE 0 END) as price_per_weight'),
                    DB::raw('DATE(parcels.created_at) as date'),
                )
                    ->leftJoin('bills', 'parcels.bill_id', '=', 'bills.id')
                    ->whereNull('parcels.deleted_at')
                    ->whereDate('parcels.created_at', Carbon::now())
                    ->groupBy(DB::raw('DATE(parcels.created_at)'))->get();

                $responseData['import_parcels'] = [
                    'start_date' => Carbon::now()->format('Y-m-d'),
                    'end_date' => Carbon::now()->format('Y-m-d'),
                    'group' => 'Import Parcels',
                    'description' => "Import parcels",
                    'import_actual' => $parcelImportQuery->sum('qty_actual'),
                    'qty_create_bill' => $parcelImportQuery->sum('qty_create_bill'),
                    'weight_create_bill' => number_format($parcelImportQuery->sum('weight_create_bill'), 2),
                    'total_lak' => number_format($parcelImportQuery->sum('total_lak'), 2),
                    'price_per_weight' => number_format($parcelImportQuery->avg('price_per_weight'), 2),
                    'payment_total_lak' => null,
                    'payment_total_cny' => null,
                    'payment_cash' => null,
                    'payment_transfer' => null,
                    'payment_alipay' => null,
                    'payment_wechatpay' => null,
                ];
            }

            // mock data
            $responseData['parcel_shipped_success'] = [
                'start_date' => Carbon::now()->format('Y-m-d'),
                'end_date' => Carbon::now()->format('Y-m-d'),
                'group' => 'Shipped (Success)',
                'description' => 'Shipped (Success)',
                'import_actual' => null,
                'qty_create_bill' => '-400',
                'weight_create_bill' => '-200',
                'total_lak' => number_format(400000.457, 2),
                'price_per_weight' => number_format(250, 2),
                'payment_total_lak' => number_format(400000.457, 2),
                'payment_total_cny' => number_format(4000.45, 2),
                'payment_cash' => number_format(4000, 2),
                'payment_transfer' => number_format(4000, 2),
                'payment_alipay' => number_format(4000, 2),
                'payment_wechatpay' => number_format(4000, 2),
            ];

            // mock data
            $responseData['in_stock'] = [
                'start_date' => Carbon::now()->format('Y-m-d'),
                'end_date' => Carbon::now()->format('Y-m-d'),
                'group' => '',
                'description' => 'In Stock',
                'import_actual' => null,
                'qty_create_bill' => '400',
                'weight_create_bill' => '200',
                'total_lak' => number_format(400000.457, 2),
                'price_per_weight' => null,
                'payment_total_lak' => null,
                'payment_total_cny' => null,
                'payment_cash' => null,
                'payment_transfer' => null,
                'payment_alipay' => null,
                'payment_wechatpay' => null,
            ];
            
            $responseData['import_parcels_forsale'] = [
                'start_date' => $responseData['import_parcels']['start_date'],
                'end_date' => $responseData['import_parcels']['end_date'],
                'group' => 'Import Parcels - Forsale',
                'description' => '',
                'import_actual' => $responseData['import_parcels']['import_actual'],
                'qty_create_bill' => $responseData['import_parcels']['qty_create_bill'],
                'weight_create_bill' => $responseData['import_parcels']['weight_create_bill'],
                'total_lak' => $responseData['import_parcels']['total_lak'],
                'price_per_weight' => $responseData['import_parcels']['price_per_weight'],
                'payment_total_lak' => null,
                'payment_total_cny' => null,
                'payment_cash' => null,
                'payment_transfer' => null,
                'payment_alipay' => null,
                'payment_wechatpay' => null,
            ];

            // forbuy
            if($request->start_at && $request->end_at) {
                $parcelForbuyQuery = Parcel::select(
                    DB::raw('COUNT(parcels.id) as qty_actual'),
                    DB::raw('SUM(parcels.id) as qty_create_bill'),
                    DB::raw('SUM(weight) as weight_create_bill'),
                    DB::raw('SUM(price) as total_lak'),
                    DB::raw('SUM(price) / SUM(weight) as price_per_weight'),
                    DB::raw('DATE(parcels.created_at) as date'),
                )
                    ->whereNull('parcels.deleted_at')
                    ->whereBetween('parcels.created_at', [$request->start_at, $request->end_at])
                    ->groupBy(DB::raw('DATE(parcels.created_at)'))->get();

                $responseData['import_parcels_forbuy'] = [
                    'start_date' => $parcelForbuyQuery->count() > 0 ? $parcelForbuyQuery->min('date') : null,
                    'end_date' => $parcelForbuyQuery->count() > 0 ? $parcelForbuyQuery->max('date') : null,
                    'group' => 'Import Parcels - ForBuy',
                    'description' => "",
                    'import_actual' => $parcelForbuyQuery->sum('qty_actual'),
                    'qty_create_bill' => $parcelForbuyQuery->sum('qty_create_bill'),
                    'weight_create_bill' => number_format($parcelForbuyQuery->sum('weight_create_bill'), 2),
                    'total_lak' => number_format($parcelForbuyQuery->sum('total_lak'), 2),
                    'price_per_weight' => number_format($parcelForbuyQuery->avg('price_per_weight'), 2),
                    'payment_total_lak' => null,
                    'payment_total_cny' => null,
                    'payment_cash' => null,
                    'payment_transfer' => null,
                    'payment_alipay' => null,
                    'payment_wechatpay' => null,
                ];
            } else {
                $parcelForbuyQuery = Parcel::select(
                    DB::raw('COUNT(parcels.id) as qty_actual'),
                    DB::raw('SUM(parcels.id) as qty_create_bill'),
                    DB::raw('SUM(weight) as weight_create_bill'),
                    DB::raw('SUM(price) as total_lak'),
                    DB::raw('SUM(price) / SUM(weight) as price_per_weight'),
                    DB::raw('DATE(parcels.created_at) as date'),
                )
                    ->whereNull('parcels.deleted_at')
                    ->whereDate('parcels.created_at', Carbon::now())
                    ->groupBy(DB::raw('DATE(parcels.created_at)'))->get();
    
                $responseData['import_parcels_forbuy'] = [
                    'start_date' => Carbon::now()->format('Y-m-d'),
                    'end_date' => Carbon::now()->format('Y-m-d'),
                    'group' => 'Import Parcels - ForBuy',
                    'description' => "",
                    'import_actual' => $parcelForbuyQuery->sum('qty_actual'),
                    'qty_create_bill' => $parcelForbuyQuery->sum('qty_create_bill'),
                    'weight_create_bill' => number_format($parcelForbuyQuery->sum('weight_create_bill'), 2),
                    'total_lak' => number_format($parcelForbuyQuery->sum('total_lak'), 2),
                    'price_per_weight' => number_format($parcelForbuyQuery->avg('price_per_weight'), 2),
                    'payment_total_lak' => null,
                    'payment_total_cny' => null,
                    'payment_cash' => null,
                    'payment_transfer' => null,
                    'payment_alipay' => null,
                    'payment_wechatpay' => null,
                ];
            }

            if($request->query('start_at') && $request->query('end_at')) {
                $responseData['income_other'] = IncomeExpense::where('type', 'income')
                    ->where('sub_type', 'other')
                    ->whereNull('deleted_at')
                    ->where('status', 'verify')
                    ->where('created_at', '>=', $request->query('start_at'))
                    ->where('created_at', '<=', $request->query('end_at'))
                    ->get();
    
                $responseData['expenses_other'] = IncomeExpense::where('type', 'expenses')
                    ->where('sub_type', 'other')
                    ->whereNull('deleted_at')
                    ->where('status', 'verify')
                    ->where('created_at', '>=', $request->query('start_at'))
                    ->where('created_at', '<=', $request->query('end_at'))
                    ->get();
    
                $responseData['income_top_up'] = IncomeExpense::where('type', 'income')
                    ->where('sub_type', 'top_up')
                    ->whereNull('deleted_at')
                    ->where('status', 'verify')
                    ->where('created_at', '>=', $request->query('start_at'))
                    ->where('created_at', '<=', $request->query('end_at'))
                    ->get();
            } else {
                $responseData['income_other'] = IncomeExpense::
                    // where('type', 'income')
                    // ->where('sub_type', 'other')
                    whereNull('deleted_at')
                    // ->where('status', 'verify')
                    // ->where('created_at', '>=', Carbon::now()->format('Y-m-d'))
                    // ->where('created_at', '<=', Carbon::now()->format('Y-m-d'))
                    ->get();
    
                $responseData['expenses_other'] = IncomeExpense::where('type', 'expenses')
                    ->where('sub_type', 'other')
                    ->whereNull('deleted_at')
                    ->where('status', 'verify')
                    ->where('created_at', '>=', Carbon::now()->format('Y-m-d'))
                    ->where('created_at', '<=', Carbon::now()->format('Y-m-d'))
                    ->get();
    
                $responseData['income_top_up'] = IncomeExpense::where('type', 'income')
                    ->where('sub_type', 'top_up')
                    ->whereNull('deleted_at')
                    ->where('status', 'verify')
                    ->where('created_at', '>=', Carbon::now()->format('Y-m-d'))
                    ->where('created_at', '<=', Carbon::now()->format('Y-m-d'))
                    ->get();
            }

            return response()->json([
                'code' => 200,
                'status' => 'OK',
                'data' => $responseData,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'msg' => $e->getMessage(),
                'status' => 'ERROR',
                'error' => array(),
                'code' => 400
            ], 400);
        }
    }
}
