<?php

namespace App\Http\Controllers;

use App\Models\Parcel;
use App\Models\ParcelBalanceTransaction;
use App\Models\ZtoBalanceCredit;
use App\Models\ZtoBalanceTransaction;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ZtoBalanceCreditController extends Controller
{


    public function reportParcelTopup(Request $request)
    {

        $validator = Validator::make(request()->all(), [
            'start_at' => 'date_format:Y-m-d H:i:s',
            'end_at' => 'date_format:Y-m-d H:i:s',
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
            $parcelBalance_parcel = ParcelBalanceTransaction::select(
                'parcel_balance_transactions.*',
                'parcels.track_no',
                'parcels.zto_track_no',
                'parcels.weight',
                'parcels.price', 
                'parcels.created_at as import_at',
                // DB::raw('CASE WHEN parcels.price IS NOT NULL THEN parcels.price * -1 ELSE 0 END as cost_price'),
            )
                ->leftJoin('parcels', 'parcels.id', '=', 'parcel_balance_transactions.parcel_id')
                ->orderBy('parcel_balance_transactions.id', 'desc');

            if ($request->start_at && $request->end_at) {
                $parcelBalance_parcel->whereBetween('parcel_balance_transactions.created_at', [$request->start_at, $request->end_at]);
            } else {
                $parcelBalance_parcel->whereDate('parcel_balance_transactions.created_at', Carbon::now());
            }

            if ($request->has('searchText')) {
                $arraySearchText = ['parcels.track_no'];
                foreach ($arraySearchText as $key => $value) {
                    $parcelBalance_parcel->orWhereLike($value,  '%' . $request->query('searchText') . '%');
                }
            }
            // dd($parcelBalance_parcel->toSql());
            $data_parcelBalance = $parcelBalance_parcel->get();

            $responseData = [];
            $responseData['parcel_balance'] = $data_parcelBalance;
            $responseData['total'] = [
                'weight' => number_format($parcelBalance_parcel->sum('weight'), 2),
                'cost_price' => number_format($parcelBalance_parcel->sum('price') * -1, 2),
                'balance' => number_format($parcelBalance_parcel->sum('balance_amount_lak'), 2),
            ];

            return response()->json([
                'data' => $responseData,
                'status' => 'OK',
                'code' => 200,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'msg' => 'Get Parcel Balance Credit failed',
                'errors' => $e->getMessage(),
                'status' => 'ERROR',
                'data' => [],
            ], 400);
        }
    }


    public function createTopup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pay_cash' => 'numeric|nullable',
            'pay_transfer' => 'numeric|nullable',
            'pay_alipay' => 'numeric|nullable',
            'pay_wechat' => 'numeric|nullable',
            'description' => 'string|nullable',
            'amount' => 'required|numeric',
            'bank' => 'required|string',
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
            $balanceTransaction_last = ZtoBalanceTransaction::orderBy('id', 'desc')->first();
            $balanceTransaction = new ZtoBalanceTransaction();
            if ($balanceTransaction_last) {
                $balanceTransaction->balance_amount_lak = $balanceTransaction_last->balance_amount_lak + $request->amount;
            } else {
                $balanceTransaction->balance_amount_lak = $request->amount;
            }
            $balanceTransaction->amount_lak = $request->amount;
            $balanceTransaction->pay_cash = isset($request->pay_cash) ? $request->pay_cash : null;
            $balanceTransaction->pay_transfer = isset($request->pay_transfer) ? $request->pay_transfer : null;
            $balanceTransaction->pay_alipay = isset($request->pay_alipay) ? $request->pay_alipay : null;
            $balanceTransaction->pay_wechat = isset($request->pay_wechat) ? $request->pay_wechat : null;
            $balanceTransaction->description = $request->description;
            $balanceTransaction->bank_name = $request->bank;
            $balanceTransaction->save();

            $balanceCredit_last = ZtoBalanceCredit::orderBy('id', 'desc')->first();
            // $balanceCredit = new ZtoBalanceCredit();
            if ($balanceCredit_last) {
                $balanceCredit_last->balance_amount_lak = $balanceCredit_last->balance_amount_lak + $request->amount;
                $balanceCredit_last->save();

                // $balanceCredit->balance_amount_lak = $balanceCredit_last->balance_amount_lak + $request->amount;
                // $balanceCredit_last->delete();
            } else {
                $balanceCredit = new ZtoBalanceCredit();
                $balanceCredit->balance_amount_lak = $request->amount;
                $balanceCredit->save();

                // $balanceCredit->balance_amount_lak = $request->amount;
            }
            // $balanceCredit->save();


            DB::commit();
            return response()->json([
                'code' => 200,
                'msg' => 'TopUp Credit created',
                'status' => 'OK',
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'msg' => 'TopUp Credit failed',
                'errors' => $e->getMessage(),
                'status' => 'ERROR',
            ], 400);
        }
    }
}
