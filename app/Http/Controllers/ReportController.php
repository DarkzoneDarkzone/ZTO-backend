<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReportAccountingCollection;
use App\Models\Payment;
use App\Support\Collection;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function reportAccounting(Request $request)
    {
        try {
            $subQuery = Payment::select(
                'payments.id',
                'payments.payment_no',
                'payments.amount_lak',
                'payments.created_at',
                'payments.deleted_at',
                DB::raw('(CASE WHEN payments.method = "cash" THEN payments.amount_lak ELSE 0 END) as cash'),
                DB::raw('(CASE WHEN payments.method = "transffer" THEN payments.amount_lak ELSE 0 END) as transffer'),
                DB::raw('(CASE WHEN payments.method = "airpay" THEN payments.amount_lak ELSE 0 END) as airpay'),
                DB::raw('(CASE WHEN payments.method = "wechat_pay" THEN payments.amount_lak ELSE 0 END) as wechat_pay'),
            )
                ->where(['status' => 'paid', 'active' => 1])
                ->whereNull('deleted_at')
                ->whereHas('Bills', function ($query) {
                    $query->where('bills.status', 'shipped');
                });

            $lastQuery = Payment::select(
                DB::raw('ANY_VALUE(payments.id) as id'),
                'payments.payment_no',
                DB::raw('SUM(cash) as cash'),
                DB::raw('SUM(transffer) as transffer'),
                DB::raw('SUM(airpay) as airpay'),
                DB::raw('SUM(wechat_pay) as wechat_pay'),
                DB::raw('SUM(payments.amount_lak) as amount'),
                DB::raw('ANY_VALUE(payments.created_at) as created_at'),
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
                'msg' => $e,
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
