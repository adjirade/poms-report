<?php

return [

    /*
    |--------------------------------------------------------------------------
    | HQ Cloud Sync (PRD Section 2.2 - Hub-and-Spoke)
    |--------------------------------------------------------------------------
    |
    | Setiap pabrik (spoke) mengirim data terverifikasi ke Cloud Kantor Pusat
    | (hub) melalui scheduled job terenkripsi (HTTPS + API Token).
    |
    */

    // Master switch: hanya pabrik (spoke) yang mengaktifkan push ke HQ.
    'enabled' => env('HQ_SYNC_ENABLED', false),

    // Base URL dari Cloud HQ Server, contoh: https://hq.example.com/api
    'api_url' => env('HQ_API_URL', ''),

    // Shared API token. Harus sama dengan HQ_API_TOKEN di sisi HQ server.
    'api_token' => env('HQ_API_TOKEN', ''),

    // Hanya kirim data yang sudah diverifikasi (sesuai PRD 2.2).
    'sync_verified_only' => env('HQ_SYNC_VERIFIED_ONLY', true),

    // Jumlah maksimum record per request (membatasi payload size).
    'batch_size' => env('HQ_SYNC_BATCH_SIZE', 200),

    // HTTP timeout dalam detik untuk push ke HQ.
    'timeout' => env('HQ_SYNC_TIMEOUT', 30),

    // Paksa semua URL aplikasi ke https:// (set true di produksi di belakang
    // reverse proxy / load balancer TLS). Default false untuk pengembangan lokal.
    'force_https' => env('APP_FORCE_HTTPS', false),
];
