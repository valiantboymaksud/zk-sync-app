<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ZktecoService;
use App\Models\ZkUser;

class SyncZkUsers extends Command
{
    protected $signature = 'zk:sync-users';
    protected $description = 'Sync all users from ZKTeco device into local database';

    public function handle(ZktecoService $zk)
    {
        $users = $zk->fetchUsers();
        $count = 0;

        foreach ($users as $user) {
            if (empty($user['user_id'])) continue;

            ZkUser::updateOrCreate(
                ['user_id' => $user['user_id']],
                [
                    'name'        => $user['name'] ?? '',
                    'card_number' => $user['card_number'] ?? '',
                    'data'        => json_encode($user),
                ]
            );
            $count++;
        }

        $this->info("✅ Synced {$count} users from ZKTeco.");
    }
}
