<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        // Tes tidak membutuhkan aset frontend. Tanpa ini, setiap request halaman gagal 500 di CI
        // karena public/build/manifest.json belum ada (npm run build berjalan setelah tes).
        $this->withoutVite();
    }
}
