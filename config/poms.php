<?php

return [

    /*
    |--------------------------------------------------------------------------
    | POMS Application Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi spesifik aplikasi POMS Report. Menggunakan file config
    | (bukan pemanggilan env() langsung di kode) agar tetap aman ketika
    | `php artisan config:cache` dijalankan di produksi.
    |
    */

    // Kode unik pabrik ini (spoke). Contoh: PKS_01, PKS_02, dst.
    'plant_id' => env('PLANT_ID', 'PKS_01'),

    // Nama pabrik untuk tampilan laporan.
    'plant_name' => env('PLANT_NAME', 'Pabrik Kelapa Sawit 01'),

    // Selisih jam maksimum (timestamp_kirim vs timestamp_server) sebelum
    // record otomatis di-flag sebagai anomali waktu.
    'time_discrepancy_hours' => env('VALIDATION_TIME_DISCREPANCY_HOURS', 4),
];
