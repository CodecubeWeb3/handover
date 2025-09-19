<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class HealthCheckController
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'app' => ['status' => 'ok', 'version' => App::version()],
            'database' => $this->check(function (): void {
                DB::select('select 1');
            }),
            'cache' => $this->check(function (): void {
                Cache::remember('health-check', 5, static fn () => now()->timestamp);
            }),
            'queue' => $this->check(function (): void {
                if (Config::get('queue.default') === 'database' && ! Schema::hasTable('jobs')) {
                    throw new \RuntimeException('jobs table missing');
                }
            }),
        ];

        $statusCode = collect($checks)->contains(fn ($check) => ($check['status'] ?? 'fail') !== 'ok') ? 503 : 200;

        return response()->json([
            'status' => $statusCode === 200 ? 'ok' : 'fail',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $statusCode);
    }

    private function check(callable $callback): array
    {
        try {
            $callback();

            return ['status' => 'ok'];
        } catch (Throwable $exception) {
            return [
                'status' => 'fail',
                'error' => $exception->getMessage(),
            ];
        }
    }
}