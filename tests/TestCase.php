<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // Pengaman: artefak `php artisan optimize` (config/routes/views cache)
        // dibuat dari .env lokal dan TIDAK BOLEH bocor ke environment testing —
        // config cache menimpa env dari phpunit.xml (APP_ENV, HQ_API_TOKEN, dll)
        // sehingga test gagal dengan 419/401 yang menyesatkan. Hapus file cache
        // SEBELUM aplikasi boot.
        foreach (glob(__DIR__.'/../bootstrap/cache/*.php') ?: [] as $cached) {
            @unlink($cached);
        }

        parent::setUp();
    }
}
