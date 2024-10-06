<?php

namespace App\Http\Controllers;

use App\Exports\ParcelExport;
use App\Imports\ParcelImport;
use App\Models\Customer;
use App\Models\Parcel;
use App\Models\ReturnParcel;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ParcelController extends Controller
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
        // inprogress
        $validator = Validator::make(request()->all(), [
            // 'phone' => 'required|string',
            'item' => 'required|array|numeric',
            'return_date' => 'required|date_format:Y-m-d H:i:s',
            'delivery_car_no' => 'required|string',
            'delivery_person' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }

        DB::beginTransaction();
        try {
            $returnParcelArr = [];
            foreach ($request->item as $key => $value) {
                $arr = [];
                $arr['parcel_id'] = $value;
                $arr['driver_name'] = $request->delivery_person;
                $arr['car_number'] = $request->delivery_car_no;
                $arr['created_at'] = $request->return_date;
                $arr['created_by'] = Auth::user()->id;
                array_push($arr, $returnParcelArr);
            }

            DB::commit();
            return response()->json([
                'status' => 'Created',
                'code' => 200,
                // 'data' => $returnParcel
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'msg' => 'Something went wrong.',
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
    public function show(Parcel $parcel)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Parcel $parcel)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Parcel $parcel)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Parcel $parcel)
    {
        //
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function export()
    {
        return Excel::download(new ParcelExport, 'parcels.xlsx');
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function import(Request $request)
    {
        // Validate incoming request data
        $request->validate([
            'file' => 'required',
        ]);

        DB::beginTransaction();

        try {
            $import = new ParcelImport;
            $import->import($request->file('file'));

            if ($import->failures()->count() > 0) {
                $failuredArray = array_map(function ($failure) {
                    return $failure[0];
                }, $import->failures()->toArray());

                return response()->json([
                    'status' => false,
                    'message' => 'Import excel file failed',
                    'code' => 400,
                    'errors' => $failuredArray,
                ], 400);
            }

            $parcelArray = $import->getArray();
            $customer_phone = array_filter(array_column($parcelArray, 'phone'));
            $customer_phone = array_unique($customer_phone);

            $customerPhoneCreated = Customer::whereIn('phone', $customer_phone)->get()->pluck('phone')->toArray();
            $customerPhoneDiff = array_diff($customer_phone, $customerPhoneCreated);

            $customerArr = [];
            foreach ($customerPhoneDiff as $key => $value) {
                $customer = [
                    'phone' => $value,
                    'name' => null,
                    'address' => null,
                    'active' => false,
                ];
                array_push($customer, $customerArr);
            }

            Customer::insert($customerArr);
            Parcel::insert($parcelArray);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Import excel file Successfully',
                'code' => 201,
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Import excel file failed',
                'code' => 400,
                'errors' => $e->getMessage(),
            ], 400);
        }
    }
}
