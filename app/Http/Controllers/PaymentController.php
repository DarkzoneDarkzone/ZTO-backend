<?php

namespace App\Http\Controllers;

use App\Models\Balance;
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
                    $ex = explode(':', $filter);
                    $query->where($Operator->FiltersOperators([$ex[0], $ex[1], $ex[2]]));
                }
            }
            $query = $query->select(
                DB::raw('ANY_VALUE(payments.id) as id'),
                'payments.payment_no',
                DB::raw('ANY_VALUE(payments.status) as status'),
                DB::raw('SUM(payments.amount_lak) as amount_lak'),
                DB::raw('SUM(payments.amount_cny) as amount_cny'),
                DB::raw('DATE_ADD(ANY_VALUE(payments.created_at), INTERVAL 7 HOUR) as pay_created_at'),
                DB::raw('DATE_ADD(ANY_VALUE(payments.updated_at), INTERVAL 7 HOUR) as pay_updated_at'),
            );
            $query->groupBy('payment_no');

            $bill_payment = Bill::joinSub($query, 'pay_query', function (JoinClause $join) {
                $join->join('bill_payment', 'bill_payment.payment_id', '=', 'pay_query.id');
                $join->where('bill_payment.deleted_at', null);
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
                    $bill_payment->orderBy('pay_query.pay_'.$field, $direction);
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
        $query1 = Payment::query();
        $query1->where('payment_no', $payment_no)->first();
        $bill_payment = Bill::joinSub($query1, 'pay_query', function (JoinClause $join) {
            $join->join('bill_payment', 'bill_payment.payment_id', '=', 'pay_query.id');
            $join->where('bill_payment.deleted_at', null);
            $join->on('bill_payment.bill_id', '=', 'bills.id');
        });

        $sub_q = Parcel::select('bill_id', DB::raw('SUM(weight) as total_weight'))->whereNotNull('bill_id')->groupBy('bill_id');
        $bill_payment->joinSub($sub_q, 'parcel_q', function (JoinClause $join) {
            $join->on('parcel_q.bill_id', '=', 'bills.id');
        });

        // $bill_payment->select('bills.*', 'bill_payment.*', );
        $bill_payment = $bill_payment->get();

        $payment = Payment::where('payment_no', $payment_no)->get();

        $payment_no_json = (object) array('payment_no' => $payment_no, 'bill_payment' => $bill_payment, 'payments' => $payment);
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

            $currency_now = Currency::orderBy('id', 'desc')->first();

            /////// check payments
            $payments_save = array();
            $payments_price_lak = 0;
            $payments_price_cny = 0;
            foreach ($request->payment_type as $key => $pay_type) {
                $payment = new Payment();
                $payment->created_by = $auth_id;
                $payment->active = $request->active;
                $payment->method = $pay_type['name'];
                switch ($pay_type['currency']) {
                    case 'lak':
                        $payment->amount_lak = $pay_type['amount'];
                        $payment->amount_cny = $pay_type['amount'] / ($currency_now->amount_cny * $currency_now->amount_lak);
                        break;
                    case 'cny':
                        $payment->amount_lak = $pay_type['amount'] * ($currency_now->amount_lak / $currency_now->amount_cny);
                        $payment->amount_cny = $pay_type['amount'];
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

            /////// check bills

            $bills_id = array();
            $bills_price_lak = 0;
            $bills_price_cny = 0;
            foreach ($bills as $bill) {
                array_push($bills_id, $bill->id);
                $bills_price_lak += $bill->amount_lak;
                $bills_price_cny += $bill->amount_cny;
            }

            /////// create payment_no
            $currentDate = Carbon::now()->format('ym');
            $payCount = Payment::whereYear('created_at', Carbon::now()->year)
                ->whereMonth('created_at', Carbon::now()->month)
                ->orderBy('id', 'desc')->first();
            if (!$payCount) {
                $payment_no_defult = 'SK' . $currentDate . '-' . sprintf('%05d', 00001);
            } else {
                $ex = explode('-', $payCount->payment_no);
                $number = (int) $ex[1];
                $payment_no_defult = 'SK' . $currentDate . '-' . sprintf('%05d', $number + 1);
            }
            // round($payments_price_cny, 2) >= round($bills_price_cny, 2)

            $paymenst_ceil =  (ceil($payments_price_cny * 100) / 100);
            $bill_ceil = (ceil($bills_price_cny * 100) / 100);
            ////// check payment >= bills = success
            if ($paymenst_ceil >= $bill_ceil) {
                foreach ($bills as $bill) {
                    $bill->status = 'success';
                    $bill->save();
                    foreach ($bill->Parcels as $parcel) {
                        $parcel->payment_at = Carbon::now();
                        $parcel->status = 'success';
                        $parcel->save();
                    }
                }

                foreach ($payments_save as $key => $payment) {
                    $payment->payment_no = $payment_no_defult;
                    $payment->status = 'paid';
                    $payment->save();
                    $payment->Bills()->sync($bills_id);

                    $balanceStack = Balance::orderBy('id', 'desc')->first();
                    $balance = new Balance();
                    $balance->amount_lak = $payment->amount_lak;
                    $balance->amount_cny = $payment->amount_cny;
                    if ($balanceStack) {
                        $balance->balance_amount_lak = $balanceStack->balance_amount_lak + $payment->amount_lak;
                        $balance->balance_amount_cny = $balanceStack->balance_amount_cny + $payment->amount_cny;
                    } else {
                        $balance->balance_amount_lak = $payment->amount_lak;
                        $balance->balance_amount_cny = $payment->amount_cny;
                    }
                    $balance->payment_id = $payment->id;
                    $balance->save();
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
    public function updatePaymentNo(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
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
                'msg' => ' went wrong.',
                'errors' => $validator->errors()->toJson(),
                'status' => 'Unauthorized',
            ], 400);
        }

        DB::beginTransaction();
        try {
            $auth_id = Auth::user()->id;
            $payments = Payment::where('payment_no', $id)->get();
            $bills = Bill::whereIn('bill_no', $request->bill)->get();
            if (count($payments) == 0) {
                return response()->json([
                    'msg' => 'payment_no not found.',
                    'status' => 'ERROR',
                    'errors' => array()
                ], 400);
            }

            if (count($bills) == 0) {
                return response()->json([
                    'msg' => 'bill_no not found.',
                    'status' => 'ERROR',
                    'errors' => array()
                ], 400);
            }

            //// reset bills_old
            $bills_old_id = array();
            $bills_old = $payments[0]->Bills;
            foreach ($bills_old as $key => $bill_old) {
                array_push($bills_old_id, $bill_old->id);
            }
            $payments_methods_old = array();
            foreach ($payments as $key => $pay) {
                array_push($payments_methods_old, $pay->method);
                $pay->Bills()->detach($bills_old_id);
            }

            $currency_now = Currency::orderBy('id', 'desc')->first();

            /////// check payments
            $payments_save = array();
            $payments_price_lak = 0;
            $payments_price_cny = 0;
            $payment_methods_new = array();
            foreach ($request->payment_type as $key => $pay_type) {
                array_push($payment_methods_new, $pay_type['name']);
                if (!in_array($pay_type['name'], $payments_methods_old)) {
                    $payment = new Payment();
                    $payment->created_by = $auth_id;
                    $payment->active = $request->active;
                    $payment->method = $pay_type['name'];
                    switch ($pay_type['currency']) {
                        case 'lak':
                            $payment->amount_lak = $pay_type['amount'];
                            $payment->amount_cny = $pay_type['amount'] / ($currency_now->amount_cny * $currency_now->amount_lak);
                            break;
                        case 'cny':
                            $payment->amount_lak = $pay_type['amount'] * ($currency_now->amount_lak / $currency_now->amount_cny);
                            $payment->amount_cny = $pay_type['amount'];
                            break;
                        default:
                            $payment->amount_lak = 0;
                            $payment->amount_cny = 0;
                            break;
                    }
                    $payments_price_lak += $payment->amount_lak;
                    $payments_price_cny += $payment->amount_cny;
                    array_push($payments_save, $payment);
                } else {
                    $index_pay = array_search($pay_type['name'], $payments_methods_old);
                    switch ($pay_type['currency']) {
                        case 'lak':
                            $payments[$index_pay]->amount_lak = $pay_type['amount'];
                            $payments[$index_pay]->amount_cny = $pay_type['amount'] / ($currency_now->amount_cny * $currency_now->amount_lak);
                            break;
                        case 'cny':
                            $payments[$index_pay]->amount_lak = $pay_type['amount'] * ($currency_now->amount_lak / $currency_now->amount_cny);
                            $payments[$index_pay]->amount_cny = $pay_type['amount'];
                            break;
                        default:
                            $payments[$index_pay]->amount_lak = 0;
                            $payments[$index_pay]->amount_cny = 0;
                            break;
                    }
                    $payments_price_lak += $payments[$index_pay]->amount_lak;
                    $payments_price_cny += $payments[$index_pay]->amount_cny;
                    array_push($payments_save, $payments[$index_pay]);
                }
            }

            /////// check diff methods payment
            $result_diff = array_diff($payments_methods_old, $payment_methods_new);
            if (count($result_diff) > 0) {
                foreach ($result_diff as $key => $method) {
                    $payments[$key]->delete();
                }
            }

            /////// check bills
            $bills_id = array();
            $bills_price_lak = 0;
            $bills_price_cny = 0;
            //// bills new
            foreach ($bills as $bill) {
                array_push($bills_id, $bill->id);
                $bills_price_lak += $bill->amount_lak;
                $bills_price_cny += $bill->amount_cny;
            }

            /////// check diff bill
            $bills_diff = array_diff($bills_old_id, $bills_id);
            if (count($bills_diff) > 0) {
                foreach ($bills_diff as $key => $method) {
                    $bills_old[$key]->status = 'shipped';
                    $bills_old[$key]->save();
                }
            }


            // round($payments_price_cny, 2) >= round($bills_price_cny, 2)
            $paymenst_ceil =  (ceil($payments_price_cny * 100) / 100);
            $bill_ceil = (ceil($bills_price_cny * 100) / 100);
            ////// check payment >= bills = success
            if ($paymenst_ceil >= $bill_ceil) {
                foreach ($bills as $bill) {
                    $bill->status = 'success';
                    $bill->save();
                    foreach ($bill->Parcels as $parcel) {
                        $parcel->payment_at = Carbon::now();
                        $parcel->status = 'success';
                        $parcel->save();
                    }
                }
                foreach ($payments_save as $key => $payment) {
                    $payment->payment_no = $id;
                    $payment->status = 'paid';
                    $payment->save();
                    $payment->Bills()->sync($bills_id);

                    $balanceStack = Balance::orderBy('id', 'desc')->first();
                    $balance = new Balance();
                    $balance->amount_lak = $payments_price_lak;
                    $balance->amount_cny = $payments_price_cny;
                    if ($balanceStack) {
                        $balance->balance_amount_lak = $balanceStack->balance_amount_lak + $payments_price_lak;
                        $balance->balance_amount_cny = $balanceStack->balance_amount_cny + $payments_price_cny;
                    } else {
                        $balance->balance_amount_lak = $payments_price_lak;
                        $balance->balance_amount_cny = $payments_price_cny;
                    }
                    $balance->payment_id = $payment->id;
                    $balance->save();
                }
            } else {
                foreach ($bills as $bill) {
                    $bill->status = 'waiting_payment';
                    $bill->save();
                }
                foreach ($payments_save as $key => $payment) {
                    $payment->payment_no = $id;
                    $payment->status = 'pending';
                    $payment->save();
                    $payment->Bills()->sync($bills_id);
                }
            }


            DB::commit();
            return response()->json([
                'code' => 200,
                'status' => 'OK',
                'data' => array()
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'msg' => 'Something went wrong.',
                'errors' => array(),
                'status' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        // $payment = Payment::find($id);
        try {
            $payments = Payment::where('payment_no', $id)->get();
            if (!$payments) {
                return response()->json([
                    'message' => 'payment not found',
                    'status' => 'ERROR',
                    'code' => 404,
                ], 400);
            }
            foreach ($payments as $key => $pay) {
                $pay->delete();
            }

            return response()->json([
                'status' => 'OK',
                'code' => '200',
                'data' => null
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'msg' => 'Something went wrong.',
                'errors' => array(),
                'status' => $e->getMessage(),
            ], 500);
        }
    }
}
