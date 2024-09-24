<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CurrencyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $currency = Currency::orderBy('id', 'asc')->get();
        return response()->json([
            'code' => 200,
            'status' => 'OK',
            'data' => $currency
        ], 200);
    }

    /**
     * get by id
     */
    public function getById($id)
    {
        $customerLevel = Currency::where('id', $id)->first();

        if (!$customerLevel) {
            return response()->json([
                'msg' => 'customerLevel not found.',
                'status' => 'ERROR',
                'data' => array()
            ], 400);
        }
        // $customerLevel->updated_at = $customerLevel->updated_at->format('Y-m-d H:i:s');
            // 'updated_at' => $this->updated_at->format('Y-m-d H:i:s');
        return response()->json([
            'code' => 200,
            'status' => 'OK',
            'data' => $customerLevel
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date_format:Y-m-d H:i:s' ,
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
        
        $auth_id = Auth::user()->id;

        $currency = new Currency();
        $currency->date = $request->date;
        $currency->exchange_cny = $request->exchange_cny;
        $currency->exchange_lak = $request->exchange_lak;
        $currency->create_by = $auth_id;
        $currency->save();

        return response()->json([
            'code' => 201,
            'status' => 'Created',
            'data' => $currency
        ], 201);
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
    public function update(Request $request, Currency $currency)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Currency $currency)
    {
        //
    }
}
