<?php

namespace App\Http\Controllers;

use App\Models\Balance;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\IncomeExpense;
use App\Models\Parcel;
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
                    $query->Where($Operator->FiltersOperators(explode(':', $filter)));
                }
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
            'phone' => 'required|string',
            'item' => 'required|array',
            'item.*' => 'string',
            'date_return' => 'required|date_format:Y-m-d H:i:s',
            'delivery_car_no' => 'required|string',
            'delivery_person' => 'required|string',
            'sub_type' => 'required|string',
            'description' => 'string'
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
            $customer = Customer::with([
                'CustomerLevel' => function ($query) {
                    $query->select('id', 'rate');
                },
            ])->where('phone',  $request->phone)->first();
            if (!$customer) {
                return response()->json([
                    'msg' => 'customer not found.',
                    'status' => 'ERROR',
                    'errors' => array()
                ], 400);
            }
            $currency_now = Currency::orderBy('id', 'asc')->first();

            $incomeExpense = new IncomeExpense();
            $incomeExpense->type = 'income';
            $incomeExpense->status = 'return';

            $price_bill  = 0;
            $parcels = Parcel::whereIn('track_no', $request->item)->get();
            foreach ($parcels as $parcel) {
                $price_bill += ($parcel->weight * $parcel->price);

                $return_parcel = new ReturnParcel();
                $return_parcel->created_by = $auth_id;
                $return_parcel->car_number = $request->delivery_car_no;
                $return_parcel->driver_name = $request->delivery_person;
                $return_parcel->amount_cny = $parcel->price / ($currency_now->amount_cny * $currency_now->amount_lak);
                $return_parcel->parcel_id = $parcel->id;
                $return_parcel->save();

                // $parcel->status = 'return';
                // $parcel->save();
            }

            $incomeExpense->amount_lak = $price_bill;
            $incomeExpense->amount_cny = $price_bill / ($currency_now->amount_cny * $currency_now->amount_lak);
            $incomeExpense->save();

            // $balance = new Balance();
            // $balance->income_id = $incomeExpense->id;

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
            'parcel_id' => 'required|numeric',
            'weight' => 'required|numeric',
            'amount_refund' => 'required|numeric',
            'sub_type' => 'required|string',
            'description' => 'string'
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
            $incomeExpense = new IncomeExpense();
            $incomeExpense->type = 'expense';
            $incomeExpense->sub_type = $request->sub_type;
            $incomeExpense->status = 'pending';
            isset($request->description) ? ($incomeExpense->description =  $request->description) : $incomeExpense->description = 'expense';
            $incomeExpense->save();

            $currency_now = Currency::orderBy('id', 'asc')->first();

            $parcels = Parcel::whereIn('track_no', $request->item)->where('status', 'success')->get();
            foreach ($parcels as $parcel) {
                $return_parcel = new ReturnParcel();
                $return_parcel->parcel_id = $parcel->id;
                $return_parcel->created_by = $auth_id;
                $return_parcel->car_number = $request->delivery_car_no;
                $return_parcel->driver_name = $request->delivery_person;
                $return_parcel->weight = $request->weight;
                $return_parcel->amount_lak = $request->amount_refund;
                $return_parcel->amount_cny = $request->amount_refund / ($currency_now->amount_cny * $currency_now->amount_lak);
                $return_parcel->save();
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
     */

    public function updateExpense(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'weight' => 'numeric',
            'amount_refund' => 'numeric',
            'sub_type' => 'string',
            'description' => 'string',
            'status' => 'string'
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
            $incomeExpense = IncomeExpense::find($id);
            if (!$incomeExpense) {
                return response()->json([
                    'message' => 'income expense not found',
                    'status' => 'ERROR',
                    'code' => 400,
                ], 400);
            }

            if (isset($request->weight)) {
                $incomeExpense->weight = $request->weight;
            }
            if (isset($request->amount_refund)) {
                $incomeExpense->amount_refund = $request->amount_refund;
            }
            if (isset($request->sub_type)) {
                $incomeExpense->sub_type = $request->sub_type;
            }
            if (isset($request->description)) {
                $incomeExpense->description = $request->description;
            }
            if (isset($request->status)) {
                $incomeExpense->status = $request->status;
            }
            $incomeExpense->save();

            $currency_now = Currency::orderBy('id', 'asc')->first();

            // if ($request->status == 'verify') {
            //     $return_parcel = ReturnParcel::whereIn('income_expenses_id', $incomeExpense->id)->get();
                
            //     foreach ($parcels as $parcel) {
            //         // $return_parcel = new ReturnParcel();

            //         $return_parcel->created_by = $auth_id;
            //         $return_parcel->parcel_id = $parcel->id;
            //         $return_parcel->car_number = $request->delivery_car_no;
            //         $return_parcel->driver_name = $request->delivery_person;
            //         $return_parcel->weight = $request->weight;
            //         $return_parcel->amount_lak = $request->amount_refund;
            //         $return_parcel->amount_cny = $request->amount_refund / ($currency_now->amount_cny * $currency_now->amount_lak);
            //         $return_parcel->save();
            //     }
            // }


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



    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'msg' => 'validator wrong.',
                'errors' => $validator->errors()->toJson(),
                'status' => 'Unauthorized',
            ], 400);
        }


        $incomeExpense = IncomeExpense::find($id);
        if (!$incomeExpense) {
            return response()->json([
                'message' => 'income expense not found',
                'status' => 'ERROR',
                'code' => 404,
            ], 400);
        }
        // $incomeExpense->status = $request->status;
        // $incomeExpense->save();

        return response()->json([
            'status' => 'OK',
            'code' => '200',
            'data' => $incomeExpense
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(IncomeExpense $incomeExpense)
    {
        //
    }
}
