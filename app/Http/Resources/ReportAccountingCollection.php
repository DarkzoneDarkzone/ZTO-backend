<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportAccountingCollection extends JsonResource
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_no' => $this->payment_no,
            'cash' => $this->cash ? $this->cash : '-',
            'transffer' => $this->transffer ? $this->transffer : '-',
            'airpay' => $this->airpay ? $this->airpay : '-',
            'wechat_pay' => $this->wechat_pay ? $this->wechat_pay : '-',
            'amount' => $this->amount,
            'bill_reference' => $this->Bills->pluck('bill_no')->implode(','),
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : '',
        ];
    }
}
