<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ZktecoService;
use App\Models\Attendance;
use App\Models\ZkUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FetchZkLogsToDbOld extends Command
{
    protected $signature = 'zk:fetch-db {--date=}';
    protected $description = 'Fetch attendance logs from ZKTeco and save to database';

    public function handle(ZktecoService $zk)
    {
        // Prevent overlapping with cache lock
        $lock = Cache::lock('zk:fetch-db', 300); // 5 minutes

        if (!$lock->get()) {
            $this->warn("⚠️ Previous sync still running. Skipping this run.");
            return 'cronjob overlap';
        }

        try {
            // Default to today
            $date = $this->option('date') ?: today()->toDateString();

            $this->info("🔄 Fetching logs for {$date}...");

            // Fetch logs from device (filtered by date)
            $logs = $zk->fetchLogs($date);
            $this->info("📥 Retrieved " . count($logs) . " logs from device.");

            // Preload all users from DB for quick mapping
            $zkUsers = ZkUser::query()->select('id', 'user_id', 'card_number')->get();
            $zkUserMap = [];
            foreach ($zkUsers as $user) {
                $zkUserMap[$user->user_id] = [
                    'id' => $user->id,
                    'card_number' => $user->card_number,
                ];
            }

            $count = 0;

            foreach ($logs as $log) {
                try {
                    $mappedUser = $zkUserMap[$log['user_id']] ?? null;

                    // Merge local card_number if missing in device log
                    if (empty($log['card_number']) && $mappedUser) {
                        $log['card_number'] = $mappedUser['card_number'];
                    }

                    // Insert or update attendance
                    $attendance = $zk->storeSingleAttendance($log, $mappedUser);

                    if ($attendance) {
                        $count++;
                    }
                } catch (\Throwable $th) {
                    Log::error('Error processing log: ' . $th->getMessage());
                }
            }

            $this->info("✅ Processed " . count($logs) . " logs, inserted {$count} new records.");
        } catch (\Throwable $e) {
            $this->error("❌ Sync failed: " . $e->getMessage());
            report($e);
        } finally {
            $lock->release();
        }
    }
}
