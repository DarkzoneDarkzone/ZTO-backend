<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Payment;
use App\Models\Currency;
use App\Models\Parcel;
use App\Rules\DynamicArray;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Database\Query\JoinClause;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = Payment::query();
            if ($request->has('filters')) {
                $Operator = new FiltersOperator();
                $arrayFilter = explode(',', $request->query('filters', []));
                foreach ($arrayFilter as $filter) {
                    $query->Where($Operator->FiltersOperators(explode(':', $filter)));
                }
            }

            $bill_payment = Bill::joinSub($query, 'pay_query', function (JoinClause $join) {
                $join->join('bill_payment', 'bill_payment.payment_id', '=', 'pay_query.id');
                $join->on('bill_payment.bill_id', '=', 'bills.id');
            });

            if ($request->has('searchText')) {
                $arraySearchText = ['bills.bill_no', 'bills.name', 'bills.phone', 'pay_query.payment_no'];
                foreach ($arraySearchText as $key => $value) {
                    $bill_payment->orWhereLike($value,  '%' . $request->query('searchText') . '%');
                }
            }

            if ($request->has('sorts')) {
                $arraySorts = explode(',', $request->query('sorts', []));
                foreach ($arraySorts as $sort) {
                    [$field, $direction] = explode(':', $sort);
                    $bill_payment->orderBy($field, $direction);
                }
            }

            if ($request->has('per_page')) {
                $payment = $bill_payment->paginate($request->query('per_page'));
            } else {
                $payment = $bill_payment->get();
            }

            return response()->json([
                'code' => 200,
                'status' => 'OK',
                'data' => $payment,
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

    /**
     * get by id
     */
    public function getById($id)
    {
        $payment = Payment::find($id);
        $payment->Bills;
        if (!$payment) {
            return response()->json([
                'msg' => 'payment not found.',
                'status' => 'ERROR',
                'data' => array()
            ], 400);
        }
        return response()->json([
            'code' => 200,
            'status' => 'OK',
            'data' => $payment
        ], 200);
    }

    /**
     * get by payment_no
     */
    public function getByPaymentNo($payment_no)
    {
        $query = Payment::query();
        // $payments = Payment::where('payment_no', $payment_no);
        $query->where('payment_no', $payment_no);
        // $payment->Bills; 
        // dd($payment_no);
        $bill_payment = Bill::joinSub($query, 'pay_query', function (JoinClause $join) {
            $join->join('bill_payment', 'bill_payment.payment_id', '=', 'pay_query.id');
            $join->on('bill_payment.bill_id', '=', 'bills.id');
        });
        // $bill_payment->select('')
        $bill_payment = $bill_payment->get();
        $payment = $query->get();

        $payment_no_json = (object) array('payment_no' => $payment_no, 'bill_payment' => $bill_payment, 'paymests' => $payment);
        if (!$bill_payment) {
            return response()->json([
                'msg' => 'payment not found.',
                'status' => 'ERROR',
                'data' => array()
            ], 400);
        }
        return response()->json([
            'code' => 200,
            'status' => 'OK',
            'data' => $payment_no_json
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'phone' => 'required|string',
            // 'name' => 'required|string',
            'bill' => 'required|array',
            'bill.*' => 'string',
            'payment_type' => 'required|array',
            'payment_type.*.name' => 'required|string',
            'payment_type.*.amount' => 'required|numeric',
            'payment_type.*.currency' => 'required|string',
            'active' => 'required|boolean',

        ]);
        if ($validator->fails()) {
            return response()->json([
                'msg' => 'validator wrong.',
                'errors' => $validator->errors()->toJson(),
                'status' => 'Unauthorized',
            ], 400);
        }
        DB::beginTransaction();
        try {
            $auth_id = Auth::user()->id;
            $bills = Bill::whereIn('bill_no', $request->bill)->get();
            foreach ($bills as $key => $bill) {
                if ($bill->status != 'shipped') {
                    return response()->json([
                        'msg' => 'some bill not shipped.',
                        'status' => 'ERROR',
                        'data' => array()
                    ], 400);
                }
            }

            $bills_id = array();
            $bills_price_lak = 0;
            $bills_price_cny = 0;
            foreach ($bills as $bill) {
                array_push($bills_id, $bill->id);
                $bills_price_lak += $bill->amount_lak;
                $bills_price_cny += $bill->amount_cny;
            }

            $currency_now = Currency::orderBy('id', 'desc')->first();
            $payments_save = array();
            $payments_price_lak = 0;
            $payments_price_cny = 0;
            foreach ($request->payment_type as $key => $pay) {
                $payment = new Payment();
                $payment->created_by = $auth_id;
                $payment->active = $request->active;
                $payment->method = $pay['name'];
                switch ($pay['currency']) {
                    case 'lak':
                        $payment->amount_lak = $pay['amount'];
                        $payment->amount_cny = $pay['amount'] / ($currency_now->amount_cny * $currency_now->amount_lak);
                        break;
                    case 'cny':
                        $payment->amount_lak = $pay['amount'] * ($currency_now->amount_lak / $currency_now->amount_cny);
                        $payment->amount_cny = $pay['amount'];
                        break;
                    default:
                        $payment->amount_lak = 0;
                        $payment->amount_cny = 0;
                        break;
                }
                $payments_price_lak += $payment->amount_lak;
                $payments_price_cny += $payment->amount_cny;
                array_push($payments_save, $payment);
            }

            $currentDate = Carbon::now()->format('ym');
            $payCount = Bill::whereYear('created_at', Carbon::now()->year)
                ->whereMonth('created_at', Carbon::now()->month)
                ->count() + 1;
            $payment_no_defult = 'SK' . $currentDate . '-' . sprintf('%04d', $payCount);
            // dd($payments_price_lak, $bills_price_lak, round($payments_price_cny, 6),  round($bills_price_cny, 6));
            if ($payments_price_lak >= $bills_price_lak &&  round($payments_price_cny, 6) >= round($bills_price_cny, 6)) {
                foreach ($bills as $bill) {
                    $bill->status = 'success';
                    $bill->save();
                }
                $parcels = Parcel::where(['phone' => $request->phone, 'status' => 'ready'])->get();
                foreach ($parcels as $parcel) {
                    $parcel->status = 'success';
                    $parcel->save();
                }

                foreach ($payments_save as $key => $payment) {
                    $payment->payment_no = $payment_no_defult;
                    $payment->status = 'paid';
                    $payment->save();
                    $payment->Bills()->sync($bills_id);
                }
                
            } else {
                foreach ($bills as $bill) {
                    $bill->status = 'waiting_payment';
                    $bill->save();
                }
                foreach ($payments_save as $key => $payment) {
                    $payment->payment_no = $payment_no_defult;
                    $payment->status = 'pending';
                    $payment->save();
                    $payment->Bills()->sync($bills_id);
                }
            }
            DB::commit();
            return response()->json([
                'code' => 201,
                'status' => 'Created',
                'data' => array()
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'msg' => 'Something wrong.',
                'errors' => $e->getMessage(),
                'status' => 'ERROR',
            ], 500);
        }
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
    public function show(Payment $payment)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Payment $payment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    // public function update(Request $request, $id)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'active' => 'required|boolean',
    //         'payment_type' => 'required|array',
    //         'payment_type.*.name',
    //         'payment_type.*.amount',
    //         'payment_type.*.currency',
    //         'bill' => 'required|array',
    //         'bill.*' => 'string',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'msg' => ' went wrong.',
    //             'errors' => $validator->errors()->toJson(),
    //             'status' => 'Unauthorized',
    //         ], 400);
    //     }
    //     DB::beginTransaction();
    //     try {
    //         $auth_id = Auth::user()->id;
    //         $payment = Payment::where('id', $id)->first();
    //         if (!$payment) {
    //             return response()->json([
    //                 'msg' => 'payment not found.',
    //                 'status' => 'ERROR',
    //                 'errors' => array()
    //             ], 400);
    //         }
    //         $payment->method = $request->payment_type->name;
    //         $currency_now = Currency::orderBy('id', 'asc')->first();
    //         switch ($request->payment_type->currency) {
    //             case 'lak':
    //                 $payment->amount_lak = $request->payment_type->amount;
    //                 $payment->amount_cny = $request->payment_type->amount / ($currency_now->amount_cny * $currency_now->amount_lak);
    //                 break;
    //             case 'cny':
    //                 $payment->amount_lak = $request->payment_type->amount / ($currency_now->amount_lak * $currency_now->amount_cny);
    //                 $payment->amount_cny = $request->payment_type->amount;
    //                 break;
    //             default:
    //                 $payment->amount_lak = 0;
    //                 $payment->amount_cny = 0;
    //                 break;
    //         }
    //         $bills = Bill::whereIn('bill_no', $payment->bill)->get();
    //         $bills_id = array();
    //         foreach ($bills as $bill) {
    //             array_push($bills_id, $bill->id);
    //         }
    //         $payment->Bills()->sync($bills_id);
    //         $payment->status = 'pending';
    //         $payment->created_by = $auth_id;

    //         $currentDate = Carbon::now()->format('ym');
    //         $payCount = Bill::whereYear('created_at', Carbon::now()->year)
    //             ->whereMonth('created_at', Carbon::now()->month)
    //             ->count() + 1;
    //         $bill->bill_no = 'SK' . $currentDate . '-' . sprintf('%04d', $payCount);

    //         $payment->save();
    //         DB::commit();
    //         return response()->json([
    //             'code' => 200,
    //             'status' => 'OK',
    //             'data' => $payment
    //         ], 200);
    //     } catch (Exception $e) {
    //         DB::rollBack();
    //         return response()->json([
    //             'msg' => 'Something went wrong.',
    //             'errors' => array(),
    //             'status' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $payment = Payment::find($id);
        if (!$payment) {
            return response()->json([
                'message' => 'payment not found',
                'status' => 'ERROR',
                'code' => 404,
            ], 400);
        }

        $payment->delete();

        return response()->json([
            'status' => 'OK',
            'code' => '200',
            'data' => null
        ], 200);
    }
}
