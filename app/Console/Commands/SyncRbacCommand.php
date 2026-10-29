<?php

namespace App\Console\Commands;

use App\Services\Rbac\RbacSynchronizer;
use Illuminate\Console\Command;

final class SyncRbacCommand extends Command
{
    protected $signature = 'rbac:sync';

    protected $description = 'Apply config/rbac.php to the database (permissions, roles and grants)';

    public function handle(RbacSynchronizer $synchronizer): int
    {
        $result = $synchronizer->sync();

        $this->info('Permissions created: '.$result['permissions_created']);
        $this->info('Roles synced: '.implode(', ', $result['roles']));

        foreach ($result['orphaned_permissions'] as $name) {
            $this->warn("Permission [{$name}] exists in the database but not in config/rbac.php. Remove it if unused.");
        }

        return self::SUCCESS;
    }
}
