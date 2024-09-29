<?php

namespace App\Http\Controllers;

use App\Models\Bill;
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
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
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
            $customer = Customer::whereIn('phone', $request->phone)->get();
            if (!$customer) {
                return response()->json([
                    'msg' => 'customer not found.',
                    'status' => 'ERROR',
                    'errors' => array()
                ], 400);
            }
            $bill->customer_id = $customer->id;
            $parcels = Parcel::whereIn('track_no', $request->item)->get();
            $parcels_id = array();
            foreach ($parcels as $parcel) {
                array_push($parcels_id, $parcel->id);
            }
            $bill->Payments()->sync($parcels_id);
            $bill->phone = $request->phone;
            // $bill->amount_lak = ....;
            // $bill->amount_cny = ....;
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
    public function edit(Bill $bill)
    {
        //
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
