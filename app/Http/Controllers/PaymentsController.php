<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

class PaymentsController extends Controller
{
    /**
     * Store a newly created resource in storage.
     * @throws ConnectionException
     */
    public function payments(PaymentRequest $request)
    {
        $data = $request->validated();
//        Redis::set('payment_processor', getenv('PAYMENT_PROCESSOR_URL'));
//        Redis::set('payment_processor', getenv('PAYMENT_PROCESSOR_FALLBACK_URL'));

        $uuid = $request->input('correlationId');
        $amount = $request->input('amount');

        try {
            $response = Http::timeout(1)->connectTimeout(1)->post(Redis::get('payment_processor'), $data);

            if ($response->successful()) {
                self::callPipelining($uuid, $amount, 1);
            } else {
                $response = Http::timeout(1)->connectTimeout(1)->post(getenv('PAYMENT_PROCESSOR_FALLBACK_URL'), $data);

                if ($response->successful()) {
                    self::callPipelining($uuid, $amount, 2);
                }
            }

            return response()->json(1);


        } catch (ConnectionException | RequestException $e) {
            return response()->json(['error' => 'Payment processor unavailable'], 503);
        }

        return response()->json(['error' => 'Payment processor unavailable'], 503);
    }

    protected static function callPipelining($uuid, $amount, $processor)
    {
        Redis::pipeline(function ($pipe) use ($uuid, $amount, $processor) {
            $pipe->xadd('pay_success', '*', [
                'uuid' => $uuid,
                'amount' => $amount,
                'processor' => $processor,
                'ts' => now()->toIso8601String(),
            ]);

            $pipe->incr('pay_count');
            $pipe->incrbyfloat('pay_amount_sum', $amount);
        });
    }
}
