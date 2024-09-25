<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json([
            'status' => 'OK',
            'code' => 200,
            "data" => Department::get()
        ], 200);
    }

    public function getById($id)
    {
        $department = Department::find($id);
        if (!$department) {
            return response()->json([
                'message' => 'Department not found',
                'status' => 'ERROR',
                'code' => 404,
            ], 400);
        }

        return response()->json([
            'status' => 'OK',
            'code' => '200',
            'data' => $department
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
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }

        DB::beginTransaction();
        try {
            $department = new Department();
            $department->name = $request->name;
            $department->description = $request->description;
            $department->active = $request->active;
            $department->save();

            DB::commit();
            return response()->json([
                'status' => 'Created',
                'code' => 200,
                'data' => $department
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
    public function show(Department $department)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Department $department)
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
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }

        DB::beginTransaction();
        try {
            $department = Department::find($id);
            if (!$department) {
                return response()->json([
                    'message' => 'Department not found',
                    'status' => 'ERROR',
                    'code' => 404,
                ], 400);
            }
            $department->name = $request->name;
            $department->description = $request->description;
            $department->active = $request->active;
            $department->save();

            DB::commit();
            return response()->json([
                'status' => 'Updated',
                'code' => 200,
                'data' => $department
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
        $department = Department::find($id);
        if (!$department) {
            return response()->json([
                'message' => 'Department not found',
                'status' => 'ERROR',
                'code' => 404,
            ], 400);
        }

        $department->delete();

        return response()->json([
            'status' => 'OK',
            'code' => '200',
            'data' => null
        ], 200);
    }
}
