<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'status' => 'ok',
        'service' => config('app.name'),
        'api_health' => url('/api/health'),
    ]);
});

Route::get('/symlink-storage', function () {
    try {
        Artisan::call('storage:link');

        return response()->json([
            'status' => 'success',
            'message' => trim(Artisan::output()) ?: 'The [public/storage] link has been connected.',
        ]);
    } catch (Throwable $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
});
