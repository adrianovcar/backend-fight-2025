<?php

namespace App\Support;

use Illuminate\Support\Facades\Redis;

class ResultStore
{
    private const IDX = 'res:idx'; // lista de chaves recentes
    private const IDX_MAX = 20000; // ajuste conforme seu cenário

    public function save(string $id, array $payload): void
    {
        $key = "res:{$id}";
        Redis::pipeline(function ($pipe) use ($key, $payload) {
            $pipe->hMSet($key, $payload);
            $pipe->lPush(self::IDX, $key);
            $pipe->lTrim(self::IDX, 0, self::IDX_MAX - 1);
            // TTL opcional para “apagar” velho no tempo:
            // $pipe->expire($key, 86400);
        });
    }

    public function get(string $id): ?array
    {
        $key = "res:{$id}";
        $data = Redis::hGetAll($key);
        return $data ? $data : null;
    }

    public function latest(int $limit = 100): array
    {
        $keys = Redis::lRange(self::IDX, 0, $limit - 1);
        if (!$keys) {
            return [];
        }
        $pipe = Redis::pipeline();
        foreach ($keys as $k) {
            $pipe->hGetAll($k);
        }
        return array_filter($pipe->exec());
    }
}
