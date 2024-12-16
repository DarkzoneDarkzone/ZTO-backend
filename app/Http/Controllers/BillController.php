<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\BillPayment;
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
use Illuminate\Database\Query\JoinClause;

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
                    $query->where($Operator->FiltersOperators(explode(':', $filter)));
                }
            }
            if ($request->has('searchText')) {
                $arraySearchText = ['bill_no', 'name', 'phone'];
                $query->whereAny($arraySearchText, 'like', '%' . $request->query('searchText') . '%');
            }

            $sub_q = Parcel::select('bill_id', DB::raw('SUM(weight) as total_weight'))->whereNotNull('bill_id')->groupBy('bill_id');

            $query = $query->joinSub($sub_q, 'parcel_q', function (JoinClause $join) {
                $join->on('parcel_q.bill_id', '=', 'bills.id');
            });

            if ($request->has('sorts')) {
                $arraySorts = explode(',', $request->query('sorts', []));
                foreach ($arraySorts as $sort) {
                    [$field, $direction] = explode(':', $sort);
                    $query->orderBy($field, $direction);
                }
            }

            if ($request->has('per_page')) {
                $bill = $query->paginate($request->query('per_page'));
            } else {
                $bill = $query->get();
            }
            return response()->json([
                'code' => 200,
                'status' => 'OK',
                'data' => $bill,
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
        $bill = Bill::where('id', $id);

        $sub_q = Parcel::select('bill_id', DB::raw('SUM(weight) as total_weight'))->whereNotNull('bill_id')->groupBy('bill_id');
        $bill = $bill->joinSub($sub_q, 'parcel_q', function (JoinClause $join) {
            $join->on('parcel_q.bill_id', '=', 'bills.id');
        })->first();

        $bill_parcel = Bill::find($id);
        $bill->item = $bill_parcel->Parcels;
        unset($bill->deleted_at);
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
            'data' => $bill,
        ], 200);
    }


    /**
     * Show the form for creating a new resource.
     */
    public function createBill(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'address' => 'required|string',
            'phone' => 'required|string',
            'level' => 'string',
            'item' => 'required|array',
            'item.*' => 'string',
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

            if (!$customer->CustomerLevel->rate) {
                return response()->json([
                    'msg' => 'customer not have level.',
                    'status' => 'ERROR',
                    'errors' => array()
                ], 400);
            }

            $rate = $customer->CustomerLevel->rate;
            $price_bill_lak  = 0;
            $parcels = Parcel::whereIn('track_no', $request->item)->get();
            foreach ($parcels as $parcel) {
                // if ($parcel->status != 'pending') {
                //     return response()->json([
                //         'msg' => 'some parcel is not pending.',
                //         'status' => 'ERROR',
                //         'data' => array()
                //     ], 400);
                // }
                ///////// weight is kg. convert to g.
                // $parcel->weight = $parcel->weight * 100;
                $price_bill_lak += ($parcel->weight * $rate);
            }
            $currency_now = Currency::orderBy('id', 'desc')->first();

            $amount_cny_convert = $price_bill_lak / ($currency_now->amount_cny * $currency_now->amount_lak);

            $amount_lak_convert = 0;
            if ($price_bill_lak < 1000) {
                $amount_lak_convert = ceil($price_bill_lak / 100) * 100;
            } else {
                $amount_lak_convert = ceil(floor($price_bill_lak) / 1000) * 1000;
            }
            $bill->amount_lak = $amount_lak_convert;
            $bill->amount_cny = ceil($amount_cny_convert * 100) / 100;
            $bill->name = $request->name;
            $bill->phone = $request->phone;
            $bill->address = $request->address;
            $bill->status = 'ready';
            $bill->created_by = $auth_id;
            $currentDate = Carbon::now()->format('ym');
            $billCount = Bill::whereYear('created_at', Carbon::now()->year)
                ->whereMonth('created_at', Carbon::now()->month)
                ->orderBy('id', 'desc')->first();
            if (!$billCount) {
                $bill->bill_no = $currentDate . '-' . sprintf('%05d', 00001);
            } else {
                $ex = explode('-', $billCount->bill_no);
                $number = (int) $ex[1];
                $bill->bill_no = $currentDate . '-' . sprintf('%05d', $number + 1);
            }
            $bill->save();

            foreach ($parcels as $parcel) {
                $parcel->bill_id = $bill->id;
                $parcel->status = 'ready';
                if ($parcel->phone == 0) {
                    $parcel->phone = $request->phone;
                    $parcel->name = $request->name;
                }
                $parcel->price_bill = $parcel->weight * $rate;
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
            $errors_val = $this->ValidatorErrors($validator);
            return response()->json([
                'msg' => 'validator errors',
                'errors' => $errors_val,
                'status' => 'ERROR',
            ], 400);
        }

        DB::beginTransaction();
        try {
            $bills = Bill::whereIn('bill_no', $request->bill_no)->orderBy('id', 'desc')->where('status', 'ready')->get();
            if (count($bills) == 0) {
                return response()->json([
                    'msg' => 'bills not found or status not ready .',
                    'status' => 'ERROR',
                    'data' => array()
                ], 400);
            }
            foreach ($bills as $bill) {
                $bill->status = 'shipped';
                $bill->save();
                foreach ($bill->Parcels as $parcel) {
                    $parcel->shipping_at = Carbon::now();
                    $parcel->save();
                }
            }

            DB::commit();
            return response()->json([
                'code' => 200,
                'status' => 'created',
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
    public function updateBill(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'address' => 'required|string',
            'phone' => 'required|string',
            'item' => 'required|array',
            'item.*' => 'string',
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
            $bill = Bill::find($id);

            if ($bill->name != $request->name) {
                $bill->name = $request->name;
            }
            if ($bill->address != $request->address) {
                $bill->address = $request->address;
            }
            if ($bill->phone != $request->phone) {
                $bill->phone = $request->phone;
            }

            $have_charge_item = false;
            foreach ($bill->Parcels as $key => $parcel) {
                if (!in_array($parcel->track_no, $request->item)) {
                    $parcel->status = 'pending';
                    $parcel->bill_id = null;
                    $parcel->save();
                    $have_charge_item = true;
                }
            }

            if ($have_charge_item) {
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
                if (!$customer->CustomerLevel) {
                    return response()->json([
                        'msg' => 'customer not yet Level.',
                        'status' => 'ERROR',
                        'errors' => array()
                    ], 400);
                }
                $rate = $customer->CustomerLevel->rate;

                $price_bill_lak  = 0;
                $parcels = Parcel::whereIn('track_no', $request->item)->get();
                foreach ($parcels as $parcel) {
                    $price_bill_lak += ($parcel->weight * $rate);
                }
                $currency_now = Currency::orderBy('id', 'desc')->first();

                $amount_cny_convert = $price_bill_lak / ($currency_now->amount_cny * $currency_now->amount_lak);
                $bill->amount_lak = ceil($price_bill_lak * 100) / 100;
                $bill->amount_cny = ceil($amount_cny_convert * 100) / 100;
                $bill->status = 'ready';
                $bill->created_by = $auth_id;

                foreach ($parcels as $parcel) {
                    $parcel->bill_id = $bill->id;
                    $parcel->status = 'ready';
                    $parcel->save();
                }
            }
            $bill->save();

            DB::commit();
            return response()->json([
                'code' => 201,
                'status' => 'update',
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
        // if ($bill->status != ) {
        //     # code...
        // }
        foreach ($bill->Parcels as $key => $parcel) {
            $parcel->status = 'pending';
            $parcel->bill_id = null;
            $parcel->save();
        }

        $bill->delete();

        return response()->json([
            'status' => 'OK',
            'code' => '200',
            'data' => null
        ], 200);
    }
}
