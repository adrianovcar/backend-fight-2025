<?php

use App\Http\Controllers\PaymentsController;
use App\Http\Requests\PaymentRequest;
use Illuminate\Support\Facades\Route;

Route::get('/up', function () {
    return "ok!";
});

Route::get('/admin/gateway', fn(App\Support\GatewaySelector $g) => [
    'active' => $g->getActive('A')
]);

Route::get('/payments-summary', fn(App\Support\ResultStore $s) => $s->latest(200));

// post for /payments endpoint, with fields:
//  "correlationId": UUID ex(4a7901b8-7d26-4d9d-aa19-4dc1c7cf60b3)
//  "amount": decimal (ex 19.90)
Route::post('/payments', [PaymentsController::class, 'payments']);
