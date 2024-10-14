<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserListResource;
use App\Models\User;
use App\Support\Collection;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with([
            'Department' => function ($query) {
                $query->select('id', 'name');
            },
            'Role' => function ($query) {
                $query->select('id', 'name');
            },
        ]);

        $Operator = new FiltersOperator();
        if ($request->has('filters')) {
            $arrayFilter = explode(',', $request->query('filters', []));
            foreach ($arrayFilter as $filter) {
                $query->Where($Operator->FiltersOperators(explode(':', $filter)));
            }
        }

        if ($request->has('searchText')) {
            $arraySearchText = ['first_name', 'email'];
            $query->whereAny($arraySearchText, 'like', '%' . $request->query('searchText') . '%');
        }

        if ($request->has('sorts')) {
            $arraySorts = explode(',', $request->query('sorts', []));
            foreach ($arraySorts as $sort) {
                [$field, $direction] = explode(':', $sort);
                $query->orderBy($field, $direction);
            }
        }

        $users =  UserListResource::collection($query->get());
        if ($request->has('per_page')) {
            $users = (new Collection($users))->paginate($request->query('per_page'));
        }


        return response()->json(
            [
                'status' => 'OK',
                'code' => 200,
                "data" => $users
            ],
            200
        );
    }

    public function getById($id)
    {
        $user = User::with([
            'Department' => function ($query) {
                $query->select('id', 'name');
            },
            'Role' => function ($query) {
                $query->select('id', 'name');
            },
        ])->where('id', $id)->first();

        if (!$user) {
            return response()->json([
                'msg' => 'User not found.',
                'status' => 'ERROR',
                'data' => null
            ], 400);
        }

        $user = new UserListResource($user);

        return response()->json([
            'msg' => 'User found.',
            'status' => 'OK',
            'data' => $user
        ], 200);
    }
    /**
     * Register a User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function register()
    {
        $validator = Validator::make(request()->all(), [
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
            'department_id' => 'required|integer',
            'role_id' => 'required|integer',
            'active' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }

        $user = new User();
        $user->first_name = request()->name;
        $user->email = request()->email;
        $user->department_id = request()->department_id;
        $user->role_id = request()->role_id;
        $user->password = bcrypt(request()->password);
        $user->save();

        return response()->json([
            'msg' => 'User found.',
            'status' => 'OK',
            'data' => $user
        ], 201);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login()
    {
        $validator = Validator::make(request()->all(), [
            'email' => 'required|email',
            'password' => 'required|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }

        $credentials = request(['email', 'password']);

        if (! $token = Auth::attempt($credentials)) {
            return response()->json([
                'errors' => 'Unauthorized'
            ], 401);
        }

        return $this->respondWithToken($token);
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        return response()->json(Auth::user());
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        Auth::logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    public function destroy($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'message' => 'User not found',
                'status' => 'ERROR',
                'code' => 404,
            ], 400);
        }

        $user->delete();

        return response()->json([
            'status' => 'OK',
            'code' => '200',
            'data' => null
        ], 200);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(Auth::refresh());
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'user' => Auth::user(),
            'expired' => Auth::factory()->getTTL()
        ]);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email',
            'password' => 'min:8',
            'department_id' => 'required|integer',
            'role_id' => 'required|integer',
            'active' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }

        DB::beginTransaction();
        try {
            $user = User::where('id', $id)->first();
            if (!$user) {
                return response()->json([
                    'msg' => 'User not found.',
                    'status' => 'ERROR',
                    'data' => null
                ], 400);
            }

            $user->first_name = $request->name;
            $user->email = $request->email;
            $user->password = bcrypt($request->password);
            $user->department_id = $request->department_id;
            $user->role_id = $request->role_id;
            $user->active = $request->active;
            $user->save();
            DB::commit();

            return response()->json([
                'msg' => 'User found.',
                'status' => 'OK',
                'data' => $user
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
}
