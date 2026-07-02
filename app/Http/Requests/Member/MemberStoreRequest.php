<?php

namespace App\Http\Requests\Member;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MemberStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $emailRaw = $this->input('email');
        $email =
            $emailRaw !== null && is_string($emailRaw) && trim($emailRaw) !== ''
                ? strtolower(trim($emailRaw))
                : null;

        $this->merge([
            'full_name' => $this->input('fullName', $this->input('full_name')),
            'sponsor_id' => $this->input('sponsorId', $this->input('sponsor_id')),
            'leg' => strtoupper((string) $this->input('leg')) ?: null,
            'password' => $this->input('password'),
            'profile_image' => $this->input('profileImage', $this->input('profile_image')),
            'address' => $this->input('address'),
            'email' => $email,
        ]);
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:2'],
            'email' => ['nullable', 'email', 'unique:members,email'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', Rule::unique('members', 'phone')->whereNotNull('phone')],
            'address' => ['required', 'string', 'min:5'],
            'sponsor_id' => ['required', 'string'],
            'leg' => ['required', 'in:LEFT,RIGHT'],
            'profile_image' => ['nullable', 'url'],
            'type' => ['sometimes', 'in:LEADER,USER'],
        ];
    }
}
