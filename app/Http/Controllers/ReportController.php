<?php

namespace App\Http\Controllers;

use App\Exports\ReportAccountingExport;
use App\Exports\ReportIncomeExpensesExport;
use App\Exports\ReportReturnParcelExport;
use App\Http\Resources\ReportAccountingCollection;
use App\Http\Resources\ReportIncomeExpensesCollection;
use App\Http\Resources\ReportReturnParcelCollection;
use App\Models\Balance;
use App\Models\Bill;
use App\Models\IncomeExpense;
use App\Models\Parcel;
use App\Models\Payment;
use App\Models\ReturnParcel;
use App\Support\Collection;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Query\JoinClause;
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

            if ($request->start_at && $request->end_at) {
                $parcelImportQuery = Parcel::select(
                    DB::raw('COUNT(parcels.id) as qty_actual'),
                    DB::raw('SUM(CASE WHEN bill_id IS NOT NULL AND parcels.status <> "pending" THEN 1 ELSE 0 END) as qty_create_bill'),
                    DB::raw('SUM(CASE WHEN bill_id IS NOT NULL AND parcels.status <> "pending" THEN weight ELSE 0 END) as weight_create_bill'),
                    DB::raw('SUM(CASE WHEN bill_id IS NOT NULL THEN parcels.price_bill ELSE 0 END) as total_lak'),
                    DB::raw('SUM(CASE WHEN bill_id IS NOT NULL THEN parcels.price_bill ELSE 0 END) / SUM(CASE WHEN bill_id IS NOT NULL AND parcels.status <> "pending" THEN weight ELSE 0 END) as price_per_weight'),
                    DB::raw('DATE(parcels.created_at) as date'),
                )
                    ->leftJoin('bills', 'parcels.bill_id', '=', 'bills.id')
                    ->whereNull('parcels.deleted_at')
                    ->whereBetween('parcels.created_at', [$request->start_at, $request->end_at])
                    ->groupBy(DB::raw('DATE(parcels.created_at)'))->get();

                $responseData['import_parcels'] = [
                    'start_date' => Carbon::createFromFormat('Y-m-d H:i:s',  $request->start_at)->format('Y-m-d'),
                    'end_date' => Carbon::createFromFormat('Y-m-d H:i:s',  $request->end_at)->format('Y-m-d'),
                    'group' => 'Import Parcels',
                    'description' => "Import parcels",
                    'import_actual' => number_format($parcelImportQuery->sum('qty_actual')),
                    'qty_create_bill' => number_format($parcelImportQuery->sum('qty_create_bill')),
                    'weight_create_bill' => number_format($parcelImportQuery->sum('weight_create_bill'), 2),
                    'total_lak' => number_format($parcelImportQuery->sum('total_lak'), 2),
                    'total_cny' => null,
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
                    DB::raw('SUM(CASE WHEN bill_id IS NOT NULL AND parcels.status <> "pending" THEN 1 ELSE 0 END) as qty_create_bill'),
                    DB::raw('SUM(CASE WHEN bill_id IS NOT NULL AND parcels.status <> "pending" THEN weight ELSE 0 END) as weight_create_bill'),
                    DB::raw('SUM(CASE WHEN bill_id IS NOT NULL THEN parcels.price_bill ELSE 0 END) as total_lak'),
                    DB::raw('SUM(CASE WHEN bill_id IS NOT NULL THEN parcels.price_bill ELSE 0 END) / SUM(CASE WHEN bill_id IS NOT NULL AND parcels.status <> "pending" THEN weight ELSE 0 END) as price_per_weight'),
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
                    'import_actual' => number_format($parcelImportQuery->sum('qty_actual')),
                    'qty_create_bill' => number_format($parcelImportQuery->sum('qty_create_bill')),
                    'weight_create_bill' => number_format($parcelImportQuery->sum('weight_create_bill'), 2),
                    'total_lak' => number_format($parcelImportQuery->sum('total_lak'), 2),
                    'total_cny' => null,
                    'price_per_weight' => number_format($parcelImportQuery->avg('price_per_weight'), 2),
                    'payment_total_lak' => null,
                    'payment_total_cny' => null,
                    'payment_cash' => null,
                    'payment_transfer' => null,
                    'payment_alipay' => null,
                    'payment_wechatpay' => null,
                ];
            }


            if ($request->start_at && $request->end_at) {
                $parcelShippedSuccess = Parcel::select(
                    DB::raw('COUNT(parcels.id) as qty_create_bill'),
                    DB::raw('SUM(parcels.price_bill) as total_lak'),
                    DB::raw('SUM(parcels.weight) as weight_create_bill'),
                    DB::raw('DATE(parcels.created_at) as date'),
                    DB::raw('MAX(CASE WHEN bill_id IS NOT NULL THEN currencies.amount_cny ELSE 0 END) as currencies_cny'),
                    DB::raw('MAX(CASE WHEN bill_id IS NOT NULL THEN currencies.amount_lak ELSE 0 END) as currencies_lak'),
                )
                    ->join('bills', 'parcels.bill_id', '=', 'bills.id')
                    ->leftJoin('currencies', DB::raw('DATE(currencies.created_at)'), '=', DB::raw('DATE(parcels.created_at)'))
                    ->whereNull('parcels.deleted_at')
                    ->where('parcels.status', 'success')
                    ->whereBetween('parcels.created_at', [$request->start_at, $request->end_at])
                    ->groupBy(DB::raw('DATE(parcels.created_at)'))->get();

                foreach ($parcelShippedSuccess as $ShippedSuccess) {
                    $ShippedSuccess->total_cny = $ShippedSuccess->total_lak / ($ShippedSuccess->currencies_cny * $ShippedSuccess->currencies_lak);
                }

                $paymentPaid = Payment::select(
                    DB::raw('SUM(CASE WHEN payments.method IS NOT NULL AND payments.method = "cash" OR payments.method = "transffer" THEN payments.amount_lak ELSE 0 END) as amount_lak'),
                    DB::raw('SUM(CASE WHEN payments.method IS NOT NULL AND payments.method = "alipay" OR payments.method = "wechat_pay" THEN payments.amount_cny ELSE 0 END) as amount_cny'),

                    DB::raw('SUM(CASE WHEN payments.method IS NOT NULL AND payments.method = "cash" THEN payments.amount_lak ELSE 0 END) as amount_cash'),
                    DB::raw('SUM(CASE WHEN payments.method IS NOT NULL AND payments.method = "transffer" THEN payments.amount_lak ELSE 0 END) as amount_transfer'),
                    DB::raw('SUM(CASE WHEN payments.method IS NOT NULL AND payments.method = "alipay" THEN payments.amount_cny ELSE 0 END) as amount_alipay'),
                    DB::raw('SUM(CASE WHEN payments.method IS NOT NULL AND payments.method = "wechat_pay" THEN payments.amount_cny ELSE 0 END) as amount_wechat_pay'),
                    DB::raw('payments.method as method'),
                )
                    ->leftJoin('bill_payment', 'bill_payment.payment_id', '=', 'payments.id')
                    ->leftJoin('bills', 'bill_payment.bill_id', '=', 'bills.id')
                    ->leftJoin('parcels', 'parcels.bill_id', '=', 'bills.id')
                    ->where('payments.status', 'paid')
                    ->whereNull('payments.deleted_at')
                    ->whereNull('parcels.deleted_at')
                    ->where('parcels.status', 'success')
                    ->whereBetween('parcels.created_at', [$request->start_at, $request->end_at])
                    ->groupBy('method')->get();

                foreach ($parcelShippedSuccess as $ShippedSuccess) {
                    $ShippedSuccess->total_cny = ceil(($ShippedSuccess->total_lak / ($ShippedSuccess->currencies_cny * $ShippedSuccess->currencies_lak)) * 100) / 100;
                }

                $responseData['parcel_shipped_success'] = [
                    'start_date' => Carbon::createFromFormat('Y-m-d H:i:s',  $request->start_at)->format('Y-m-d'),
                    'end_date' => Carbon::createFromFormat('Y-m-d H:i:s',  $request->end_at)->format('Y-m-d'),
                    'group' => 'Shipped (Success)',
                    'description' => 'Shipped (Success)',
                    'import_actual' => null,
                    'qty_create_bill' => number_format($parcelShippedSuccess->sum('qty_create_bill') * -1),
                    'weight_create_bill' => number_format($parcelShippedSuccess->sum('weight_create_bill') * -1, 2),
                    'total_lak' => number_format($parcelShippedSuccess->sum('total_lak'), 2),
                    'total_cny' => number_format($parcelShippedSuccess->sum('total_cny'), 2),
                    'price_per_weight' => number_format(($parcelShippedSuccess->sum('total_lak') / ($parcelShippedSuccess->sum('weight_create_bill') == 0 ? 1 : $parcelShippedSuccess->sum('weight_create_bill'))) * -1, 2),
                    'payment_total_lak' => number_format($paymentPaid->sum('amount_lak'), 2),
                    'payment_total_cny' => number_format($paymentPaid->sum('amount_cny'), 2),
                    'payment_cash' => number_format($paymentPaid->sum('amount_cash'), 2),
                    'payment_transfer' => number_format($paymentPaid->sum('amount_transfer'), 2),
                    'payment_alipay' => number_format($paymentPaid->sum('amount_alipay'), 2),
                    'payment_wechatpay' => number_format($paymentPaid->sum('amount_wechat_pay'), 2),
                ];

                $responseData['in_stock'] = [
                    'start_date' => Carbon::createFromFormat('Y-m-d H:i:s',  $request->start_at)->format('Y-m-d'),
                    'end_date' => Carbon::createFromFormat('Y-m-d H:i:s',  $request->end_at)->format('Y-m-d'),
                    'group' => '',
                    'description' => 'In Stock',
                    'import_actual' => null,
                    'qty_create_bill' => number_format($parcelImportQuery->sum('qty_create_bill') - $parcelShippedSuccess->sum('qty_create_bill')),
                    'weight_create_bill' => number_format($parcelImportQuery->sum('weight_create_bill') - $parcelShippedSuccess->sum('weight_create_bill'), 2),
                    'total_lak' => number_format(($parcelImportQuery->sum('total_lak') - $parcelShippedSuccess->sum('total_lak')) * -1, 2),
                    'total_cny' => null,
                    'price_per_weight' => null,
                    'payment_total_lak' => null,
                    'payment_total_cny' => null,
                    'payment_cash' => null,
                    'payment_transfer' => null,
                    'payment_alipay' => null,
                    'payment_wechatpay' => null,
                ];
            } else {
                $parcelShippedSuccess = Parcel::select(
                    DB::raw('COUNT(parcels.id) as qty_create_bill'),
                    DB::raw('SUM(parcels.price_bill) as total_lak'),
                    DB::raw('SUM(parcels.weight) as weight_create_bill'),
                    DB::raw('DATE(parcels.created_at) as date'),
                    // DB::raw('MAX(CASE WHEN bill_id IS NOT NULL THEN parcels.price_bill / currencies.amount_cny * currencies.amount_lak ELSE 0 END) as total_cny'),
                    DB::raw('MAX(CASE WHEN bill_id IS NOT NULL THEN currencies.amount_cny ELSE 0 END) as total_cny_origin'),
                    DB::raw('MAX(CASE WHEN bill_id IS NOT NULL THEN currencies.amount_lak ELSE 0 END) as total_lak_origin'),
                )
                    ->join('bills', 'parcels.bill_id', '=', 'bills.id')
                    ->leftJoin('currencies', DB::raw('DATE(currencies.created_at)'), '=', DB::raw('DATE(parcels.created_at)'))
                    ->whereNull('parcels.deleted_at')
                    ->where('parcels.status', 'success')
                    ->whereDate('parcels.created_at', Carbon::now())
                    ->groupBy(DB::raw('DATE(parcels.created_at)'))->get();

                foreach ($parcelShippedSuccess as $ShippedSuccess) {
                    $ShippedSuccess->total_cny = ceil(($ShippedSuccess->total_lak / ($ShippedSuccess->currencies_cny * $ShippedSuccess->currencies_lak)) * 100) / 100;
                }
                $paymentPaid = Payment::select(
                    DB::raw('SUM(CASE WHEN payments.method IS NOT NULL AND payments.method = "cash" OR payments.method = "transffer" THEN payments.amount_lak ELSE 0 END) as amount_lak'),
                    DB::raw('SUM(CASE WHEN payments.method IS NOT NULL AND payments.method = "alipay" OR payments.method = "wechat_pay" THEN payments.amount_cny ELSE 0 END) as amount_cny'),

                    DB::raw('SUM(CASE WHEN payments.method IS NOT NULL AND payments.method = "cash" THEN payments.amount_lak ELSE 0 END) as amount_cash'),
                    DB::raw('SUM(CASE WHEN payments.method IS NOT NULL AND payments.method = "transffer" THEN payments.amount_lak ELSE 0 END) as amount_transfer'),
                    DB::raw('SUM(CASE WHEN payments.method IS NOT NULL AND payments.method = "alipay" THEN payments.amount_cny ELSE 0 END) as amount_alipay'),
                    DB::raw('SUM(CASE WHEN payments.method IS NOT NULL AND payments.method = "wechat_pay" THEN payments.amount_cny ELSE 0 END) as amount_wechat_pay'),
                    DB::raw('payments.method as method'),
                )
                    ->leftJoin('bill_payment', 'bill_payment.payment_id', '=', 'payments.id')
                    ->leftJoin('bills', 'bill_payment.bill_id', '=', 'bills.id')
                    ->leftJoin('parcels', 'parcels.bill_id', '=', 'bills.id')
                    ->where('payments.status', 'paid')
                    ->whereNull('payments.deleted_at')
                    ->whereNull('parcels.deleted_at')
                    ->where('parcels.status', 'success')
                    ->whereDate('parcels.created_at', Carbon::now())
                    ->groupBy('method')->get();

                $responseData['parcel_shipped_success'] = [
                    'start_date' => Carbon::now()->format('Y-m-d'),
                    'end_date' => Carbon::now()->format('Y-m-d'),
                    'group' => 'Shipped (Success)',
                    'description' => 'Shipped (Success)',
                    'import_actual' => null,
                    'qty_create_bill' => number_format($parcelShippedSuccess->sum('qty_create_bill') * -1),
                    'weight_create_bill' => number_format($parcelShippedSuccess->sum('weight_create_bill') * -1, 2),
                    'total_lak' => number_format($parcelShippedSuccess->sum('total_lak'), 2),
                    'total_cny' => number_format($parcelShippedSuccess->sum('total_cny'), 2),
                    'price_per_weight' => number_format(($parcelShippedSuccess->sum('total_lak') / ($parcelShippedSuccess->sum('weight_create_bill') == 0 ? 1 : $parcelShippedSuccess->sum('weight_create_bill'))) * -1, 2),
                    'payment_total_lak' => number_format($paymentPaid->sum('amount_lak'), 2),
                    'payment_total_cny' => number_format($paymentPaid->sum('amount_cny'), 2),
                    'payment_cash' => number_format($paymentPaid->sum('amount_cash'), 2),
                    'payment_transfer' => number_format($paymentPaid->sum('amount_transfer'), 2),
                    'payment_alipay' => number_format($paymentPaid->sum('amount_alipay'), 2),
                    'payment_wechatpay' => number_format($paymentPaid->sum('amount_wechat_pay'), 2),
                ];

                $responseData['in_stock'] = [
                    'start_date' => Carbon::now()->format('Y-m-d'),
                    'end_date' => Carbon::now()->format('Y-m-d'),
                    'group' => '',
                    'description' => 'In Stock',
                    'import_actual' => null,
                    'qty_create_bill' => number_format($parcelImportQuery->sum('qty_create_bill') - $parcelShippedSuccess->sum('qty_create_bill')),
                    'weight_create_bill' => number_format($parcelImportQuery->sum('weight_create_bill') - $parcelShippedSuccess->sum('weight_create_bill'), 2),
                    'total_lak' => number_format(($parcelImportQuery->sum('total_lak') - $parcelShippedSuccess->sum('total_lak')) * -1, 2),
                    'total_cny' => null,
                    'price_per_weight' => null,
                    'payment_total_lak' => null,
                    'payment_total_cny' => null,
                    'payment_cash' => null,
                    'payment_transfer' => null,
                    'payment_alipay' => null,
                    'payment_wechatpay' => null,
                ];
            }

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
            if ($request->start_at && $request->end_at) {
                $parcelForbuyQuery = Parcel::select(
                    DB::raw('COUNT(parcels.id) as qty_actual'),
                    DB::raw('COUNT(parcels.id) as qty_create_bill'),
                    DB::raw('SUM(weight) as weight_create_bill'),
                    DB::raw('SUM(price) as total_lak'),
                    DB::raw('SUM(price) / SUM(weight) as price_per_weight'),
                    DB::raw('DATE(parcels.created_at) as date'),
                    DB::raw('MAX(CASE WHEN bill_id IS NOT NULL THEN currencies.amount_cny ELSE 0 END) as currencies_cny'),
                    DB::raw('MAX(CASE WHEN bill_id IS NOT NULL THEN currencies.amount_lak ELSE 0 END) as currencies_lak'),
                )
                    ->leftJoin('currencies', DB::raw('DATE(currencies.created_at)'), '=', DB::raw('DATE(parcels.created_at)'))
                    ->whereNull('parcels.deleted_at')
                    ->whereBetween('parcels.created_at', [$request->start_at, $request->end_at])
                    ->groupBy(DB::raw('DATE(parcels.created_at)'))->get();

                foreach ($parcelForbuyQuery as $ForbuyQuery) {
                    $ForbuyQuery->total_cny = ceil(($ForbuyQuery->total_lak / ($ForbuyQuery->currencies_cny * $ForbuyQuery->currencies_lak)) * 100) / 100;
                }
                $responseData['import_parcels_forbuy'] = [
                    'start_date' => Carbon::createFromFormat('Y-m-d H:i:s',  $request->start_at)->format('Y-m-d'),
                    'end_date' => Carbon::createFromFormat('Y-m-d H:i:s',  $request->end_at)->format('Y-m-d'),
                    'group' => 'Import Parcels - ForBuy',
                    'description' => "",
                    'import_actual' => number_format($parcelForbuyQuery->sum('qty_actual')),
                    'qty_create_bill' => number_format($parcelForbuyQuery->sum('qty_create_bill')),
                    'weight_create_bill' => number_format($parcelForbuyQuery->sum('weight_create_bill'), 2),
                    'total_lak' => number_format($parcelForbuyQuery->sum('total_lak'), 2),
                    'total_cny' => number_format($parcelForbuyQuery->sum('total_cny'), 2),
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
                    DB::raw('COUNT(parcels.id) as qty_create_bill'),
                    DB::raw('SUM(weight) as weight_create_bill'),
                    DB::raw('SUM(price) as total_lak'),
                    DB::raw('SUM(price) / SUM(weight) as price_per_weight'),
                    DB::raw('DATE(parcels.created_at) as date'),
                    DB::raw('MAX(CASE WHEN bill_id IS NOT NULL THEN currencies.amount_cny ELSE 0 END) as currencies_cny'),
                    DB::raw('MAX(CASE WHEN bill_id IS NOT NULL THEN currencies.amount_lak ELSE 0 END) as currencies_lak'),
                )
                    ->leftJoin('currencies', DB::raw('DATE(currencies.created_at)'), '=', DB::raw('DATE(parcels.created_at)'))
                    ->whereNull('parcels.deleted_at')
                    ->whereDate('parcels.created_at', Carbon::now())
                    ->groupBy(DB::raw('DATE(parcels.created_at)'))->get();

                foreach ($parcelForbuyQuery as $ForbuyQuery) {
                    $ForbuyQuery->total_cny = ceil(($ForbuyQuery->total_lak / ($ForbuyQuery->currencies_cny * $ForbuyQuery->currencies_lak)) * 100) / 100;
                }

                $responseData['import_parcels_forbuy'] = [
                    'start_date' => Carbon::now()->format('Y-m-d'),
                    'end_date' => Carbon::now()->format('Y-m-d'),
                    'group' => 'Import Parcels - ForBuy',
                    'description' => "",
                    'import_actual' => number_format($parcelForbuyQuery->sum('qty_actual')),
                    'qty_create_bill' => number_format($parcelForbuyQuery->sum('qty_create_bill')),
                    'weight_create_bill' => number_format($parcelForbuyQuery->sum('weight_create_bill'), 2),
                    'total_lak' => number_format($parcelForbuyQuery->sum('total_lak'), 2),
                    'total_cny' => number_format($parcelForbuyQuery->sum('total_cny'), 2),
                    'price_per_weight' => number_format($parcelForbuyQuery->avg('price_per_weight'), 2),
                    'payment_total_lak' => null,
                    'payment_total_cny' => null,
                    'payment_cash' => null,
                    'payment_transfer' => null,
                    'payment_alipay' => null,
                    'payment_wechatpay' => null,
                ];
            }

            if ($request->query('start_at') && $request->query('end_at')) {
                $responseData['income_other'] = IncomeExpense::where('type', 'income')
                    ->where('sub_type', 'other')
                    ->whereNull('deleted_at')
                    ->where('status', 'verify')
                    ->whereBetween('created_at', [$request->start_at, $request->end_at])
                    ->get();

                $responseData['expenses_other'] = IncomeExpense::where('type', 'expenses')
                    ->where('sub_type', 'other')
                    ->whereNull('deleted_at')
                    ->where('status', 'verify')
                    ->whereBetween('created_at', [$request->start_at, $request->end_at])
                    ->get();

                $responseData['income_top_up'] = IncomeExpense::where('type', 'income')
                    ->where('sub_type', 'top_up')
                    ->whereNull('deleted_at')
                    ->where('status', 'verify')
                    ->whereBetween('created_at', [$request->start_at, $request->end_at])
                    ->get();
            } else {
                $responseData['income_other'] = IncomeExpense::where('type', 'income')
                    ->where('sub_type', 'other')
                    ->whereNull('deleted_at')
                    ->where('status', 'verify')
                    ->whereDate('created_at', Carbon::now())
                    ->get();

                $responseData['expenses_other'] = IncomeExpense::where('type', 'expenses')
                    ->where('sub_type', 'other')
                    ->whereNull('deleted_at')
                    ->where('status', 'verify')
                    ->whereDate('created_at', Carbon::now())
                    ->get();

                $responseData['income_top_up'] = IncomeExpense::where('type', 'income')
                    ->where('sub_type', 'top_up')
                    ->whereNull('deleted_at')
                    ->where('status', 'verify')
                    ->whereDate('created_at', Carbon::now())
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
