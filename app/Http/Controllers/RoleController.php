<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\RoleResource;
use App\Support\Collection;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $roles = Role::query();

        $Operator = new FiltersOperator();
        if ($request->has('filters')) {
            $arrayFilter = explode(',', $request->query('filters', []));
            foreach ($arrayFilter as $filter) {
                $roles->Where($Operator->FiltersOperators(explode(':', $filter)));
            }
        }

        if ($request->has('sorts')) {
            $arraySorts = explode(',', $request->query('sorts', []));
            foreach ($arraySorts as $sort) {
                [$field, $direction] = explode(':', $sort);
                $roles->orderBy($field, $direction);
            }
        }

        if ($request->has('per_page')) {
            $roles = (new Collection($roles->get()))->paginate($request->query('per_page'));
        }else {
            $roles = clone $roles->get();
        }

        return response()->json([
            'status' => 'OK',
            'code' => 200,
            "data" => $roles
        ], 200);
    }

    public function getById($id)
    {
        $role = Role::find($id);
        $role_reource = RoleResource::with('Resource')->where('role_id', $id)->get();
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
            'data' => [
                'role' => $role,
                'role_resource' => $role_reource
            ]
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
            "permission" => "required|array",
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

            $array_role_resource = [];
            foreach ($request->permission as $key => $value) {
                $roleResource = [
                    "role_id" => $role->id,
                    "resource_id" => $value['id'],
                    "can_view" => $value['can_view'],
                    "can_create" => $value['can_create'],
                    "can_update" => $value['can_update'],
                    "can_delete" => $value['can_delete'],
                    "can_export" => $value['can_export'],
                ];
                array_push($array_role_resource, $roleResource);
            }
            RoleResource::insert($array_role_resource);

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
                    'message' => 'Role not found',
                    'status' => 'ERROR',
                    'code' => 404,
                ], 400);
            }
            $role->name = $request->name;
            $role->description = $request->description;
            $role->active = $request->active;
            $role->save();

            RoleResource::where('role_id', $id)->delete();
            $array_role_resource = [];
            foreach ($request->permission as $key => $value) {
                $roleResource = [
                    "role_id" => $role->id,
                    "resource_id" => $value['id'],
                    "can_view" => $value['can_view'],
                    "can_create" => $value['can_create'],
                    "can_update" => $value['can_update'],
                    "can_delete" => $value['can_delete'],
                    "can_export" => $value['can_export'],
                ];
                array_push($array_role_resource, $roleResource);
            }
            RoleResource::insert($array_role_resource);

            DB::commit();
            return response()->json([
                'status' => 'Updated',
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
                'message' => 'Role not found',
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
