<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendWhatsAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', 'in:click,api'],
            'message' => ['nullable', 'string', 'max:4000'],
        ];
    }

    public function messages(): array
    {
        return [
            'mode.required' => 'Mode pengiriman WhatsApp wajib dipilih.',
            'mode.in' => 'Mode pengiriman WhatsApp tidak valid.',
        ];
    }
}
