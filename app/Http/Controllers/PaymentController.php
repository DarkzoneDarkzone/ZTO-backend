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
                DB::raw('MAX(payments.id) as id'),
                'payments.payment_no',
                DB::raw('MAX(payments.status) as status'),
                DB::raw('SUM(payments.amount_lak) as amount_lak'),
                DB::raw('SUM(payments.amount_cny) as amount_cny'),
                DB::raw('MAX(payments.created_at) as pay_created_at'),
                DB::raw('MAX(payments.updated_at) as pay_updated_at'),
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
                    $bill_payment->orderBy('pay_query.pay_' . $field, $direction);
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
        // $bill_dd = Bill::with([
        //     'Parcels' => function ($query) {
        //         $query->select('parcels.id as p_id');
        //     },
        // ])->get();
        // dd($bill_dd);
        $query1->where('payment_no', $payment_no)->first();
        $bill_payment = Bill::joinSub($query1, 'pay_query', function (JoinClause $join) {
            $join->join('bill_payment', 'bill_payment.payment_id', '=', 'pay_query.id');
            $join->where('bill_payment.deleted_at', null);
            $join->on('bill_payment.bill_id', '=', 'bills.id');
        })->select('bills.*', 'total_weight');

        $sub_q = Parcel::select('bill_id', DB::raw('SUM(weight) as total_weight'))->whereNotNull('bill_id')->groupBy('bill_id');
        $bill_payment->joinSub($sub_q, 'parcel_q', function (JoinClause $join) {
            $join->on('parcel_q.bill_id', '=', 'bills.id');
        });
        $bill_payment->with([
            'Parcels' => function ($pacel) {
                // $pacel->select('id', 'track_no');
            },
        ]);
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
            'payment_type.*.name' => 'string|nullable',
            'payment_type.*.amount_lak' => 'required|numeric',
            'payment_type.*.amount_cny' => 'required|numeric',
            'payment_type.*.currency' => 'required|string',
            'active' => 'required|boolean',
            'draft' => 'required|boolean'
        ]);
        if ($validator->fails()) {
            $errors_val = $this->ValidatorErrors($validator);
            return response()->json([
                'msg' => 'validator errors',
                'errors' => $errors_val,
                'status' => 'ERROR',
            ], 400);
        }

        DB::beginTransaction();
        try {
            $auth_id = Auth::user()->id;
            $bills = Bill::whereIn('bill_no', $request->bill)->where('status', 'shipped')->get();

            /////// check bills
            $bills_id = array();
            $bills_price_lak = 0;
            $bills_price_cny = 0;
            foreach ($bills as $bill) {
                array_push($bills_id, $bill->id);
                $bills_price_lak += $bill->amount_lak;
                $bills_price_cny += $bill->amount_cny;
            }
            /////// check payments
            $payments_save = array();
            $payments_price_lak = 0;
            $payments_price_cny = 0;
            $payment_methods_check = array();
            foreach ($request->payment_type as $key => $pay_type) {
                if (in_array($pay_type['name'], $payment_methods_check)) {
                } else {
                    array_push($payment_methods_check, $pay_type['name']);
                    $payment = new Payment();
                    $payment->created_by = $auth_id;
                    $payment->active = $request->active;
                    $payment->method = $pay_type['name'];
                    switch ($pay_type['currency']) {
                        case 'lak':
                            $payment->amount_lak = $pay_type['amount_lak'];
                            $payment->amount_cny = $pay_type['amount_cny'];
                            break;
                        case 'cny':
                            $payment->amount_lak = $pay_type['amount_lak'];
                            $payment->amount_cny = $pay_type['amount_cny'];
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
            }

            $randomNumber = rand(10, 99);

            // sleep(2);
            /////// create payment_no
            $currentDate = Carbon::now()->format('ym');
            $payCount = Payment::whereYear('created_at', Carbon::now()->year)
                ->whereMonth('created_at', Carbon::now()->month)
                ->max('payment_no');
            if (isset($payCount)) {
                $ex = explode('-', $payCount);
                $number = (int) $ex[1];
                $payment_no_defult = 'SK' . $currentDate . '-' . sprintf('%05d', $number + 1) . '-' . $randomNumber;
            } else {
                $payment_no_defult = 'SK' . $currentDate . '-' . sprintf('%05d', 00001) . '-' . $randomNumber;
            }

            // $payment_no_defult = Carbon::now()->getTimestampMs() . $randomNumber;

            $check_bills_payments = false;
            if ($pay_type['currency'] == 'lak') {
                $check_bills_payments = $payments_price_lak >= $bills_price_lak;
            } else if ($pay_type['currency'] == 'cny') {
                $check_bills_payments = $payments_price_cny >= $bills_price_cny;
            }
            if ($check_bills_payments && $request->draft == false) {
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
                    if ($request->draft == false) {
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
                        $payment->status = 'paid';
                    }
                    $payment->payment_no = $payment_no_defult;
                    $payment->save();
                    $payment->Bills()->sync($bills_id);
                }
            } else {

                foreach ($payments_save as $key => $payment) {
                    $payment->payment_no = $payment_no_defult;
                    $payment->status = 'pending';
                    $payment->save();
                    // DB::commit();
                    $payment->Bills()->sync($bills_id);
                }
            }
            DB::commit();
            return response()->json([
                'code' => 201,
                'status' => 'Created',
                'data' => $payment->payment_no
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
            'payment_type.*.name' => 'string|nullable',
            'payment_type.*.amount_lak' => 'required|numeric',
            'payment_type.*.amount_cny' => 'required|numeric',
            'payment_type.*.currency' => 'required|string',
            'active' => 'required|boolean',
            'draft' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            $errors_val = $this->ValidatorErrors($validator);
            return response()->json([
                'msg' => 'validator errors',
                'errors' => $errors_val,
                'status' => 'ERROR',
            ], 400);
        }

        DB::beginTransaction();
        try {
            $auth_id = Auth::user()->id;
            $payments = Payment::where('payment_no', $id)->whereNot('status', 'paid')->get();
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

            // $currency_now = Currency::orderBy('id', 'desc')->first();

            /////// check payments
            $payments_save = array();
            $payments_price_lak = 0;
            $payments_price_cny = 0;
            $payment_methods_new = array();
            foreach ($request->payment_type as $key => $pay_type) {
                array_push($payment_methods_new, $pay_type['name']);
                if (in_array($pay_type['name'], $payments_methods_old)) {
                    $index_pay = array_search($pay_type['name'], $payments_methods_old);
                    switch ($pay_type['currency']) {
                        case 'lak':
                            $payments[$index_pay]->amount_lak = $pay_type['amount_lak'];
                            $payments[$index_pay]->amount_cny = $pay_type['amount_cny'];
                            break;
                        case 'cny':
                            $payments[$index_pay]->amount_lak = $pay_type['amount_lak'];
                            $payments[$index_pay]->amount_cny = $pay_type['amount_cny'];
                            break;
                        default:
                            $payments[$index_pay]->amount_lak = 0;
                            $payments[$index_pay]->amount_cny = 0;
                            break;
                    }
                    $payments_price_lak += $payments[$index_pay]->amount_lak;
                    $payments_price_cny += $payments[$index_pay]->amount_cny;
                    array_push($payments_save, $payments[$index_pay]);
                } else {
                    $payment = new Payment();
                    $payment->created_by = $auth_id;
                    $payment->active = $request->active;
                    $payment->method = $pay_type['name'];
                    switch ($pay_type['currency']) {
                        case 'lak':
                            $payment->amount_lak = $pay_type['amount_lak'];
                            $payment->amount_cny = $pay_type['amount_cny'];
                            break;
                        case 'cny':
                            $payment->amount_lak = $pay_type['amount_lak'];
                            $payment->amount_cny = $pay_type['amount_cny'];
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

            $check_bills_payments = false;
            if ($pay_type['currency'] == 'lak') {
                $check_bills_payments = $payments_price_lak >= $bills_price_lak;
            } else if ($pay_type['currency'] == 'cny') {
                $check_bills_payments = $payments_price_cny >= $bills_price_cny;
            }
            if ($check_bills_payments && $request->draft == false) {
                // dd($request->draft);
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
                    if ($request->draft == false) {
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

                        $payment->status = 'paid';
                    }
                    $payment->payment_no = $id;
                    $payment->save();
                    $payment->Bills()->sync($bills_id);
                }
            } else {
                // foreach ($bills as $bill) {
                //     $bill->status = 'waiting_payment';
                //     $bill->save();
                // }
                foreach ($payments_save as $key => $payment) {
                    $payment->payment_no = $id;
                    $payment->status = 'pending';
                    $payment->save();
                    $payment->Bills()->sync($bills_id);
                }

                // return response()->json([
                //     'msg' => 'payments Not enough.',
                //     'status' => 'ERROR',
                //     'data' => array()
                // ], 400);
            }


            DB::commit();
            return response()->json([
                'code' => 200,
                'status' => 'OK',
                'data' => $payment->payment_no
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
    public function destroy($payment_no)
    {
        // $payment = Payment::find($id);
        try {
            $payments = Payment::where('payment_no', $payment_no)->whereNull('deleted_at')->whereNot('status', 'paid')->get();
            if (count($payments) == 0) {
                return response()->json([
                    'message' => 'payments not found',
                    'status' => 'ERROR',
                    'code' => 404,
                ], 400);
            }
            $bills_id = array();
            foreach ($payments as $key => $pay) {
                if ($key == 0) {
                    $bills = $pay->Bills;
                    foreach ($bills as $bill) {
                        array_push($bills_id, $bill->id);
                        $bill->status = 'shipped';
                        $bill->save();
                        foreach ($bill->Parcels as $parcel) {
                            $parcel->payment_at = null;
                            $parcel->status = 'ready';
                            $parcel->save();
                        }
                    }
                }
                $pay->Bills()->detach($bills_id);
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
