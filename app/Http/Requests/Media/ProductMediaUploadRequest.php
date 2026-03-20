<?php

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;

class ProductMediaUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->hasFile('files') && !is_array($this->file('files'))) {
            $this->files->set('files', [$this->file('files')]);
        }
    }

    public function validationData(): array
    {
        $data = parent::validationData();
        $data['files'] = $this->files->get('files', []);

        return $data;
    }

    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:6'],
            'files.*' => ['file', 'mimes:jpg,jpeg,png,webp,avif', 'max:5120'],
        ];
    }
}
