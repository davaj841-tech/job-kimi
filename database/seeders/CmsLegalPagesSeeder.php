<?php

namespace Database\Seeders;

use App\Support\LegalPages;
use Illuminate\Database\Seeder;

class CmsLegalPagesSeeder extends Seeder
{
    public function run(): void
    {
        LegalPages::ensure();
    }
}
