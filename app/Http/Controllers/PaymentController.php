<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Payment;
use App\Models\Currency;
use App\Rules\DynamicArray;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $payment = Payment::orderBy('id', 'asc')->get();
        return response()->json([
            'code' => 200,
            'status' => 'OK',
            'data' => $payment
        ], 200);
    }

    /**
     * get by id
     */
    public function getById($id)
    {
        $payment = Payment::where('id', $id)->first();
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
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'active' => 'required|boolean',
            'payment_type' => 'required|array',
            'payment_type.*.name',
            'payment_type.*.amount',
            'payment_type.*.currency',
            'bill' => 'required|array',
            'bill.*' => 'string',
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
            $payment = new Payment();
            $payment->method = $request->payment_type->name;
            $currency_now = Currency::orderBy('id', 'asc')->first();
            switch ($request->payment_type->currency) {
                case 'lak':
                    $payment->amount_lak = $request->payment_type->amount;
                    $payment->amount_cny = $request->payment_type->amount / ($currency_now->amount_cny * $currency_now->amount_lak);
                    break;
                case 'cny':
                    $payment->amount_lak = $request->payment_type->amount / ($currency_now->amount_lak * $currency_now->amount_cny);
                    $payment->amount_cny = $request->payment_type->amount;
                    break;
                default:
                    $payment->amount_lak = 0;
                    $payment->amount_cny = 0;
                    break;
            }
            $bills = Bill::whereIn('bill_no', $request->bill)->get();
            $bills_id = array();
            foreach ($bills as $bill) {
                array_push($bills_id, $bill->id);
            }
            $payment->Bills()->sync($bills_id);
            $payment->status = 'pending';
            $payment->create_by = $auth_id;
            $payment->save();
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
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'active' => 'required|boolean',
            'payment_type' => 'required|array',
            'payment_type.*.name',
            'payment_type.*.amount',
            'payment_type.*.currency',
            'bill' => 'required|array',
            'bill.*' => 'string',
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
            $payment = Payment::where('id', $id)->first();
            if (!$payment) {
                return response()->json([
                    'msg' => 'payment not found.',
                    'status' => 'ERROR',
                    'errors' => array()
                ], 400);
            }
            $payment->method = $request->payment_type->name;
            $currency_now = Currency::orderBy('id', 'asc')->first();
            switch ($request->payment_type->currency) {
                case 'lak':
                    $payment->amount_lak = $request->payment_type->amount;
                    $payment->amount_cny = $request->payment_type->amount / ($currency_now->amount_cny * $currency_now->amount_lak);
                    break;
                case 'cny':
                    $payment->amount_lak = $request->payment_type->amount / ($currency_now->amount_lak * $currency_now->amount_cny);
                    $payment->amount_cny = $request->payment_type->amount;
                    break;
                default:
                    $payment->amount_lak = 0;
                    $payment->amount_cny = 0;
                    break;
            }
            $bills = Bill::whereIn('bill_no', $payment->bill)->get();
            $bills_id = array();
            foreach ($bills as $bill) {
                array_push($bills_id, $bill->id);
            }
            $payment->Bills()->sync($bills_id);
            // $payment->status = 'pending';
            $payment->create_by = $auth_id;
            $payment->save();
            DB::commit();
            return response()->json([
                'code' => 200,
                'status' => 'OK',
                'data' => $payment
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
