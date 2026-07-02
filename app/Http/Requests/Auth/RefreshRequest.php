<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RefreshRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'refresh_token' => ['required', 'string', 'min:10'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('refreshToken')) {
            $this->merge([
                'refresh_token' => $this->input('refreshToken'),
            ]);
        }
    }
}
