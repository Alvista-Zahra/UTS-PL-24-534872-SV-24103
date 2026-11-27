<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Endpoint /metrics untuk Prometheus (format teks exposition).
 *
 * Semua nilai dihitung ulang setiap kali di-scrape, jadi tidak perlu
 * penyimpanan bersama antar-worker PHP. Kalau backend mati, scrape gagal
 * dan Prometheus otomatis mencatat up{job="laravel-backend"} = 0.
 */
class MetricsController extends Controller
{
    public function __invoke(): Response
    {
        $started = microtime(true);

        $databaseUp = 0;
        $products = null;
        $users = null;

        try {
            DB::select('select 1');
            $databaseUp = 1;
            $products = Product::count();
            $users = User::count();
        } catch (Throwable) {
            // Database tidak bisa dihubungi: cukup laporkan 0, jangan bocorkan pesan error.
        }

        $lines = [
            '# HELP app_info Informasi build aplikasi backend.',
            '# TYPE app_info gauge',
            sprintf('app_info{app="uts-backend",php_version="%s",laravel_version="%s"} 1', PHP_VERSION, app()->version()),
            '# HELP app_database_up Apakah backend berhasil menjalankan query ke database (1 = ya, 0 = tidak).',
            '# TYPE app_database_up gauge',
            'app_database_up '.$databaseUp,
        ];

        if ($products !== null) {
            $lines[] = '# HELP app_products_total Jumlah produk yang tersimpan di database.';
            $lines[] = '# TYPE app_products_total gauge';
            $lines[] = 'app_products_total '.$products;
            $lines[] = '# HELP app_users_total Jumlah pengguna terdaftar.';
            $lines[] = '# TYPE app_users_total gauge';
            $lines[] = 'app_users_total '.$users;
        }

        $lines[] = '# HELP app_metrics_duration_seconds Lama waktu backend menyusun respons /metrics.';
        $lines[] = '# TYPE app_metrics_duration_seconds gauge';
        $lines[] = sprintf('app_metrics_duration_seconds %.6f', microtime(true) - $started);

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }
}
