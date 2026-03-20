<?php

namespace App\Http\Requests\Member;

use Illuminate\Foundation\Http\FormRequest;

class MemberIndexRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string'],
            'status' => ['nullable', 'in:ACTIVE,SUSPENDED,PENDING'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'page' => $this->input('page', 1),
            'limit' => $this->input('limit', 10),
        ]);

        if ($this->filled('search')) {
            $this->merge(['search' => $this->input('search')]);
        } else {
            $this->request->remove('search');
        }

        $status = $this->input('status');

        if (filled($status)) {
            $this->merge(['status' => strtoupper((string) $status)]);
        } else {
            $this->merge(['status' => null]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }
}
