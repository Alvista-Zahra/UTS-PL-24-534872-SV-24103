<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CollectMetrics
{
    public const CACHE_KEY = 'metrics:http_requests_total';

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Dijalankan setelah respons dikirim, jadi tidak menambah waktu tunggu pengguna.
     * Yang dihitung: jumlah permintaan per kombinasi method, rute, dan status.
     */
    public function terminate(Request $request, Response $response): void
    {
        try {
            // Pakai pola rute (api/products/{id}), bukan URL asli, supaya jumlah label tetap kecil.
            $route = $request->route()?->uri() ?? 'unmatched';

            if ($route === 'metrics') {
                return; // scrape Prometheus tidak ikut dihitung
            }

            $key = $request->method().'|'.$route.'|'.$response->getStatusCode();

            $counts = Cache::get(self::CACHE_KEY, []);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
            Cache::forever(self::CACHE_KEY, $counts);
        } catch (\Throwable) {
            // Gagal menghitung metrik tidak boleh mengganggu aplikasi.
        }
    }
}
