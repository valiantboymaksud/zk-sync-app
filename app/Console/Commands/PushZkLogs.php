<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class PushZkLogs extends Command
{
    protected $signature = 'zk:push';
    protected $description = 'Push stored logs to remote Laravel server';

    public function handle()
    {
        // $files = glob(storage_path('zk_logs_*.json'));

        // if (empty($files)) {
        //     $this->warn('No logs found to push.');
        //     return;
        // }

        $logs = Attendance::whereNull('synced_at')->get();

        foreach ($logs as $log) {
            $response = Http::withHeaders([
                'X-Sync-Token' => env('ZK_SYNC_TOKEN'),
            ])
            ->post(env('ZK_REMOTE_ENDPOINT'), [
                'logs' => $log,
            ]);

            if ($response->successful()) {
                $log->update([
                    'synced_at' => now()
                ]);
                $this->info("Pushed log for user {$log->user_id} at {$log->timestamp}");
            } else {
                $this->error("Failed to push log for user {$log->user_id} at {$log->timestamp}");
            }
        }
    }
}
