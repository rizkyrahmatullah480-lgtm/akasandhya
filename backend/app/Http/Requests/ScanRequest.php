<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'token' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'event_id.required' => 'Pilih acara terlebih dahulu.',
            'event_id.exists' => 'Acara tidak ditemukan.',
            'token.required' => 'Token undangan tidak boleh kosong.',
        ];
    }
}
