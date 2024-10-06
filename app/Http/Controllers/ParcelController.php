<?php

namespace App\Http\Controllers;

use App\Exports\ParcelExport;
use App\Imports\ParcelImport;
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
    public function index(Request $request)
    {
        try {
            $query = Parcel::query();

            if ($request->has('filters')) {
                $Operator = new FiltersOperator();
                $arrayFilter = explode(',', $request->query('filters', []));
                foreach ($arrayFilter as $filter) {
                    $query ->where($Operator->FiltersOperators(explode(':', $filter)));
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
                $parcel = $query->paginate($request->query('per_page'));
            } else {
                $parcel = $query->get();
            }

            return response()->json([
                'code' => 200,
                'status' => 'OK',
                'data' => $parcel,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'msg' => $e,
                'status' => 'ERROR',
                'error' => array(),
                'code' => 401
            ], 401);
        }
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
        return Excel::download(new ParcelExport, 'users.xlsx');
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function import(Request $request)
    {
        // Validate incoming request data
        $request->validate([
            'file' => 'required|max:2048',
        ]);
        Excel::import(new ParcelImport, $request->file('file'));

        return back()->with('success', 'Users imported successfully.');
    }
}