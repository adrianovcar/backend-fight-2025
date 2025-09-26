<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class PaymentProcessorProberService
{
    public function probeAndElect(): void
    {
        $results = [];
        $candidates = [
            'default' => env('PAYMENT_PROCESSOR_URL'),
            'fallback' => env('PAYMENT_PROCESSOR_FALLBACK_URL'),
        ];

        foreach ($candidates as $name => $baseUrl) {
            $gateKey = "health:gate:{$name}";
            $acquired = Redis::set($gateKey, '1', 'NX', 'EX', 5);
            $url = "{$baseUrl}/payments/service-health";

            if (!$acquired) {
                $results[$name] = $this->getSnapshot($name) ?: null;
                continue;
            }

            try {
                $resp = Http::timeout(2)
                    ->connectTimeout(0.002)
                    ->acceptJson()
                    ->get($url);

                if ($resp->ok()) {
                    $payload = [
                        'name' => $name,
                        'baseUrl' => $baseUrl,
                        'failing' => (bool) $resp->json('failing', true),
                        'minResponseTime' => (int) $resp->json('minResponseTime', PHP_INT_MAX),
                        'checkedAt' => now()->toIso8601String(),
                    ];
                    $results[$name] = $payload;
                    Redis::setex("health:snapshot:{$name}", 30, json_encode($payload));
                } elseif ($resp->tooManyRequests()) {
                    $results[$name] = $this->getSnapshot($name) ?: null;
                }
            } catch (\Throwable $e) {
                $results[$name] = $this->getSnapshot($name) ?: null;
            }
        }

        self::setElected($results);
    }

    protected function setElected(array $results): void
    {
        $alive = array_values(array_filter($results, fn($r) => isset($r['failing']) && $r['failing'] === false));
        if (!empty($alive)) {
            usort($alive, fn($a, $b) => $a['minResponseTime'] <=> $b['minResponseTime']);
            $winner = $alive[0];
            $current = Redis::get('payment_processor');
            $new     = $winner['baseUrl'];

            if ($current !== $new) {
                Log::info("***** New processor elected: $new");
                Redis::set('payment_processor', $new);
            }
        } else {
            Redis::set('payment_processor', null);
            Log::info("***** No alive processors found");
        }
    }

    protected function getSnapshot(string $name): ?array
    {
        $snapshot = Redis::get("health:snapshot:{$name}");
        return $snapshot ? json_decode($snapshot, true) : null;
    }
}
