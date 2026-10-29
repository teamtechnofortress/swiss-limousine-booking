<?php

namespace Database\Seeders;

use App\Services\Rbac\RbacSynchronizer;
use Illuminate\Database\Seeder;

final class RbacSeeder extends Seeder
{
    public function run(RbacSynchronizer $synchronizer): void
    {
        $synchronizer->sync();
    }
}
