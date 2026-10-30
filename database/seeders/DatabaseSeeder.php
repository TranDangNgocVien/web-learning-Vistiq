<?php

namespace Database\Seeders;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Database\Learner\Seeders\DictionaryGrammarSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([AdminCatalogSeeder::class, DictionaryGrammarSeeder::class]);
    }
}
