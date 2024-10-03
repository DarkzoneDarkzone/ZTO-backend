<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Parcel;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BillController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $bill = Bill::orderBy('id', 'asc')->get();
        return response()->json([
            'code' => 200,
            'status' => 'OK',
            'data' => $bill
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'address' => 'required|string',
            'phone' => 'required|string',
            'item' => 'required|array',
            'item.*' => 'string',
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
            $bill = new Bill();
            $customer = Customer::with([
                'CustomerLevel' => function ($query) {
                    $query->select('rate');
                },
            ])->where('phone',  $request->phone)->first();
            if (!$customer) {
                return response()->json([
                    'msg' => 'customer not found.',
                    'status' => 'ERROR',
                    'errors' => array()
                ], 400);
            }
            $rate = $customer->customer_level->rate;

            $price_parcels  = 0;
            $parcels = Parcel::whereIn('track_no', $request->item)->get();
            foreach ($parcels as $parcel) {
                $price_parcels += ($parcel->price * ($rate / 100));
            }

            $currency_now = Currency::orderBy('id', 'asc')->first();

            $bill->amount_lak = $price_parcels;
            $bill->amount_cny = $price_parcels / ($currency_now->amount_cny * $currency_now->amount_lak);
          
            $bill->name = $request->name;
            $bill->phone = $request->phone;
            $bill->address = $request->address;
            // $bill->bill_no = 'sk....';

            $bill->status = 'ready';
            $bill->create_by = $auth_id;
            $bill->save();
            DB::commit();
            return response()->json([
                'code' => 201,
                'status' => 'Created',
                'data' => $bill
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
    public function show(Bill $bill)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function createShipping(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bill_no' => 'required|array',
            'bill_no.*' => 'string',
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
            $bill = new Bill();
            $customer = Customer::with([
                'CustomerLevel' => function ($query) {
                    $query->select('rate');
                },
            ])->where('phone',  $request->phone)->first();
            if (!$customer) {
                return response()->json([
                    'msg' => 'customer not found.',
                    'status' => 'ERROR',
                    'errors' => array()
                ], 400);
            }
            $rate = $customer->customer_level->rate;

            $price_parcels  = 0;
            $parcels = Parcel::whereIn('track_no', $request->item)->get();
            foreach ($parcels as $parcel) {
                $price_parcels += ($parcel->price * ($rate / 100));
            }

            $currency_now = Currency::orderBy('id', 'asc')->first();

            $bill->amount_lak = $price_parcels;
            $bill->amount_cny = $price_parcels / ($currency_now->amount_cny * $currency_now->amount_lak);
          
            $bill->name = $request->name;
            $bill->phone = $request->phone;
            $bill->address = $request->address;
            // $bill->bill_no = 'sk....';

            $bill->status = 'ready';
            $bill->create_by = $auth_id;
            $bill->save();
            DB::commit();
            return response()->json([
                'code' => 201,
                'status' => 'Created',
                'data' => $bill
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
     * Update the specified resource in storage.
     */
    public function update(Request $request, Bill $bill)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Bill $bill)
    {
        //
    }
}
