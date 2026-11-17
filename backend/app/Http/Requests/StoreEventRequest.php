<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'event_type' => ['required', 'string', 'in:pernikahan,ulang_tahun,seminar,wisuda,santri,formal,keluarga,lainnya'],
            'organizer' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date'],
            'event_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/'],
            'location' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'banner' => ['nullable', 'string', 'max:2048'],
            'template_id' => ['nullable', 'integer', 'exists:templates,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama acara wajib diisi.',
            'event_type.required' => 'Jenis acara wajib dipilih.',
            'event_type.in' => 'Jenis acara tidak valid.',
            'organizer.required' => 'Nama penyelenggara wajib diisi.',
            'event_date.required' => 'Tanggal acara wajib diisi.',
            'event_date.date' => 'Format tanggal acara tidak valid.',
            'event_time.required' => 'Waktu acara wajib diisi.',
            'event_time.regex' => 'Format waktu acara harus HH:MM.',
            'location.required' => 'Lokasi acara wajib diisi.',
            'template_id.exists' => 'Desain undangan yang dipilih tidak ditemukan.',
        ];
    }
}
