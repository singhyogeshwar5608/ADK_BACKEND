<?php

namespace App\Http\Requests\AdkEvent;

use Illuminate\Foundation\Http\FormRequest;

class AdkEventIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->input('search'),
            'start_date' => $this->input('start_date', $this->input('startDate')),
            'end_date' => $this->input('end_date', $this->input('endDate')),
        ]);
    }
}
