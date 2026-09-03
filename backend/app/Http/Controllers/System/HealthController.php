<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->databaseIsReady(),
            'cache' => $this->cacheIsReady(),
        ];
        $ready = !in_array(false, $checks, true);

        return response()->json([
            'status' => $ready ? 'ready' : 'not_ready',
            'checks' => $checks,
        ], $ready ? 200 : 503);
    }

    private function databaseIsReady(): bool
    {
        try {
            return DB::selectOne('SELECT 1 AS ready') !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    private function cacheIsReady(): bool
    {
        $key = 'health:'.Str::random(20);

        try {
            Cache::put($key, 'ready', 10);
            $ready = Cache::get($key) === 'ready';
            Cache::forget($key);
            return $ready;
        } catch (\Throwable) {
            return false;
        }
    }
}
