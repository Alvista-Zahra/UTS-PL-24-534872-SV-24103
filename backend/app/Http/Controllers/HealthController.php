<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $results = [
            // Benar-benar menjalankan query ke basis data.
            'database' => $this->probe(function () {
                DB::select('select 1');
            }),

            // Benar-benar menulis lalu membaca kembali satu nilai dari cache.
            'cache' => $this->probe(function () {
                $key = 'health-check:'.Str::random(8);

                Cache::put($key, 'ok', 10);
                $value = Cache::get($key);
                Cache::forget($key);

                if ($value !== 'ok') {
                    throw new RuntimeException('cache tidak mengembalikan nilai yang baru ditulis');
                }
            }),
        ];

        $problems = array_filter($results, fn (array $r) => ! $r['ok']);
        $failed = array_keys($problems);

        if ($failed !== []) {
            // ERROR: sebab lengkap hanya masuk log, tidak dikirim ke pemanggil.
            Log::error('health.check_failed', [
                'actor' => 'system',
                'action' => 'health_check',
                'reason' => 'dependency_unavailable',
                'failed' => $failed,
                'details' => array_map(fn (array $r) => $r['reason'], $problems),
            ]);
        }

        return response()->json([
            'status' => $failed === [] ? 'healthy' : 'unhealthy',
            'checks' => array_map(fn (array $r) => $r['ok'] ? 'ok' : 'down', $results),
            'failed' => $failed,
        ], $failed === [] ? 200 : 503);
    }

    /**
     * Menjalankan satu pemeriksaan. Gagal = exception, hasilnya dibungkus agar
     * satu komponen yang rusak tidak menggagalkan pemeriksaan komponen lain.
     */
    private function probe(callable $check): array
    {
        try {
            $check();

            return ['ok' => true, 'reason' => null];
        } catch (Throwable $e) {
            // Pesan PDOException (penyebab asli) tidak memuat SQL maupun password.
            $cause = $e->getPrevious() ?? $e;

            return [
                'ok' => false,
                'reason' => class_basename($cause).': '.Str::limit($cause->getMessage(), 160),
            ];
        }
    }
}