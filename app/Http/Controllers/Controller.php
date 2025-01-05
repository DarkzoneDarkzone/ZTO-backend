<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Parcel;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

abstract class Controller
{
    public function ValidatorErrors($validator)
    {
        $errors_val = array();
        foreach ($validator->errors()->toArray() as $key => $value) {
            $errors_val = array_merge($errors_val, $value);
        }
        return $errors_val;
    }

    public function CreateBillByPhone($phone)
    {
        // Validate phone number and not 0 and not null and not empty
        if (isset($phone) && $phone != 0) {
            DB::beginTransaction();
            try {
                $auth_id = Auth::user()->id;
                $bill = new Bill();
                $customer = Customer::with([
                    'CustomerLevel' => function ($query) {
                        $query->select('id', 'rate');
                    },
                ])->where('phone', $phone)->where('status', true)->first();
                if (isset($customer) && isset($customer->CustomerLevel->rate)) {
                    $rate = $customer->CustomerLevel->rate;
                    $price_bill_lak  = 0;
                    $parcels = Parcel::where('phone', $phone)->where('status', 'pending')->get();
                    foreach ($parcels as $parcel) {
                        $price_bill_lak += ceil(floor(($parcel->weight * $rate)) / 1000) * 1000;
                    }
                    $currency_now = Currency::orderBy('id', 'desc')->first();
                    $amount_cny_convert = $price_bill_lak / ($currency_now->amount_cny * $currency_now->amount_lak);
                    $bill->amount_lak = $price_bill_lak;
                    $bill->amount_cny = ceil($amount_cny_convert * 100) / 100;
                    $bill->name = $customer->name;
                    $bill->phone = $phone;
                    $bill->address = $customer->address;
                    $bill->status = 'ready';
                    $bill->created_by = $auth_id;
                    $currentDate = Carbon::now()->format('ym');
                    $billCount = Bill::whereYear('created_at', Carbon::now()->year)
                        ->whereMonth('created_at', Carbon::now()->month)
                        ->orderBy('id', 'desc')->first();
                    if (!$billCount) {
                        $bill->bill_no = $currentDate . '-' . sprintf('%05d', 00001);
                    } else {
                        $ex = explode('-', $billCount->bill_no);
                        $number = (int) $ex[1];
                        $bill->bill_no = $currentDate . '-' . sprintf('%05d', $number + 1);
                    }
                    $bill->save();

                    foreach ($parcels as $parcel) {
                        $parcel->bill_id = $bill->id;
                        $parcel->status = 'ready';
                        if ($parcel->phone == 0) {
                            $parcel->phone = $phone;
                            $parcel->name = $customer->name;
                        }
                        $parcel->price_bill = $parcel->weight * $rate;
                        $parcel->save();
                    }
                    DB::commit();
                }
                return 1;
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
    }
}
