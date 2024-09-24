<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json([
            'status' => 'OK',
            'code' => 200,
            "data" => Role::get()
        ], 200);
    }

    public function getById($id)
    {
        $role = Role::find($id);
        if (!$role) {
            return response()->json([
                'message' => 'User not found',
                'status' => 'ERROR',
                'code' => 404,
            ], 400);
        }

        return response()->json([
            'status' => 'OK',
            'code' => '200',
            'data' => $role
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'name' => 'required|string',
            'description' => 'string',
            'active' => 'required|boolean',
            "permission"    => "required|array"
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }

        DB::beginTransaction();
        try {
            $role = new Role();
            $role->name = $request->name;
            $role->description = $request->description;
            $role->active = $request->active;
            $role->save();

            $role->Resources()->sync($request->permission);
            DB::commit();
            return response()->json([
                'status' => 'Created',
                'code' => 200,
                'data' => $role
            ], 201);
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
    public function show(Role $role)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Role $role)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make(request()->all(), [
            'name' => 'required|string',
            'description' => 'string',
            'active' => 'required|boolean',
            "permission"    => "required|array"
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }

        DB::beginTransaction();
        try {
            $role = Role::find($id);
            if (!$role) {
                return response()->json([
                    'message' => 'User not found',
                    'status' => 'ERROR',
                    'code' => 404,
                ], 400);
            }
            $role->name = $request->name;
            $role->description = $request->description;
            $role->active = $request->active;
            $role->save();

            $role->Resources()->sync($request->permission);
            DB::commit();
            return response()->json([
                'status' => 'Created',
                'code' => 200,
                'data' => null
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
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $role = Role::find($id);
        if (!$role) {
            return response()->json([
                'message' => 'User not found',
                'status' => 'ERROR',
                'code' => 404,
            ], 400);
        }

        $role->delete();

        return response()->json([
            'status' => 'OK',
            'code' => '200',
            'data' => null
        ], 200);
    }
}
