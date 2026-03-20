<?php

namespace App\Http\Requests\AdkEvent;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdkEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'leader_name' => ['required', 'string', 'min:2'],
            'meeting_date' => ['required', 'date'],
            'meeting_time' => ['required'],
            'store_name' => ['required', 'string', 'min:2'],
            'address' => ['required', 'string'],
            'state' => ['required', 'string'],
            'city' => ['required', 'string'],
            'leader_mobile' => ['required', 'digits:10'],
            'store_mobile' => ['required', 'digits:10'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
