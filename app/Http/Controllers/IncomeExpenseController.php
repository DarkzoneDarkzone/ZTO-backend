<?php

namespace App\Http\Controllers;

use App\Models\Balance;
use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\IncomeExpense;
use App\Models\Parcel;
use App\Models\Payment;
use App\Models\ReturnParcel;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class IncomeExpenseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = IncomeExpense::query();

            if ($request->has('filters')) {
                $Operator = new FiltersOperator();
                $arrayFilter = explode(',', $request->query('filters', []));
                foreach ($arrayFilter as $filter) {
                    $query->where($Operator->FiltersOperators(explode(':', $filter)));
                }
            }

            if ($request->has('searchText')) {
                $arraySearchText = ['id', 'type', 'description'];
                $query->whereAny($arraySearchText, 'like', '%' . $request->query('searchText') . '%');
            }

            if ($request->has('sorts')) {
                $arraySorts = explode(',', $request->query('sorts', []));
                foreach ($arraySorts as $sort) {
                    [$field, $direction] = explode(':', $sort);
                    $query->orderBy($field, $direction);
                }
            }

            if ($request->has('per_page')) {
                $payment = $query->paginate($request->query('per_page'));
            } else {
                $payment = $query->get();
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
        $incomeExpense = IncomeExpense::where('id', $id)->first();
        $query_return_parcel = ReturnParcel::query();
        $query_return_parcel->join('parcels', 'parcels.id', '=', 'return_parcels.parcel_id')
            ->where('return_parcels.income_expenses_id', $incomeExpense->id);
        /////////////
        if ($incomeExpense->type == 'expenses') {
            $query_return_parcel->select(
                'parcels.*',
                'return_parcels.weight',
                'return_parcels.refund_amount_lak',
                'return_parcels.refund_amount_cny'
            );
        } else if ($incomeExpense->type == 'income') {
            $query_return_parcel->select(
                'parcels.*',
                'return_parcels.car_number',
                'return_parcels.driver_name',
                'return_parcels.created_at as date_return'
            );
        }
        ////////////
        $return_parcel = $query_return_parcel->get();
        $incomeExpense->parcel = $return_parcel;
        if (!$incomeExpense) {
            return response()->json([
                'msg' => 'income expense not found.',
                'status' => 'ERROR',
                'data' => array()
            ], 400);
        }
        return response()->json([
            'code' => 200,
            'status' => 'OK',
            'data' => $incomeExpense
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function createIncome(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'phone' => 'required|string',
            'item' => 'array',
            'item.*' => 'string',
            'date_return' => 'date_format:Y-m-d H:i:s',
            'delivery_car_no' => 'string',
            'delivery_person' => 'string',
            'sub_type' => 'required|string',
            // 'pay_type' => 'required|string',
            'pay_cash' => 'numeric|nullable',
            'pay_transfer' => 'numeric|nullable',
            'pay_alipay' => 'numeric|nullable',
            'pay_wechat' => 'numeric|nullable',
            'description' => 'string|nullable',
            'amount_return' => 'required|numeric',
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
            $currency_now = Currency::orderBy('id', 'desc')->first();

            $costs_lak = $request->amount_return;
            $costs_cny = $request->amount_return / ($currency_now->amount_cny * $currency_now->amount_lak);
            $costs_cny = round($costs_cny * 100) / 100;

            $incomeExpense = new IncomeExpense();
            $incomeExpense->type = 'income';
            $incomeExpense->sub_type = $request->sub_type;
            // $incomeExpense->pay_type = $request->pay_type;
            $incomeExpense->pay_cash = isset($request->pay_cash) ? $request->pay_cash : null;
            $incomeExpense->pay_transfer = isset($request->pay_transfer) ? $request->pay_transfer : null;
            $incomeExpense->pay_alipay = isset($request->pay_alipay) ? $request->pay_alipay : null;
            $incomeExpense->pay_wechat = isset($request->pay_wechat) ? $request->pay_wechat : null;

            $incomeExpense->status = 'pending';
            // isset($request->description) ? ($incomeExpense->description =  $request->description) : ($incomeExpense->description = '');
            $incomeExpense->description = isset($request->description) ?  $request->description : $request->sub_type;
            $incomeExpense->amount_lak = $costs_lak;
            $incomeExpense->amount_cny = $costs_cny;
            $incomeExpense->save();

            if ($request->sub_type == 'return') {
                $check_return = Parcel::whereIn('track_no', $request->item)->select('id')->get();
                if (count($check_return) == 0) {
                    return response()->json([
                        'msg' => 'parcel not found.',
                        'status' => 'ERROR',
                        'errors' => array()
                    ], 400);
                }
                foreach ($check_return as $parcel) {
                    $return_parcel = new ReturnParcel();
                    $return_parcel->created_by = $auth_id;
                    $return_parcel->parcel_id = $parcel->id;
                    $return_parcel->income_expenses_id = $incomeExpense->id;
                    $return_parcel->car_number = $request->delivery_car_no;
                    $return_parcel->driver_name = $request->delivery_person;
                    $return_parcel->save();
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


    public function createExpense(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item' => 'numeric',
            'weight' => 'numeric',
            'amount_refund' => 'required|numeric',
            'sub_type' => 'required|string',
            // 'pay_type' => 'required|string',
            'pay_cash' => 'numeric|nullable',
            'pay_transfer' => 'numeric|nullable',
            'pay_alipay' => 'numeric|nullable',
            'pay_wechat' => 'numeric|nullable',
            'description' => 'string|nullable',
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
            $currency_now = Currency::orderBy('id', 'desc')->first();

            $refund_lak = $request->amount_refund;
            $refund_cny = $request->amount_refund / ($currency_now->amount_cny * $currency_now->amount_lak);
            $refund_cny = round($refund_cny * 100) / 100;

            $incomeExpense = new IncomeExpense();
            $incomeExpense->type = 'expenses';
            $incomeExpense->sub_type = $request->sub_type;
            // $incomeExpense->pay_type = $request->pay_type;
            $incomeExpense->pay_cash = isset($request->pay_cash) ? $request->pay_cash : null;
            $incomeExpense->pay_transfer = isset($request->pay_transfer) ? $request->pay_transfer : null;
            $incomeExpense->pay_alipay = isset($request->pay_alipay) ? $request->pay_alipay : null;
            $incomeExpense->pay_wechat = isset($request->pay_wechat) ? $request->pay_wechat : null;

            $incomeExpense->status = 'pending';
            // isset($request->description) ? ($incomeExpense->description =  $request->description) : ($incomeExpense->description = '');
            $incomeExpense->description = isset($request->description) ?  $request->description : $request->sub_type;
            $incomeExpense->amount_lak = $refund_lak;
            $incomeExpense->amount_cny = $refund_cny;
            $incomeExpense->save();

            if ($request->sub_type == 'refund') {
                $parcel = Parcel::where(['track_no' => $request->item])->first();
                if (!$parcel) {
                    return response()->json([
                        'msg' => 'parcel not found.',
                        'status' => 'ERROR',
                        'errors' => array()
                    ], 400);
                }
                if (!$parcel->status == 'success') {
                    return response()->json([
                        'msg' => 'parcel unsuccess.',
                        'status' => 'ERROR',
                        'errors' => array()
                    ], 400);
                }

                $check_payment = Bill::join('bill_payment', 'bill_payment.bill_id', '=', 'bills.id')
                    ->join('payments', 'payments.id', '=', 'bill_payment.payment_id')
                    ->join('parcels', 'parcels.bill_id', '=', 'bills.id')
                    ->where(['parcels.track_no' => $request->item, 'payments.status' => 'paid'])
                    ->select('bills.bill_no', 'bills.status', 'payments.payment_no', 'payments.status', 'parcels.track_no', 'parcels.status')
                    ->first();
                if (!$check_payment) {
                    return response()->json([
                        'msg' => 'payment unpaid.',
                        'status' => 'ERROR',
                        'errors' => array()
                    ], 400);
                }

                $return_parcel = new ReturnParcel();
                $return_parcel->created_by = $auth_id;
                $return_parcel->parcel_id = $parcel->id;
                $return_parcel->income_expenses_id = $incomeExpense->id;
                $return_parcel->weight = $request->weight;
                $return_parcel->refund_amount_lak = $refund_lak;
                $return_parcel->refund_amount_cny = $refund_cny;
                $return_parcel->save();
            }

            DB::commit();
            return response()->json([
                'code' => 200,
                'status' => 'Created',
                'data' => array()
            ], 200);
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
    public function show(IncomeExpense $incomeExpense)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(IncomeExpense $incomeExpense)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     * 
     */


    public function updateIncome(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            // 'phone' => 'string',
            'item' => 'array',
            'item.*' => 'string',
            'date_return' => 'date_format:Y-m-d H:i:s',
            'delivery_car_no' => 'string',
            'delivery_person' => 'string',
            'sub_type' => 'required|string',
            // 'pay_type' => 'required|string',
            'pay_cash' => 'numeric|nullable',
            'pay_transfer' => 'numeric|nullable',
            'pay_alipay' => 'numeric|nullable',
            'pay_wechat' => 'numeric|nullable',
            'description' => 'string|nullable',
            'amount_return' => 'required|numeric',
            'status' => 'required|string'
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
            $incomeExpense = IncomeExpense::where('id', $id)->where('status', 'pending')->first();
            if (!$incomeExpense) {
                return response()->json([
                    'message' => 'income not found or income is verify',
                    'status' => 'ERROR',
                    'code' => 400,
                ], 400);
            }
            if ($request->sub_type == 'other' && $incomeExpense->sub_type == 'return') {
                $return_parcels = ReturnParcel::where('income_expenses_id', $incomeExpense->id)->get();
                foreach ($return_parcels as $key => $return) {
                    $return->delete();
                }
            }

            if ($incomeExpense->amount_lak != $request->amount_return) {
                $currency_now = Currency::orderBy('id', 'desc')->first();
                $incomeExpense->amount_lak = $request->amount_return;;
                $amount_cny = $request->amount_return / ($currency_now->amount_cny * $currency_now->amount_lak);
                $incomeExpense->amount_cny = round($amount_cny * 100) / 100;
            }
            $incomeExpense->sub_type = $request->sub_type;
            // $incomeExpense->pay_type = $request->pay_type;
            $incomeExpense->pay_cash = isset($request->pay_cash) ? $request->pay_cash : null;
            $incomeExpense->pay_transfer = isset($request->pay_transfer) ? $request->pay_transfer : null;
            $incomeExpense->pay_alipay = isset($request->pay_alipay) ? $request->pay_alipay : null;
            $incomeExpense->pay_wechat = isset($request->pay_wechat) ? $request->pay_wechat : null;

            $incomeExpense->status = $request->status;
            // isset($request->description) ? ($incomeExpense->description =  $request->description) : ($incomeExpense->description = '');
            $incomeExpense->description = isset($request->description) ?  $request->description : $request->sub_type;
            $incomeExpense->save();

            if ($request->sub_type == 'return') {
                $return_parcels = ReturnParcel::where('income_expenses_id', $incomeExpense->id)->get();
                if (count($return_parcels) > 0) {
                    foreach ($return_parcels as $return) {
                        $return->created_at = $request->date_return;      
                        $return->car_number = $request->delivery_car_no;
                        $return->driver_name = $request->delivery_person;
                        $return->save();
                        if ($request->status == 'verify') {
                            $parcel = $return->Parcel;
                            $parcel->status = 'return';
                            $parcel->save();
                        }
                    }
                } else {
                    $check_return = Parcel::whereIn('track_no', $request->item)->get();
                    foreach ($check_return as $parcel) {
                        $return_parcel = new ReturnParcel();
                        $return_parcel->created_by = $auth_id;
                        $return_parcel->parcel_id = $parcel->id;
                        // $return_parcel->created_at = $request->date_return;            
                        $return_parcel->income_expenses_id = $incomeExpense->id;
                        $return_parcel->car_number = $request->delivery_car_no;
                        $return_parcel->driver_name = $request->delivery_person;
                        $return_parcel->save();
                        if ($request->status == 'verify') {
                            $parcel->status = 'return';
                            $parcel->save();
                        }
                    }
                }
            }

            if ($request->status == 'verify') {
                $balance_previous = Balance::orderBy('id', 'desc')->first();
                $balance = new Balance();
                $balance->amount_lak = $incomeExpense->amount_lak;
                $balance->amount_cny = $incomeExpense->amount_cny;
                $balance->balance_amount_lak = $balance_previous ?  ($balance_previous->balance_amount_lak + $incomeExpense->amount_lak) : ($incomeExpense->amount_lak);
                $balance->balance_amount_cny = $balance_previous ?  ($balance_previous->balance_amount_cny + $incomeExpense->amount_cny) : ($incomeExpense->amount_cny);
                $balance->income_id = $incomeExpense->id;
                $balance->save();
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


    public function updateExpense(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'item' => 'numeric',
            'weight' => 'numeric',
            'amount_refund' => 'numeric',
            'sub_type' => 'required|string',
            // 'pay_type' => 'required|string',
            'pay_cash' => 'numeric|nullable',
            'pay_transfer' => 'numeric|nullable',
            'pay_alipay' => 'numeric|nullable',
            'pay_wechat' => 'numeric|nullable',
            'description' => 'string|nullable',
            'status' => 'required|string'
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
            $incomeExpense = IncomeExpense::where('id', $id)->where('status', 'pending')->first();
            if (!$incomeExpense) {
                return response()->json([
                    'message' => 'expense not found or expense is verify',
                    'status' => 'ERROR',
                    'code' => 400,
                ], 400);
            }
            if ($request->sub_type == 'other' && $incomeExpense->sub_type == 'refund') {
                $return_parcel = ReturnParcel::where('income_expenses_id', $incomeExpense->id)->first();
                $return_parcel->delete();
            }
            if ($incomeExpense->amount_lak != $request->amount_refund) {
                $currency_now = Currency::orderBy('id', 'desc')->first();
                $incomeExpense->amount_lak = $request->amount_refund;
                $amount_cny = $request->amount_return / ($currency_now->amount_cny * $currency_now->amount_lak);
                $incomeExpense->amount_cny = round($amount_cny * 100) / 100;
            }
            $incomeExpense->sub_type = $request->sub_type;
            // $incomeExpense->pay_type = $request->pay_type;
            $incomeExpense->pay_cash = isset($request->pay_cash) ? $request->pay_cash : null;
            $incomeExpense->pay_transfer = isset($request->pay_transfer) ? $request->pay_transfer : null;
            $incomeExpense->pay_alipay = isset($request->pay_alipay) ? $request->pay_alipay : null;
            $incomeExpense->pay_wechat = isset($request->pay_wechat) ? $request->pay_wechat : null;
            
            $incomeExpense->status = $request->status;
            // isset($request->description) ? ($incomeExpense->description =  $request->description) : ($incomeExpense->description = '');
            $incomeExpense->description = isset($request->description) ?  $request->description : $request->sub_type;
            $incomeExpense->save();
            if ($request->sub_type == 'refund') {
                $return_parcel = ReturnParcel::where('income_expenses_id', $incomeExpense->id)->first();
                if ($return_parcel) {
                    $return_parcel->weight = $request->weight;
                    $return_parcel->refund_amount_lak = $incomeExpense->amount_lak;
                    $return_parcel->refund_amount_cny = $incomeExpense->amount_cny;;
                    $return_parcel->save();
                    if ($request->status == 'verify') {
                        $parcel = $return_parcel->Parcel;
                        $parcel->status = 'ready';
                        $parcel->save();
                    }
                } else {
                    $parcel = Parcel::where(['track_no' => $request->item])->first();
                    if (!$parcel) {
                        return response()->json([
                            'msg' => 'parcel not found.',
                            'status' => 'ERROR',
                            'errors' => array()
                        ], 400);
                    }
                    if (!$parcel->status == 'success') {
                        return response()->json([
                            'msg' => 'parcel unsuccess.',
                            'status' => 'ERROR',
                            'errors' => array()
                        ], 400);
                    }
                    $return_parcel = new ReturnParcel();
                    $return_parcel->created_by = $auth_id;
                    $return_parcel->parcel_id = $parcel->id;
                    $return_parcel->income_expenses_id = $incomeExpense->id;
                    $return_parcel->weight = $request->weight;
                    $return_parcel->refund_amount_lak = $incomeExpense->amount_lak;
                    $return_parcel->refund_amount_cny = $incomeExpense->amount_cny;;
                    $return_parcel->save();
                    if ($request->status == 'verify') {
                        $parcel->status = 'ready';
                        $parcel->save();
                    }
                }
            }

            if ($request->status == 'verify') {
                $balance_previous = Balance::orderBy('id', 'desc')->first();
                $balance = new Balance();
                $balance->amount_lak = $incomeExpense->amount_lak;
                $balance->amount_cny = $incomeExpense->amount_cny;;
                $balance->balance_amount_lak = $balance_previous ?  ($balance_previous->balance_amount_lak - $incomeExpense->amount_lak) : (0 - $incomeExpense->amount_lak);
                $balance->balance_amount_cny = $balance_previous ?  ($balance_previous->balance_amount_cny - $incomeExpense->amount_cny) : (0 - $incomeExpense->amount_cny);
                $balance->income_id = $incomeExpense->id;
                $balance->save();
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
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $incomeExpense = IncomeExpense::find($id);
        if (!$incomeExpense) {
            return response()->json([
                'message' => 'income expense not found',
                'status' => 'ERROR',
                'code' => 404,
            ], 400);
        }

        $incomeExpense->delete();

        return response()->json([
            'status' => 'OK',
            'code' => '200',
            'data' => null
        ], 200);
    }
}
