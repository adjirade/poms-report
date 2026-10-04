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

    // Identitas perusahaan untuk kop surat dokumen PDF (laporan/export).
    'company_name' => env('COMPANY_NAME', 'PT Sawit Sejahtera Abadi'),
    'company_address' => env('COMPANY_ADDRESS', ''),

    // Kode unik pabrik ini (spoke). Contoh: PKS_01, PKS_02, dst.
    'plant_id' => env('PLANT_ID', 'PKS_01'),

    // Nama pabrik untuk tampilan laporan.
    'plant_name' => env('PLANT_NAME', 'Pabrik Kelapa Sawit 01'),

    // Selisih jam maksimum (timestamp_kirim vs timestamp_server) sebelum
    // record otomatis di-flag sebagai anomali waktu.
    'time_discrepancy_hours' => env('VALIDATION_TIME_DISCREPANCY_HOURS', 4),

    // Jam kerja shift untuk analisis per-shift (end melewati start = shift
    // lintas tengah malam). Jam end eksklusif, start inklusif.
    'shifts' => [
        'Shift 1' => ['start' => '06:00', 'end' => '14:00'],
        'Shift 2' => ['start' => '14:00', 'end' => '22:00'],
        'Shift 3' => ['start' => '22:00', 'end' => '06:00'],
    ],

    // Password akun bootstrap dari `php artisan db:seed`. Kosong = generator
    // acak (dicetak SEKALI ke console). Jangan hardcode di repo.
    'bootstrap_admin_password' => env('ADMIN_INITIAL_PASSWORD', ''),
    'bootstrap_operator_password' => env('OPERATOR_INITIAL_PASSWORD', ''),

    // Data demo (DemoDataSeeder): dashboard/chart terisi riwayat ~30 hari.
    // Aktifkan dengan POMS_SEED_DEMO=true saat db:seed / migrate:fresh --seed.
    // JANGAN aktifkan di produksi.
    'seed_demo' => env('POMS_SEED_DEMO', false),

    // Password akun demo DemoDataSeeder. Kosong (default) = setiap user demo
    // mendapat password acak UNIK yang dicetak/ditulis sekali saat seeding.
    // Diisi = semua user demo memakai password yang sama (mis. QA/testing).
    'demo_password' => env('POMS_DEMO_PASSWORD', ''),

    // Chat ID Telegram penerima alert operasional (sync gagal, backup gagal,
    // dsb). Kosong = alert hanya masuk ke file log.
    'alert_telegram_chat_id' => env('POMS_ALERT_TELEGRAM_CHAT_ID', ''),

    // Target KPI (A6 — Target vs Realisasi). Kosong = pakai default di
    // App\Support\KpiTargetConfig. Dapat ditimpa per stasiun/parameter, mis.:
    //   'targets' => ['lab' => ['kadar_alb_cpo' => ['max' => 4.0]]],
    //   'plant_targets' => ['ffa' => ['max' => 4.5]],
    'targets' => [],
    'plant_targets' => [],
];
