<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Parcel;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class BillController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        try {
            $query = Bill::query();

            if ($request->has('filters')) {
                $Operator = new FiltersOperator();
                $arrayFilter = explode(',', $request->query('filters', []));
                foreach ($arrayFilter as $filter) {
                    $query->orWhere($Operator->FiltersOperators(explode(':', $filter)));
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
                $customerLevel = $query->paginate($request->query('per_page'));
            } else {
                $customerLevel = $query->get();
            }

            return response()->json([
                'code' => 200,
                'status' => 'OK',
                'data' => $customerLevel,
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
        $bill = Bill::where('id', $id)->first();
        if (!$bill) {
            return response()->json([
                'msg' => 'bill not found.',
                'status' => 'ERROR',
                'data' => array()
            ], 400);
        }
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
            $rate = $customer->CustomerLevel->rate;
            $price_bill  = 0;
            $parcels = Parcel::whereIn('track_no', $request->item)->get();
            foreach ($parcels as $parcel) {
                $price_bill += ($parcel->weight * $rate);
            }
            $currency_now = Currency::orderBy('id', 'asc')->first();
            $bill->amount_lak = $price_bill;
            $bill->amount_cny = $price_bill / ($currency_now->amount_cny * $currency_now->amount_lak);
            $bill->name = $request->name;
            $bill->phone = $request->phone;
            $bill->address = $request->address;
            $bill->status = 'ready';
            $bill->created_by = $auth_id;

            $currentDate = Carbon::now()->format('ym');
            $billCount = Bill::whereYear('created_at', Carbon::now()->year)
                ->whereMonth('created_at', Carbon::now()->month)
                ->count() + 1;
            $bill->bill_no = $currentDate . '-' . sprintf('%04d', $billCount);

            $bill->save();
            foreach ($parcels as $parcel) {
                $parcel->bill_id = $bill->id;
                $parcel->status = 'ready';
                $parcel->save();
            }

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
            // $auth_id = Auth::user()->id;

            $bills = Bill::whereIn('bill_no', $request->bill_no)->get();

            foreach ($bills as $bill) {
                foreach ($$bill->Parcels as $parcel) {
                    $parcel->shipping_at = new DateTime();
                    $parcel->save();
                }
                $bill->status = 'shipped';
                // $bill->created_by = 
                $bill->save();
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
                'code' => 500
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
    public function destroy($id)
    {
        $bill = Bill::find($id);
        if (!$bill) {
            return response()->json([
                'message' => 'bill not found',
                'status' => 'ERROR',
                'code' => 404,
            ], 400);
        }

        $bill->delete();

        return response()->json([
            'status' => 'OK',
            'code' => '200',
            'data' => null
        ], 200);
    }
}
