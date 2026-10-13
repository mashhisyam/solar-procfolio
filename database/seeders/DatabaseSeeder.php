<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Data referensi wajib. Jalankan DemoSeeder terpisah untuk data contoh: php artisan db:seed --class=DemoSeeder */
    public function run(): void
    {
        $this->call(ReferenceSeeder::class);
    }
}
