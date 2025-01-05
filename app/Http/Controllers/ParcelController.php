<?php

namespace App\Http\Controllers;

use App\Exports\ParcelExport;
use App\Imports\ParcelImport;
use App\Models\Bill;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Parcel;
use App\Models\ReturnParcel;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Query\JoinClause;
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
                    $ex = explode(':', $filter);
                    $query->where($Operator->FiltersOperators(['parcels.' . $ex[0], $ex[1], $ex[2]]));
                }
            }

            if ($request->has('start_at')) {
                $query->where('parcels.created_at', '>=', $request->query('start_at'));
            }

            if ($request->has('end_at')) {
                $query->where('parcels.created_at', '<=', $request->query('end_at'));
            }

            if ($request->has('searchText')) {
                $arraySearchText = ['parcels.track_no', 'parcels.name', 'parcels.phone', 'parcels.zto_track_no'];
                $query->whereAny($arraySearchText, 'like', '%' . $request->query('searchText') . '%');
            }

            if ($request->has('sorts')) {
                $arraySorts = explode(',', $request->query('sorts', []));
                foreach ($arraySorts as $sort) {
                    [$field, $direction] = explode(':', $sort);
                    $query->orderBy($field, $direction);
                }
            }

            if ($request->has('status')) {
                switch ($request->query('status')) {
                    case 'refund':
                        $query->leftJoin('return_parcels', 'parcels.id', '=', 'return_parcels.parcel_id')
                            ->where('return_parcels.parcel_id', '=', null)
                            ->select(
                                'parcels.*',
                                'parcels.weight as weight',
                                'return_parcels.weight as refund_weight'
                            );
                        break;
                        // ->where('income_expenses.status', '=', 'verify')
                    case 'return':
                        $query->leftJoin('return_parcels', 'parcels.id', '=', 'return_parcels.parcel_id')
                            ->leftJoin('income_expenses', 'income_expenses.id', '=', 'return_parcels.income_expenses_id')
                            ->select(
                                'parcels.*',
                                'parcels.weight as weight',
                                'return_parcels.refund_amount_lak',
                                'return_parcels.refund_amount_cny',
                                'return_parcels.weight as refund_weight'
                            );
                        break;
                    default:
                        break;
                }
            } else {
                $query->with(['Bill' => function ($bill) {
                    $bill->select('id', 'status');
                }, 'Bill.Payments' => function ($pay) {
                    $pay->select('payments.id');
                }])->select('parcels.*', 'shipping_at', 'receipt_at', 'payment_at');
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
                'msg' => $e->getMessage(),
                'status' => 'ERROR',
                'error' => array(),
                'code' => 400
            ], 400);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request) {}

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
    public function update_check(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'is_check' => 'required|array',
            'is_check.*' => 'string',
        ]);
        
        DB::beginTransaction();
        try {
            // update parcels is_check from requests
            $parcels = Parcel::whereIn('track_no', $request->is_check)->get();
            foreach ($parcels as $parcel) {
                $parcel->is_check = true;
                $parcel->save();
            }

            DB::commit();
            return response()->json([
                'status' => 'Updated check',
                'code' => 200,
                'data' => []
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'msg' => 'Something went wrong.',
                'errors' => $e->getMessage(),
                'status' => 'ERROR',
            ], 400);
        }
        
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make(request()->all(), [
            'name' => 'string',
            'phone' => 'numeric',
            'status' => 'required|string|in:pending,ready,success',
        ]);

        if ($validator->fails()) {
            $errors_val = $this->ValidatorErrors($validator);
            return response()->json([
                'msg' => 'validator errors',
                'errors' => $errors_val,
                'status' => 'ERROR',
            ], 400);
        }

        DB::beginTransaction();
        try {
            $parcel = Parcel::find($id);
            if (!$parcel) {
                return response()->json([
                    'status' => 'Not Found',
                    'code' => 404,
                    'msg' => 'Parcel not found',
                ], 404);
            }

            $parcel->name = $request->name;
            $parcel->phone = $request->phone;
            $parcel->status = $request->status;
            $parcel->save();

            DB::commit();
            return response()->json([
                'status' => 'Updated',
                'code' => 200,
                'data' => $parcel
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'msg' => 'Something went wrong.',
                'errors' => $e->getMessage(),
                'status' => 'ERROR',
            ], 400);
        }
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
    public function export(Request $request)
    {
        $validator = Validator::make(request()->all(), [
            'start_at' => 'date_format:Y-m-d',
            'end_at' => 'date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            $errors_val = $this->ValidatorErrors($validator);
            return response()->json([
                'msg' => 'validator errors',
                'errors' => $errors_val,
                'status' => 'ERROR',
            ], 400);
        }

        try {
            $query = Parcel::query();

            if ($request->has('start_at')) {
                $query->where('created_at', '>=', $request->query('start_at'));
            }
            if ($request->has('end_at')) {
                $query->where('created_at', '<=', $request->query('end_at'));
            }

            if ($request->has('sorts')) {
                $arraySorts = explode(',', $request->query('sorts', []));
                foreach ($arraySorts as $sort) {
                    [$field, $direction] = explode(':', $sort);
                    $query->orderBy($field, $direction);
                }
            }

            $parcel = $query->get();

            return Excel::download(new ParcelExport($parcel), 'parcels-' . Carbon::now()->format('Y-m-d') . '.xlsx');
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
            $parcel_track_no = array_filter(array_column($parcelArray, 'track_no'));
            $parcel_track_no = array_unique($parcel_track_no);

            $parcelTrackNoCreated = Parcel::whereIn('track_no', $parcel_track_no)
                ->where(function ($query) {
                    $query->orWhere('status', '<>', 'return')
                        ->orWhere('deleted_at', '==', null);
                })
                ->get()
                ->pluck('track_no')
                ->toArray();
            $parcelTrackNoDiff = array_diff($parcel_track_no, $parcelTrackNoCreated);

            $parcelArrCreate = collect($parcelArray)->whereIn('track_no', $parcelTrackNoDiff)->toArray();

            $customer_phone = array_filter(array_column($parcelArray, 'phone'));
            $customer_phone = array_unique($customer_phone);

            $customerPhoneCreated = Customer::whereIn('phone', $customer_phone)->get()->pluck('phone')->toArray();
            $customerPhoneDiff = array_diff($customer_phone, $customerPhoneCreated);

            $customerArr = [];
            foreach ($customerPhoneDiff as $key => $value) {
                $cusName = collect($parcelArray)->where('phone', $value)->pluck('name')->first();
                $customer = [
                    'phone' => $value,
                    'name' => $cusName ?? null,
                    'address' => null,
                    'active' => false,
                ];
                array_push($customerArr, $customer);
            }

            Customer::insert($customerArr);
            Parcel::insert($parcelArrCreate);

            
            DB::commit();

            foreach ($customer_phone as $key => $value) {
                $this->CreateBillByPhone($value);
            }

            return response()->json([
                'status' => true,
                'message' => 'Import excel file Successfully',
                'code' => 201,
                'data' => [
                    'totalParcel' => count($parcelArrCreate),
                ]
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
