<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'file' => ['required_without:guests', 'file', 'mimes:csv,txt', 'max:4096'],
            'guests' => ['required_without:file', 'array'],
            'guests.*.name' => ['required', 'string', 'max:255'],
            'guests.*.whatsapp_number' => ['required', 'string', 'max:20'],
            'guests.*.category' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required_without' => 'Pilih file CSV yang akan diimpor.',
            'file.mimes' => 'File harus berformat CSV.',
            'guests.required_without' => 'Data tamu tidak boleh kosong.',
            'guests.*.name.required' => 'Setiap tamu harus memiliki nama.',
            'guests.*.whatsapp_number.required' => 'Setiap tamu harus memiliki nomor WhatsApp.',
        ];
    }

    /**
     * Normalize phone numbers in the JSON payload before validation so
     * numbers with spaces or dashes are accepted. Per-row validation still
     * happens in the controller so one bad row does not reject the batch.
     */
    protected function prepareForValidation(): void
    {
        $guests = $this->input('guests');

        if (!is_array($guests)) {
            return;
        }

        foreach ($guests as $index => $row) {
            if (is_array($row) && isset($row['whatsapp_number']) && is_string($row['whatsapp_number'])) {
                $guests[$index]['whatsapp_number'] = preg_replace('/[\s\-().]+/', '', $row['whatsapp_number']);
            }
        }

        $this->merge(['guests' => $guests]);
    }
}
