<?php

namespace Database\Admin\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AdminCatalogSeeder::class);
    }
}
