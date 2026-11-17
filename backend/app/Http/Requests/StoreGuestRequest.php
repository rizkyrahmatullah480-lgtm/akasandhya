<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'whatsapp_number' => ['required', 'string', 'max:20', 'regex:/^(\+?62|0)8[1-9][0-9]{7,11}$/'],
            'category' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Accept numbers typed with spaces, dashes, dots or parentheses, then
     * validate the compact form. The service still normalizes to 62xxxx.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('whatsapp_number'))) {
            $this->merge([
                'whatsapp_number' => preg_replace('/[\s\-().]+/', '', $this->input('whatsapp_number')),
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama tamu wajib diisi.',
            'whatsapp_number.required' => 'Nomor WhatsApp wajib diisi.',
            'whatsapp_number.regex' => 'Nomor WhatsApp tidak valid. Gunakan format 08xxxxxxxxxx atau +62xxxxxxxxxx.',
            'category.max' => 'Kategori tamu maksimal 100 karakter.',
        ];
    }
}
