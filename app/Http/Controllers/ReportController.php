<?php

namespace App\Http\Controllers;

use App\Exports\ReportAccountingExport;
use App\Exports\ReportIncomeExpensesExport;
use App\Exports\ReportReturnParcelExport;
use App\Http\Resources\ReportAccountingCollection;
use App\Http\Resources\ReportIncomeExpensesCollection;
use App\Http\Resources\ReportReturnParcelCollection;
use App\Models\Balance;
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
                DB::raw('(CASE WHEN payments.method = "alipay" OR payments.method = "wechat_pay" THEN payments.amount_cny ELSE payments.amount_lak END) as amount_lak'),
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
                DB::raw('(CASE WHEN payments.method = "alipay" OR payments.method = "wechat_pay" THEN payments.amount_cny ELSE payments.amount_lak END) as amount_lak'),
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
                $returnParcel->where('created_at', '>=', $request->query('start_at'));
            }

            if ($request->has('end_at')) {
                $returnParcel->where('created_at', '<=', $request->query('end_at'));
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

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
