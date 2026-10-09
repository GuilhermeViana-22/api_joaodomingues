<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'nome' => 'João Domingues',
        'api' => url('/api/v1'),
    ]);
});
