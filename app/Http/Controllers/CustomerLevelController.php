<?php

namespace App\Http\Controllers;

use App\Models\CustomerLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\Validator;

class CustomerLevelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = CustomerLevel::query();

            if ($request->has('filters')) {
                $Operator = new FiltersOperator();
                $arrayFilter = explode(',', $request->query('filters', []));
                foreach ($arrayFilter as $filter) {
                    $query->where($Operator->FiltersOperators(explode(':', $filter)));
                }
            }

            if ($request->has('searchText')) {
                $arraySearchText = ['name'];
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
     * get data by id
     */
    public function getById($id)
    {
        $customerLevel = CustomerLevel::where('id', $id)->first();
        // $customerLevel->Customers;
        if (!$customerLevel) {
            return response()->json([
                'msg' => 'customerLevel not found.',
                'status' => 'ERROR',
                'data' => array(),
                'code' => 400
            ], 400);
        }
        return response()->json([
            'code' => 200,
            'status' => 'OK',
            'data' => $customerLevel,
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'description' => 'string',
            'active' => 'required|boolean',
            'rate' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'msg' => ' went wrong.',
                'errors' => $validator->errors()->toJson(),
                'status' => 'Unauthorized',
            ], 400);
        }
        $auth_id = Auth::user()->id;

        $check_name_duplicate = CustomerLevel::where('name', $request->name)->first();
        if ($check_name_duplicate) {
            return response()->json([
                'msg' => 'This name already exists. Please input another one.',
                'errors' => 'duplicate',
                'status' => 'ERROR',
            ], 400);
        }

        $customerLevel = new CustomerLevel();
        $customerLevel->name = $request->name;
        $customerLevel->description = $request->description;
        $customerLevel->active = $request->active;
        $customerLevel->rate = $request->rate;
        $customerLevel->created_by = $auth_id;
        $customerLevel->save();

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

        // print_r()
    }

    /**
     * Display the specified resource.
     */
    public function show(CustomerLevel $customerLevel)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(CustomerLevel $customerLevel)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'description' => 'string',
            'active' => 'required|boolean',
            'rate' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'msg' => 'Something went wrong.',
                'errors' => $validator->errors()->toJson(),
                'status' => 'ERROR',
            ], 400);
        }

        DB::beginTransaction();
        try {
            $check_name_duplicate = CustomerLevel::where('name', $request->name)->first();
            if ($check_name_duplicate && $check_name_duplicate->id != $id) {
                return response()->json([
                    'msg' => 'This name already exists. Please input another one.',
                    'errors' => 'duplicate',
                    'status' => 'ERROR',
                ], 400);
            }
            
            $customerLevel = CustomerLevel::where('id', $id)->first();
            if (!$customerLevel) {
                return response()->json([
                    'msg' => 'customerLevel not found.',
                    'status' => 'ERROR',
                    'errors' => array()
                ], 400);
            }

            $auth_id = Auth::user()->id;

           

            $customerLevel->name = $request->name;
            $customerLevel->description = $request->description;
            $customerLevel->active = $request->active;
            $customerLevel->rate = $request->rate;
            $customerLevel->created_by = $auth_id;

            $customerLevel->save();
            DB::commit();
            return response()->json(
                [
                    'status' => 'OK',
                    'code' => 200,
                    'data' => $customerLevel
                ],
                200
            );
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'msg' => 'Something went wrong.',
                'errors' =>  $e->getMessage(),
                'status' => 'ERROR',
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $customerLevel = CustomerLevel::where('id', $id)->first();
            if (!$customerLevel) {
                return response()->json([
                    'msg' => 'customerLevel not found.',
                    'status' => 'ERROR',
                    'data' => array()
                ], 400);
            }
            $customerLevel->delete();

            return response()->json(
                [
                    'status' => 'OK',
                    'code' => 200,
                    'data' => array()
                ],
                200
            );
        } catch (Exception $e) {
            return response()->json([
                'msg' => 'Something went wrong.',
                'errors' => $e->getMessage(),
                'status' => 'Unauthorized',
            ], 500);
        }
    }
}
