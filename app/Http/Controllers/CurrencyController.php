<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CurrencyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = Currency::query();

            if ($request->has('filters')) {
                $Operator = new FiltersOperator();
                $arrayFilter = explode(',', $request->query('filters', []));
                foreach ($arrayFilter as $filter) {
                    $query->where($Operator->FiltersOperators(explode(':', $filter)));
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
                $currency = $query->paginate($request->query('per_page'));
            } else {
                $currency = $query->get();
            }

            return response()->json([
                'code' => 200,
                'status' => 'OK',
                'data' => $currency,
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
        $currency = Currency::where('id', $id)->first();

        if (!$currency) {
            return response()->json([
                'msg' => 'currency not found.',
                'status' => 'ERROR',
                'data' => array()
            ], 400);
        }
        return response()->json([
            'code' => 200,
            'status' => 'OK',
            'data' => $currency
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'date' => 'required|date_format:Y-m-d H:i:s' ,
            'exchange_cny' => 'required|numeric',
            'exchange_lak' => 'numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'msg' => ' went wrong.',
                'errors' => $validator->errors()->toJson(),
                'status' => 'Unauthorized',
            ], 400);
        }

        try {
            $auth_id = Auth::user()->id;
            $currency = new Currency();
            // $currency->date = $request->date;
            $currency->amount_cny = $request->exchange_cny;
            $currency->amount_lak = $request->exchange_lak;
            $currency->created_by = $auth_id;
            $currency->save();

            return response()->json([
                'code' => 201,
                'status' => 'Created',
                'data' => $currency
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'msg' => 'Something went wrong.',
                'errors' => $e->getMessage(),
                'status' => 'ERROR',
                'code' => 422,
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
    public function show(Currency $currency)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Currency $currency)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            // 'date' => 'required|date_format:Y-m-d H:i:s' ,
            'exchange_cny' => 'required|numeric',
            'exchange_lak' => 'numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'msg' => ' went wrong.',
                'errors' => $validator->errors()->toJson(),
                'status' => 'Unauthorized',
                'code' => 400,
            ], 400);
        }

        try {
            $auth_id = Auth::user()->id;
            $currency = Currency::where('id', $id)->first();
            if (!$currency) {
                return response()->json([
                    'msg' => 'currency not found.',
                    'status' => 'ERROR',
                    'errors' => array()
                ], 400);
            }
            // $currency->date = $request->date;
            $currency->amount_cny = $request->exchange_cny;
            $currency->amount_lak = $request->exchange_lak;
            $currency->created_by = $auth_id;
            $currency->save();

            return response()->json([
                'code' => 200,
                'status' => 'OK',
                'data' => $currency
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'msg' => 'Something went wrong.',
                'errors' => $e->getMessage(),
                'status' => 'ERROR',
                'code' => 500,
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $currency = Currency::find($id);
        if (!$currency) {
            return response()->json([
                'message' => 'currency not found',
                'status' => 'ERROR',
                'code' => 404,
            ], 400);
        }

        $currency->delete();

        return response()->json([
            'status' => 'OK',
            'code' => '200',
            'data' => array()
        ], 200);
    }
}
