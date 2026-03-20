<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class DashboardMetricsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'range' => (int) $this->input('range', 30),
        ]);
    }

    public function rules(): array
    {
        return [
            'range' => ['sometimes', 'integer', 'min:7', 'max:180'],
        ];
    }
}
