<?php

namespace App\Providers;

use App\Services\PaymentProcessorProberService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;

class OctaneServiceProvider extends ServiceProvider
{
    public function boot(PaymentProcessorProberService $prober): void
    {
        // roda a cada 5s em cada pod, mas só o que pegar o lock executa
        if ($this->app->bound('octane')) {
            $octane = $this->app->make('octane');
            $octane->tick('payment-processor-prober', function () use ($prober) {
                $lock = Cache::lock('probe:leader', 4); // TTL levemente menor que o intervalo
                if ($lock->get()) {
                    try {
                        $prober->probeAndElect();
                    } finally {
                        optional($lock)->release();
                    }
                }
            });
        }
    }
}
