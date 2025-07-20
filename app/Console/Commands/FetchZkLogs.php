<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ZktecoService;

class FetchZkLogs extends Command
{
    protected $signature = 'zk:fetch';
    protected $description = 'Fetch attendance logs from ZKTeco and save to local JSON file';

    public function handle(ZktecoService $zk)
    {
        $logs = $zk->fetchLogs();

        $filename = storage_path('zk_logs_' . now()->format('Y_m_d_His') . '.json');
        file_put_contents($filename, json_encode($logs, JSON_PRETTY_PRINT));

        $this->info("Saved " . count($logs) . " logs to $filename");
    }
}

