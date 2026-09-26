<?php
namespace Modules\Assessment\Database\Seeders;

use Illuminate\Database\Seeder;

class AssessmentDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AssessmentCatalogSeeder::class,
        ]);
    }
}
