<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class OrderIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

   protected function prepareForValidation(): void
{
    $status = $this->input('status');
    $paymentStatus = $this->input('paymentStatus', $this->input('payment_status'));
    $memberSearch = $this->input('memberSearch', $this->input('member_search'));

    if ($status) {
        $status = strtoupper($status);
    }

    if ($paymentStatus) {
        $paymentStatus = strtoupper($paymentStatus);
    }

    if (!is_string($memberSearch)) {
        $memberSearch = null;
    }

    $this->merge([
        'page' => (int) $this->input('page', 1),
        'limit' => (int) $this->input('limit', 10),
        'status' => $status,
        'payment_status' => $paymentStatus,
        'member_search' => $memberSearch,
    ]);
}

    public function rules(): array
{
    return [
        'page' => ['integer','min:1'],
        'limit' => ['integer','min:1','max:100'],
        'status' => ['nullable','in:PENDING,PROCESSING,SHIPPED,DELIVERED,CANCELLED'],
        'payment_status' => ['nullable','in:PENDING,PAID,REFUNDED,FAILED'],
        'member_search' => ['nullable','string'],
    ];
}
}
