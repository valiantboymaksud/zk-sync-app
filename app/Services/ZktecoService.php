<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\ZkUser;
use MehediJaman\LaravelZkteco\LaravelZkteco;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ZktecoService
{
    protected $zk;

    public function __construct()
    {
        $this->zk = new LaravelZkteco(config('zkteco.ip'), config('zkteco.port'));
        $this->zk->connect();
    }

    public function fetchUsers(): array
    {
        try {
            $this->zk->disableDevice();
            $users = $this->zk->getUser();
        } finally {
            $this->zk->enableDevice();
            $this->zk->disconnect();
        }

        $result = [];
        foreach ($users as $user) {
            $result[] = [
                'uid'         => $user[0] ?? null,
                'user_id'     => $user[1] ?? null,
                'name'        => $user[2] ?? null,
                'card_number' => $user[3] ?? null,
                'privilege'   => $user[4] ?? null,
            ];
        }

        return $result;
    }

    public function fetchLogs(?string $date = null): array
    {
        try {
            $this->zk->disableDevice();
            $logs = $this->zk->getAttendance();
        } finally {
            $this->zk->enableDevice();
            $this->zk->disconnect();
        }

        $targetDate = $date
            ? Carbon::parse($date)->format('Y-m-d')
            : Carbon::now()->format('Y-m-d');

        $result = [];
        foreach ($logs as $log) {
            $timestamp = isset($log[2]) ? Carbon::parse($log[2])->format('Y-m-d H:i:s') : null;
            if (!$timestamp) continue;

            if (strpos($timestamp, $targetDate) !== 0) continue;

            $result[] = [
                'uid'       => $log[0] ?? null,
                'user_id'   => $log[1] ?? null,
                'timestamp' => $timestamp,
                'state'     => $log[3] ?? null,
                'verif'     => $log[4] ?? null,
                'device_id' => $log[5] ?? null,
                'status'    => $log[6] ?? 'check',
            ];
        }

        return $result;
    }

    public function bulkUpsertLogs(array $logs, array $zkUserMap): int
    {
        $insertData = [];
        foreach ($logs as $log) {
            $userId = $log['user_id'];
            $userInfo = $zkUserMap[$userId] ?? ['id' => null, 'card_number' => null];

            $insertData[] = [
                'user_id'     => $userId,
                'zk_user_id'  => $userInfo['id'],
                'card_number' => $userInfo['card_number'],
                'timestamp'   => $log['timestamp'],
                'status'      => $log['status'],
                'verif'       => $log['verif'],
                'device_id'   => $log['device_id'],
                'data'        => json_encode([...$log, 'card_number' => $userInfo['card_number']]),
                'created_at'  => now(),
                'updated_at'  => now(),
            ];
        }

        if (!empty($insertData)) {
            DB::table('attendances')->upsert(
                $insertData,
                ['user_id', 'timestamp'],
                ['zk_user_id', 'card_number', 'status', 'verif', 'device_id', 'data', 'updated_at']
            );
        }

        return count($insertData);
    }

    public function storeSingleAttendance(array $log, ?array $userInfo = null): bool
    {
        $attendance = Attendance::updateOrCreate(
            [
                'user_id'   => $log['user_id'],
                'timestamp' => $log['timestamp'],
            ],
            [
                'zk_user_id'  => $userInfo['id'] ?? null,
                'card_number' => $log['card_number'] ?? null,
                'status'      => $log['status'] ?? null,
                'verif'       => $log['verif'] ?? null,
                'device_id'   => $log['device_id'] ?? null,
                'data'        => json_encode($log, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            ]
        );

        return $attendance->wasRecentlyCreated;
    }
}
