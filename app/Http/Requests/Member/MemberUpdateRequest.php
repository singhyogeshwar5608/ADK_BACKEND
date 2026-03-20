<?php

namespace App\Http\Requests\Member;

use Illuminate\Foundation\Http\FormRequest;

class MemberUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('fullName')) {
            $payload['full_name'] = $this->input('fullName');
        }

        if ($this->has('sponsorId')) {
            $payload['sponsor_id'] = $this->input('sponsorId');
        }

        if ($this->has('leg')) {
            if ($this->filled('leg')) {
                $payload['leg'] = strtoupper((string) $this->input('leg'));
            } else {
                $this->request->remove('leg');
            }
        }

        if ($this->has('status')) {
            if ($this->filled('status')) {
                $payload['status'] = strtoupper((string) $this->input('status'));
            } else {
                $this->request->remove('status');
            }
        }

        if ($this->has('profileImage')) {
            $payload['profile_image'] = $this->input('profileImage');
        }

        if (! empty($payload)) {
            $this->merge($payload);
        }
    }

    public function rules(): array
    {
        return [
            'full_name' => ['sometimes', 'string', 'min:2'],
            'email' => ['sometimes', 'email'],
            'phone' => ['sometimes', 'string'],
            'status' => ['sometimes', 'in:ACTIVE,SUSPENDED,PENDING'],
            'leg' => ['sometimes', 'in:LEFT,RIGHT'],
            'profile_image' => ['sometimes', 'nullable', 'url'],
        ];
    }
}
