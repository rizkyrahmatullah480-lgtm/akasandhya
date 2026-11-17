<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public application URL
    |--------------------------------------------------------------------------
    | Used to build the guest invitation links that are shared over WhatsApp.
    */
    'frontend_url' => rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/'),

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Business Cloud API (Meta)
    |--------------------------------------------------------------------------
    | Leave WHATSAPP_API_ENABLED=false to keep the app in Click-to-Chat mode.
    | Secrets must never be exposed to the SvelteKit frontend.
    */
    'api_enabled' => (bool) env('WHATSAPP_API_ENABLED', false),
    'api_version' => env('WHATSAPP_API_VERSION', 'v20.0'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),

    // If a template name is configured the API mode uses a WhatsApp message
    // template (required outside the 24 hour customer service window).
    // The template must accept six body parameters in this order:
    // recipient name, event name, date, time, location, invitation link.
    // In Mode Santri the recipient name is the parent/guardian name.
    'template_name' => env('WHATSAPP_TEMPLATE_NAME'),
    'template_language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'id'),

    'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Default Click-to-Chat message
    |--------------------------------------------------------------------------
    */
    'message_template' => env('WHATSAPP_MESSAGE_TEMPLATE', implode("\n", [
        'Assalamu\'alaikum Wr. Wb.',
        '',
        'Kepada Yth. *{nama_tamu}*',
        '',
        'Dengan penuh hormat, kami mengundang Bapak/Ibu untuk hadir dalam acara:',
        '',
        '*{nama_acara}*',
        '',
        'Tanggal: {tanggal_acara}',
        'Waktu: {waktu_acara}',
        'Lokasi: {lokasi_acara}',
        '',
        'Undangan digital dan QR Code kehadiran dapat dibuka melalui tautan berikut:',
        '{link_undangan}',
        '',
        'Merupakan suatu kehormatan bagi kami apabila Bapak/Ibu berkenan hadir.',
        '',
        'Wassalamu\'alaikum Wr. Wb.',
    ])),

];
