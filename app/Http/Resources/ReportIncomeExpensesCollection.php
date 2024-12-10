<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportIncomeExpensesCollection extends JsonResource
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
            'description' => $this->description ?  $this->description :  ($this->payment_no ? 'shipping' : '-'),
            'amount_lak' => $this->amount_lak ? $this->amount_lak : '-',
            'amount_cny' => $this->amount_cny ? $this->amount_cny : '-',
            'balance_amount_lak' => $this->balance_amount_lak ? $this->balance_amount_lak : '-',
            'balance_amount_cny' => $this->balance_amount_cny ? $this->balance_amount_cny : '-',
            'top_up_lak' => $this->top_up_lak ? $this->top_up_lak : '-',
            'top_up_cny' => $this->top_up_cny ? $this->top_up_cny : '-',
            'shipping_lak' => $this->shipping_lak ? $this->shipping_lak : ($this->amount_lak ? $this->amount_lak : '-'),
            'shipping_cny' => $this->shipping_cny ? $this->shipping_cny : ($this->amount_cny ? $this->amount_cny : '-'),
            'expenses_lak' => $this->expenses_lak ? $this->expenses_lak : '-',
            'expenses_cny' => $this->expenses_cny ? $this->expenses_cny : '-',
            'bill_reference' => isset($this->Payment) ? $this->Payment->Bills->pluck('bill_no')->implode(',') : null,
            'created_at' => $this->created_at,
            'type' => $this->type,
            'sub_type' => $this->sub_type,
        ];
    }
}
