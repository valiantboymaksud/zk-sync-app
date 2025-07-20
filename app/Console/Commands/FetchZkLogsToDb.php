<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ZktecoService;
use App\Models\Attendance;

class FetchZkLogsToDb extends Command
{
    protected $signature = 'zk:fetch-db';
    protected $description = 'Fetch attendance logs from ZKTeco and save to database';

    public function handle(ZktecoService $zk)
    {
        $logs = $zk->fetchLogs();

        $count = 0;
        foreach ($logs as $log) {
            // Insert or update attendance record
            $attendance = Attendance::updateOrCreate(
                [
                    'user_id' => $log['user_id'],
                    'timestamp' => $log['timestamp'],
                ],
                [
                    'status' => $log['status'] ?? null,
                    'verif' => $log['verif'] ?? null,
                    'device_id' => $log['device_id'] ?? null,
                    'data' => json_encode($log), // store full log data
                ]
            );
            if ($attendance->wasRecentlyCreated) {
                $count++;
            }
        }

        $this->info("Fetched " . count($logs) . " logs, inserted $count new records.");
    }
}

