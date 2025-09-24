<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

class PaymentsController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function payments(PaymentRequest $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validated();
        // Store request into redis cache with prefix 'queue'
        Redis::rpush('queue', json_encode($data));

        $response = Http::post(getenv('PAYMENT_PROCESSOR_URL'), $data);

        return response()->json($response->json(), $response->status());
    }
}
