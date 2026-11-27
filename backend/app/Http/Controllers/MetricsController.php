<?php

namespace App\Http\Controllers;

use App\Http\Middleware\CollectMetrics;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class MetricsController extends Controller
{
    /**
     * Mengeluarkan metrik dalam format teks Prometheus.
     */
    public function __invoke(): Response
    {
        $counts = Cache::get(CollectMetrics::CACHE_KEY, []);
        ksort($counts);

        $lines = [
            '# HELP http_requests_total Jumlah permintaan HTTP yang diterima backend.',
            '# TYPE http_requests_total counter',
        ];

        foreach ($counts as $key => $total) {
            [$method, $route, $status] = explode('|', $key, 3);

            $lines[] = sprintf(
                'http_requests_total{method="%s",route="%s",status="%s"} %d',
                $this->escape($method),
                $this->escape($route),
                $this->escape($status),
                $total
            );
        }

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', '"', "\n"], ['\\\\', '\\"', '\\n'], $value);
    }
}
