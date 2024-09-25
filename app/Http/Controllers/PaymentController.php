<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Currency;
use App\Rules\DynamicArray;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'active' => 'required|boolean',
            'payment_type' => 'required|array', 'payment_type.*.name', 'payment_type.*.amount', 'payment_type.*.currency',
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
        dd($validator);

        $auth_id = Auth::user()->id;

        $currency_current = Currency::orderBy('id', 'asc')->first();

        $payment = new Payment();

        switch ($request->payment_type->currency) {
            case 'lak':
                $payment->amount_lak = $request->payment_type->amount;
                $payment->amount_cny = $request->payment_type->amount * $currency_current->exchange_cny;
                break;
            case 'cny':
                $payment->amount_lak = $request->payment_type->amount / $currency_current->exchange_cny;
                $payment->amount_cny = $request->payment_type->amount;
                break;
            default:
                break;
        }
       
        $payment->method = $request->payment_type->name;
        $payment->status = 'pending';
        $payment->create_by = $auth_id;
        $payment->save();

        return response()->json([
            'code' => 201,
            'status' => 'Created',
            'data' => $customerLevel
        ], 200);
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
    public function update(Request $request, Payment $payment)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Payment $payment)
    {
        //
    }
}
