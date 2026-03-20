<?php

namespace App\Http\Requests\Member;

use Illuminate\Foundation\Http\FormRequest;

class MemberStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'full_name' => $this->input('fullName', $this->input('full_name')),
            'sponsor_id' => $this->input('sponsorId', $this->input('sponsor_id')),
            'leg' => strtoupper((string) $this->input('leg')) ?: null,
            'password' => $this->input('password'),
            'profile_image' => $this->input('profileImage', $this->input('profile_image')),
        ]);
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:2'],
            'email' => ['required', 'email', 'unique:members,email'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'string'],
            'sponsor_id' => ['required', 'string'],
            'leg' => ['required', 'in:LEFT,RIGHT'],
            'profile_image' => ['nullable', 'url'],
        ];
    }
}
