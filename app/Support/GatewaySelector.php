<?php
namespace App\Support;

use Illuminate\Support\Facades\Redis;

class GatewaySelector
{
    private const KEY = 'gw:active'; // ex: 'A' | 'B'

    public function setActive(string $id, int $ttlSeconds = 10): void
    {
        Redis::set(self::KEY, $id, 'EX', $ttlSeconds);
    }

    public function getActive(?string $fallback = null): ?string
    {
        return Redis::get(self::KEY) ?? $fallback;
    }
}
