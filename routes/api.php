<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/up', function () {
    return "ok!";
});

Route::get('/admin/gateway', fn(App\Support\GatewaySelector $g) => [
    'active' => $g->getActive('A')
]);

Route::get('/admin/results', fn(App\Support\ResultStore $s) => $s->latest(200));
