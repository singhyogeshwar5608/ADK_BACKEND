<?php

namespace App\Http\Requests\EventMedia;

use Illuminate\Foundation\Http\FormRequest;

class EventMediaUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'ADMIN';
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
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['file', 'max:20480', 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/webm'],
        ];
    }
}
