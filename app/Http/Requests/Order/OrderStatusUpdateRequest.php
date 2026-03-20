<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class OrderStatusUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:PENDING,PROCESSING,SHIPPED,DELIVERED,CANCELLED'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
