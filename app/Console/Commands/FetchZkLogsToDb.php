<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ZktecoService;
use App\Models\ZkUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FetchZkLogsToDb extends Command
{
    protected $signature = 'zk:fetch-db {--date=} {--mode=auto}';
    protected $description = 'Fetch attendance logs from ZKTeco and save to database';

    public function handle(ZktecoService $zk)
    {
        $lock = Cache::lock('zk:fetch-db', 300); // 5 minutes

        if (!$lock->get()) {
            $this->warn("⚠️ Previous sync still running. Skipping this run.");
            return 0;
        }

        try {
            $date = $this->option('date') ?: today()->toDateString();
            $mode = $this->option('mode'); // auto | single | bulk

            $this->info("🔄 Fetching logs for {$date}...");

            $logs = $zk->fetchLogs($date);
            $this->info("📥 Retrieved " . count($logs) . " logs from device.");

            // Load users for mapping
            $zkUsers = ZkUser::select('id', 'user_id', 'card_number')->get();
            $zkUserMap = [];
            foreach ($zkUsers as $user) {
                $zkUserMap[$user->user_id] = [
                    'id' => $user->id,
                    'card_number' => $user->card_number,
                ];
            }

            // Decide mode automatically if not specified
            if ($mode === 'auto') {
                $mode = count($logs) > 200 ? 'bulk' : 'single';
            }

            if ($mode === 'bulk') {
                $this->info("🚀 Running in bulk upsert mode...");
                $totalInserted = 0;

                collect($logs)
                    ->chunk(500)
                    ->each(function ($chunk) use ($zk, $zkUserMap, &$totalInserted) {
                        $totalInserted += $zk->bulkUpsertLogs($chunk->toArray(), $zkUserMap);
                    });

                $this->info("✅ Bulk inserted/updated {$totalInserted} records.");
            } else {
                $this->info("🧩 Running in single insert mode...");
                $count = 0;

                foreach ($logs as $log) {
                    try {
                        $mappedUser = $zkUserMap[$log['user_id']] ?? null;

                        if (empty($log['card_number']) && $mappedUser) {
                            $log['card_number'] = $mappedUser['card_number'];
                        }

                        $attendance = $zk->storeSingleAttendance($log, $mappedUser);

                        if ($attendance) $count++;
                    } catch (\Throwable $th) {
                        Log::error('Error processing log: ' . $th->getMessage());
                    }
                }

                $this->info("✅ Inserted {$count} new records (single mode).");
            }

        } catch (\Throwable $e) {
            $this->error("❌ Sync failed: " . $e->getMessage());
            report($e);
        } finally {
            $lock->release();
        }

        return 0;
    }
}
