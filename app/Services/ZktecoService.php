<?php

namespace App\Services;

use MehediJaman\LaravelZkteco\LaravelZkteco;

class ZktecoService
{
    protected $zk;

    public function __construct()
    {
        $this->zk = new LaravelZkteco(config('zkteco.ip'), config('zkteco.port'));
        $this->zk->connect();
    }

    public function fetchLogs(): array
    {
        $this->zk->disableDevice();
        $logs = $this->zk->getAttendance();
        $this->zk->enableDevice();
        $this->zk->disconnect();

        return array_map(function ($log) {
            return [
                'uid'       => $log[0] ?? null, // internal device log ID (optional)
                'user_id'   => $log[1],         // enrolled user ID from device
                'timestamp' => date('Y-m-d H:i:s', strtotime($log[2])),
                'state'     => $log[3] ?? null, // typically 0 = check-in, 1 = check-out
                'verif'     => $log[4] ?? null, // verification method (fingerprint/card)
                'device_id' => $log[5] ?? null, // optional if you handle multiple devices
                'status'    => $log[6] ?? 'check', // custom field if needed (default: "check")
            ];
        }, $logs);
    }
}
