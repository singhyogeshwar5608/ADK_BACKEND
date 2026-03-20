<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:2'],
            'email' => ['required', 'email', Rule::unique('members', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'string'],
            'role' => ['nullable', Rule::in(['ADMIN', 'MEMBER'])],
            'sponsor_id' => ['nullable', 'string'],
            'leg' => ['nullable', Rule::in(['LEFT', 'RIGHT'])],
            'profile_image' => ['nullable', 'url'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'full_name' => $this->input('full_name', $this->input('fullName')),
            'email' => strtolower($this->input('email', $this->input('username', ''))),
            'sponsor_id' => $this->input('sponsor_id', $this->input('sponsorId')),
            'profile_image' => $this->input('profile_image', $this->input('profileImage')),
        ]);
    }
}
