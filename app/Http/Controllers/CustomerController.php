<?php

namespace App\Http\Controllers;

use App\Http\Resources\CustomerListResource;
use App\Models\Customer;
use App\Models\CustomerLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\Auth;
use App\Support\Collection;
use Illuminate\Database\Query\JoinClause;

class CustomerController extends Controller
{
    /**
     * get data all
     */
    public function index(Request $request)
    {
        try {
            $query = Customer::query();

            $Operator = new FiltersOperator();
            if ($request->has('filters')) {
                $arrayFilter = explode(',', $request->query('filters', []));
                foreach ($arrayFilter as $filter) {
                    $ex = explode(':', $filter);
                    $query->where($Operator->FiltersOperators(['customers.' . $ex[0], $ex[1], $ex[2]]));
                }
            }
            $query->join('customer_levels', 'customer_levels.id', '=', 'customers.customer_level_id');
            $query->select('customers.*', 'customer_levels.name as level_name', 'customer_levels.rate as level_rate');
            if ($request->has('searchText')) {
                $arraySearchText = ['customers.name', 'customers.phone', 'customer_levels.name'];
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
                $customer = $query->paginate($request->query('per_page'));
            } else {
                $customer = $query->get();
            }
            // $query->with([
            //     'CustomerLevel' => function ($query) {
            //         $query->select('id', 'name', 'rate');
            //     },
            // ]);
            // $customer =  CustomerListResource::collection($query->get());
            // if ($request->has('per_page')) {
            //     $customer = (new Collection($customer))->paginate($request->query('per_page'));
            // }
            return response()->json([
                'code' => 200,
                'status' => 'OK',
                'data' => $customer,
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
        $customer = Customer::with([
            'CustomerLevel' => function ($query) {
                $query->select('id', 'name');
            },
        ])->where('id', $id)->first();

        if (!$customer) {
            return response()->json([
                'code' => 400,
                'status' => 'ERROR',
                'errors' => array()
            ], 400);
        }

        return response()->json([
            'code' => 200,
            'status' => 'OK',
            'data' => $customer
        ], 200);
    }

    /**
     * create data 
     */
    public function create(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string',
                'phone' => 'required|string',
                'address' => 'string',
                'level_id' => 'required|numeric',
                'verify' => 'required|boolean',
                'active' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'msg' => ' went wrong.',
                    'errors' => $validator->errors()->toJson(),
                    'status' => 'Unauthorized',
                ], 400);
            }

            $check_phone_duplicate = Customer::where('phone', $request->phone)->first();
            if ($check_phone_duplicate) {
                return response()->json([
                    'msg' => 'This phone already exists. Please input another one.',
                    'errors' => 'duplicate',
                    'status' => 'ERROR',
                ], 400);
            }

            $auth_id = Auth::user()->id;

            $customer = new Customer();
            $customer->name = $request->name;
            $customer->phone = $request->phone;
            $customer->address = $request->address;
            $customer->customer_level_id = $request->level_id;
            $customer->verify = $request->verify;
            $customer->active = $request->active;
            $customer->created_by = $auth_id;
            $customer->save();

            return response()->json([
                'status' => 'Created',
                'code' => 201,
                'data' => $customer
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'msg' => 'Something went wrong.',
                'errors' => $e->getMessage(),
                'status' => 'Unauthorized',
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
    public function show(Customer $customer)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Customer $customer)
    {
        //
    }

    /**
     * Update date.
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'phone' => 'required|string',
            'address' => 'string',
            'level_id' => 'required|numeric',
            'verify' => 'required|boolean',
            'active' => 'required|boolean'
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
            $check_phone_duplicate = CustomerLevel::where('phone', $request->phone)->first();
            if ($check_phone_duplicate && $check_phone_duplicate->id != $id) {
                return response()->json([
                    'msg' => 'This name already exists. Please input another one.',
                    'errors' => 'duplicate',
                    'status' => 'ERROR',
                ], 400);
            }

            $customer = Customer::where('id', $id)->first();
            if (!$customer) {
                return response()->json([
                    'msg' => 'customer not found.',
                    'status' => 'ERROR',
                    'errors' => array()
                ], 400);
            }

        
            $customer->name = $request->name;
            $customer->phone = $request->phone;
            $customer->address = $request->address;
            $customer->customer_level_id = $request->level_id;
            $customer->verify = $request->verify;
            $customer->active = $request->active;

            $auth_id = Auth::user()->id;
            $customer->created_by = $auth_id;

            $customer->save();
            DB::commit();
            return response()->json(
                [
                    'status' => 'OK',
                    'code' => 200,
                    'data' => $customer
                ],
                200
            );
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
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $customer = Customer::where('id', $id)->first();
            if (!$customer) {
                return response()->json([
                    'msg' => 'customer not found.',
                    'status' => 'ERROR',
                    'data' => array()
                ], 400);
            }
            $customer->delete();

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
                'status' => 'ERROR',
                'code' => 500
            ], 500);
        }
    }
}
