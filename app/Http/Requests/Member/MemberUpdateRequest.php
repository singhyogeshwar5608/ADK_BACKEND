<?php

namespace App\Http\Requests\Member;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

        if ($this->has('password')) {
            $payload['password'] = $this->input('password');
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

        // KYC fields
        if ($this->has('bankAccountNumber')) {
            $payload['bank_account_number'] = $this->input('bankAccountNumber');
        }
        if ($this->has('bankAccountImage')) {
            $payload['bank_account_image'] = $this->input('bankAccountImage');
        }
        if ($this->has('panNumber')) {
            $payload['pan_number'] = $this->input('panNumber');
        }
        if ($this->has('panImage')) {
            $payload['pan_image'] = $this->input('panImage');
        }
        if ($this->has('aadharNumber')) {
            $payload['aadhar_number'] = $this->input('aadharNumber');
        }
        if ($this->has('aadharImage')) {
            $payload['aadhar_image'] = $this->input('aadharImage');
        }
        if ($this->has('aadharBackImage')) {
            $payload['aadhar_back_image'] = $this->input('aadharBackImage');
        }
        if ($this->has('qrCodeImage')) {
            $payload['qr_code_image'] = $this->input('qrCodeImage');
        }

        // Nominee fields
        if ($this->has('nomineeName')) {
            $payload['nominee_name'] = $this->input('nomineeName');
        }
        if ($this->has('nomineeAadharNumber')) {
            $payload['nominee_aadhar_number'] = $this->input('nomineeAadharNumber');
        }
        if ($this->has('nomineeAadharImage')) {
            $payload['nominee_aadhar_image'] = $this->input('nomineeAadharImage');
        }
        if ($this->has('nomineeAadharBackImage')) {
            $payload['nominee_aadhar_back_image'] = $this->input('nomineeAadharBackImage');
        }

        if (! empty($payload)) {
            $this->merge($payload);
        }
    }

    public function rules(): array
    {
        return [
            'full_name' => ['sometimes', 'string', 'min:2'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'email' => ['sometimes', 'email'],
            'phone' => ['sometimes', 'string'],
            'status' => ['sometimes', 'in:ACTIVE,SUSPENDED,PENDING'],
            'type' => ['sometimes', 'in:LEADER,USER'],
            'leg' => ['sometimes', 'in:LEFT,RIGHT'],
            'profile_image' => ['sometimes', 'nullable', 'url'],
            // Sponsor re-assignment (resolved + cycle-guarded in MemberController)
            'sponsor_id' => ['sometimes', 'nullable', 'string', 'max:50'],
            // KYC fields
            'bank_account_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'bank_account_image' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'pan_number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'pan_image' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'aadhar_number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'aadhar_image' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'aadhar_back_image' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'qr_code_image' => ['sometimes', 'nullable', 'string', 'max:2048'],
            // Nominee fields
            'nominee_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'nominee_aadhar_number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'nominee_aadhar_image' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'nominee_aadhar_back_image' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ];
    }
}
